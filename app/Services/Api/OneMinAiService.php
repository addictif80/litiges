<?php
declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Audit;
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
        return $this->normaliserCle((string) (Settings::get('oneminai_api_key') ?: Config::get('apis.oneminai.api_key', '')));
    }

    /**
     * Nettoie une cle API : espaces, sauts de ligne et espaces insecables
     * (frequents lors d'un copier/coller depuis une page web).
     */
    private function normaliserCle(string $cle): string
    {
        return preg_replace('/[\s\x{00A0}\x{2007}\x{200B}\x{202F}\x{2060}\x{FEFF}]+/u', '', $cle) ?? trim($cle);
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
     * Certains modeles (OpenAI raisonnement GPT-5/GPT-6/o*, DeepSeek, Z.AI/GLM)
     * rejettent temperature/top_p/... avec une erreur 400 au lieu de les ignorer.
     * Voir la doc 1min.ai « Supported request fields ».
     */
    private function modeleSansEchantillonnage(): bool
    {
        return (bool) preg_match('/^(gpt-5|gpt-6|o[1-9]|deepseek|glm)/', strtolower($this->model()));
    }

    /** Plafond de sortie : plus large pour les modeles de raisonnement (jetons de raisonnement inclus). */
    private function maxTokens(): int
    {
        return $this->modeleSansEchantillonnage() ? 2000 : 800;
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
            'model'       => $this->model(),
            'stream'      => false,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'max_tokens'  => $this->maxTokens(),
        ];
        if (!$this->modeleSansEchantillonnage()) {
            $body['temperature'] = 0.2;
        }

        $response = HttpClient::post(self::ENDPOINT, $body, [
            'Authorization' => 'Bearer ' . $this->apiKey(),
            'Content-Type'  => 'application/json',
        ], $this->timeout());

        if ($response['error'] !== null || $response['status'] < 200 || $response['status'] >= 300) {
            $detail = $this->erreurLisible($response);
            $this->journaliserErreur($response, $detail);

            return [
                'confiance'        => false,
                'score'            => 0,
                'decision'         => 'erreur',
                'raison'           => $detail,
                'risques'          => [],
                'recommandations'   => [],
                'analyse'          => '',
            ];
        }

        $content = $response['json']['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            $this->journaliserErreur($response, 'Reponse IA vide.');

            return [
                'confiance'        => false,
                'score'            => 0,
                'decision'         => 'erreur',
                'raison'           => 'Reponse IA vide (modele « ' . $this->model() . ' » ; essayez d\'augmenter la limite de jetons ou changez de modele).',
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

    /**
     * Teste la configuration : valide la cle API (GET /openai/v1/models) et
     * verifie que le modele choisi figure dans la liste des modeles accessibles.
     *
     * @return array{ok:bool,message:string,modeles:array<int,string>}
     */
    public function tester(?string $modele = null, ?string $cle = null): array
    {
        $cle    = ($cle !== null && $cle !== '') ? $this->normaliserCle($cle) : $this->apiKey();
        $modele = ($modele !== null && $modele !== '') ? $modele : $this->model();

        if ($cle === '') {
            return ['ok' => false, 'message' => 'Aucune cle API 1min.ai renseignee.', 'modeles' => []];
        }

        $response = HttpClient::get('https://api.1min.ai/openai/v1/models', [
            'Authorization' => 'Bearer ' . $cle,
        ], $this->timeout());

        if ($response['error'] !== null || $response['status'] < 200 || $response['status'] >= 300) {
            return ['ok' => false, 'message' => $this->erreurLisible($response), 'modeles' => []];
        }

        $ids = [];
        foreach ((array) ($response['json']['data'] ?? []) as $m) {
            if (is_array($m) && isset($m['id'])) {
                $ids[] = (string) $m['id'];
            }
        }
        sort($ids);

        if ($ids !== [] && !in_array($modele, $ids, true)) {
            return [
                'ok'      => false,
                'message' => "Cle API valide, mais le modele « {$modele} » n'est pas accessible. Choisissez un identifiant de la liste.",
                'modeles' => $ids,
            ];
        }

        $message = 'Cle API valide';
        if ($ids !== []) {
            $message .= ' (' . count($ids) . ' modeles accessibles)';
        }
        $message .= " ; modele « {$modele} » disponible.";

        return ['ok' => true, 'message' => $message, 'modeles' => $ids];
    }

    /**
     * Construit un message d'erreur lisible a partir de la reponse HTTP de 1min.ai
     * (l'API renvoie { "error": { "message": ..., "code": ... } }).
     *
     * @param array{status:int,body:string,error:?string,json:mixed} $response
     */
    private function erreurLisible(array $response): string
    {
        $status  = (int) ($response['status'] ?? 0);
        $message = '';

        $json = $response['json'] ?? null;
        if (is_array($json)) {
            $message = (string) ($json['error']['message'] ?? $json['message'] ?? '');
            if ($message === '' && isset($json['error']) && is_string($json['error'])) {
                $message = $json['error'];
            }
        }
        if ($message === '' && is_string($response['body'] ?? null)) {
            $message = trim((string) $response['body']);
        }
        if ($message === '' && !empty($response['error'])) {
            $message = (string) $response['error'];
        }

        $message = trim((string) preg_replace('/\s+/', ' ', strip_tags($message)));
        if (mb_strlen($message) > 240) {
            $message = mb_substr($message, 0, 240) . '…';
        }

        if ($status > 0) {
            $prefixe = 'HTTP ' . $status . ' — ';
        } elseif (!empty($response['error'])) {
            $prefixe = 'Connexion — ';
        } else {
            $prefixe = '';
        }

        return $prefixe . ($message !== '' ? $message : 'reponse vide du service');
    }

    /**
     * Journalise l'echec (audit + fichier storage/logs/ia.log) sans jamais faire
     * echouer l'action metier.
     *
     * @param array{status:int,body:string,error:?string,json:mixed} $response
     */
    private function journaliserErreur(array $response, string $detail): void
    {
        try {
            Audit::log('ia_analyse_erreur', 'client', null, [
                'modele' => $this->model(),
                'status' => (int) ($response['status'] ?? 0),
                'detail' => mb_substr($detail, 0, 300),
            ]);
        } catch (\Throwable) {
            // ignore
        }

        try {
            $dir = APP_ROOT . '/storage/logs';
            if (is_dir($dir)) {
                $ligne = [
                    'date'   => date('c'),
                    'modele' => $this->model(),
                    'status' => (int) ($response['status'] ?? 0),
                    'error'  => $response['error'] ?? null,
                    'detail' => $detail,
                    'body'   => mb_substr((string) ($response['body'] ?? ''), 0, 2000),
                ];
                @file_put_contents(
                    $dir . '/ia.log',
                    json_encode($ligne, JSON_UNESCAPED_UNICODE) . "\n",
                    FILE_APPEND
                );
            }
        } catch (\Throwable) {
            // ignore
        }
    }
}
