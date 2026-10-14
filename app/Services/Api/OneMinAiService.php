<?php
declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Settings;
use App\Services\HttpClient;

/**
 * Service d'analyse de confiance client via 1min.ai (API OpenAI-compatible).
 * Analyse les données d'une entreprise et retourne un avis : confiance oui/non + explications.
 */
class OneMinAiService
{
    private const ENDPOINT = 'https://api.1min.ai/openai/v1/chat/completions';

    public function isEnabled(): bool
    {
        return Settings::bool('oneminai_enabled', false) && $this->apiKey() !== '';
    }

    private function apiKey(): string
    {
        return trim((string) (Settings::get('oneminai_api_key') ?: Config::get('apis.oneminai.api_key', '')));
    }

    private function model(): string
    {
        return trim((string) (Settings::get('oneminai_model') ?: Config::get('apis.oneminai.model', 'gpt-4o-mini')));
    }

    private function timeout(): int
    {
        return (int) Config::get('apis.oneminai.timeout', 120);
    }

    /**
     * Analyse les données d'une entreprise.
     * @param array<string,mixed> $client
     * @param float|null $encours
     * @return array{confiance:bool,score:int,decision:string,raison:string,risques:array<int,string>,recommandations:array<int,string>,analyse:string,raw?:mixed}
     */
    public function analyserConfiance(array $client, ?float $encours = null): array
    {
        if (!$this->isEnabled()) {
            return [
                'confiance'        => false,
                'score'            => 0,
                'decision'         => 'indisponible',
                'raison'           => "L'analyse IA n'est pas configurée.",
                'risques'          => [],
                'recommandations'   => [],
                'analyse'          => '',
            ];
        }

        $system = "Tu es un analyste de risque crédit spécialisé dans le recouvrement (litiges BTP, véhicules, machines). "
            . "Analyse les données d'une entreprise pour donner un avis objectif : Faut-il faire confiance à ce client pour poursuivre/accorder un délai ? "
            . "Réponds UNIQUEMENT au format JSON strict, sans texte hors JSON. "
            . "Schéma attendu : {\n"
            . "  \"confiance\": bool,\n"
            . "  \"score\": int (0-100, 100 = confiance maximale),\n"
            . "  \"decision\": \"faire_confiance\"|\"faire_attention\"|\"ne_pas_faire_confiance\",\n"
            . "  \"raison\": string (explication courte, max 200 mots),\n"
            . "  \"risques\": [string],\n"
            . "  \"recommandations\": [string],\n"
            . "  \"analyse\": string (analyse détaillée, max 400 mots)\n"
            . "} "
            . "Sois factuel, pondère : ancienneté, rentabilité (CA/résultat), procédure collective/BODACC, statut, encours vs CA, effectif, forme juridique. "
            . "N'invente pas de chiffres. Si une donnée est absente, indique-le.";

        $donnees = [
            'raison_sociale'       => $client['raison_sociale'] ?? null,
            'siren'                => $client['siren'] ?? null,
            'siret'                => $client['siret'] ?? null,
            'forme_juridique'      => $client['forme_juridique'] ?? null,
            'adresse'              => $client['adresse'] ?? null,
            'code_postal'          => $client['code_postal'] ?? null,
            'ville'                => $client['ville'] ?? null,
            'date_creation'        => $client['date_creation'] ?? null,
            'effectif'             => $client['effectif'] ?? null,
            'capital'              => $client['capital'] ?? null,
            'chiffre_affaires'     => $client['chiffre_affaires'] ?? null,
            'resultat_net'         => $client['resultat_net'] ?? null,
            'annee_finances'       => $client['annee_finances'] ?? null,
            'statut_entreprise'    => $client['statut_entreprise'] ?? null,
            'procedure_collective' => $client['procedure_collective'] ?? null,
            'score_solvabilite'    => $client['score_solvabilite'] ?? null,
            'encours_interne'      => $encours,
            'source_api'           => $client['source_api'] ?? null,
        ];

        $user = "Analyse ces données d'entreprise (format JSON) :\n" . json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $body = [
            'model'    => $this->model(),
            'stream'   => false,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.2,
            'max_tokens'  => 800,
        ];

        $response = HttpClient::post(self::ENDPOINT, $body, [
            'Authorization' => 'Bearer ' . $this->apiKey(),
            'Content-Type'  => 'application/json',
        ], $this->timeout());

        if ($response['error'] !== null || $response['status'] < 200 || $response['status'] >= 300) {
            return [
                'confiance'        => false,
                'score'            => 0,
                'decision'         => 'erreur',
                'raison'           => 'Erreur lors de l\'analyse IA.',
                'risques'          => [],
                'recommandations'   => [],
                'analyse'          => mb_substr((string) ($response['body'] ?? ''), 0, 300),
            ];
        }

        $content = $response['json']['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            return [
                'confiance'        => false,
                'score'            => 0,
                'decision'         => 'erreur',
                'raison'           => 'Réponse IA vide.',
                'risques'          => [],
                'recommandations'   => [],
                'analyse'          => '',
            ];
        }

        $content = trim($content);
        // Extraire JSON si entouré de ```json
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $m)) {
            $content = trim($m[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $content = trim($m[0]);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [
                'confiance'        => false,
                'score'            => 0,
                'decision'         => 'erreur',
                'raison'           => 'Format de réponse IA invalide.',
                'risques'          => [],
                'recommandations'   => [],
                'analyse'          => $content,
            ];
        }

        return [
            'confiance'        => (bool) ($decoded['confiance'] ?? false),
            'score'            => (int) max(0, min(100, $decoded['score'] ?? 0)),
            'decision'         => (string) ($decoded['decision'] ?? 'faire_attention'),
            'raison'           => (string) ($decoded['raison'] ?? ''),
            'risques'          => is_array($decoded['risques']) ? array_values($decoded['risques']) : [],
            'recommandations'   => is_array($decoded['recommandations']) ? array_values($decoded['recommandations']) : [],
            'analyse'          => (string) ($decoded['analyse'] ?? ''),
            'raw'              => $decoded,
        ];
    }
}
