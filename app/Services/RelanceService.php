<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Enums\CanalAction;
use App\Enums\TypeAction;
use App\Exceptions\ValidationException;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\Relance;

/**
 * Enregistrement d'une relance / d'un contact : mise a jour du suivi du litige
 * (derniere relance, prochaine relance), historique, audit et webhook.
 */
class RelanceService
{
    /**
     * @param array<string,mixed> $data type_action, canal, date_action, objet, contenu, resultat,
     *                                  montant_promis, date_promesse, prochaine_action_at, contact_id
     * @throws ValidationException
     */
    public function enregistrer(int $litigeId, array $data, ?int $userId): int
    {
        $litiges = new Litige();
        $litige  = $litiges->find($litigeId);
        if (!$litige) {
            throw new ValidationException(['litige' => 'Litige introuvable.']);
        }

        $type  = TypeAction::tryFrom((string) ($data['type_action'] ?? 'relance')) ?? TypeAction::Relance;
        $canal = CanalAction::tryFrom((string) ($data['canal'] ?? 'telephone')) ?? CanalAction::Telephone;

        $dateAction   = $this->dateHeure((string) ($data['date_action'] ?? '')) ?: date('Y-m-d H:i:s');
        $datePromesse = $this->date((string) ($data['date_promesse'] ?? ''));
        $prochaine    = $this->date((string) ($data['prochaine_action_at'] ?? ''));

        $contenu = trim((string) ($data['contenu'] ?? ''));
        $objet   = trim((string) ($data['objet'] ?? ''));
        if ($contenu === '' && $objet === '') {
            throw new ValidationException(['objet' => "Merci de renseigner l'objet ou le détail de la relance."]);
        }

        $montantPromis = $data['montant_promis'] ?? null;
        if ($montantPromis === '' || $montantPromis === null) {
            $montantPromis = null;
        } else {
            $propre = str_replace([' ', ','], ['', '.'], (string) $montantPromis);
            $montantPromis = is_numeric($propre) ? round((float) $propre, 2) : null;
        }

        $id = (new Relance())->create([
            'litige_id'           => $litigeId,
            'contact_id'          => !empty($data['contact_id']) ? (int) $data['contact_id'] : null,
            'type_action'         => $type->value,
            'canal'               => $canal->value,
            'date_action'         => $dateAction,
            'objet'               => $objet !== '' ? $objet : $type->label(),
            'contenu'             => $contenu,
            'resultat'            => trim((string) ($data['resultat'] ?? '')) ?: null,
            'montant_promis'      => $montantPromis,
            'date_promesse'       => $datePromesse,
            'prochaine_action_at' => $prochaine,
            'created_by'          => $userId,
        ]);

        // Mise a jour du suivi du litige.
        $delai = max(1, (int) ($litige['delai_relance_jours'] ?? 15));
        $prochaineRelance = $prochaine ?: date('Y-m-d', strtotime($dateAction . " +{$delai} days"));

        // Une promesse de paiement non depassee repousse la relance a la date promise.
        if ($datePromesse && strtotime($datePromesse) >= strtotime('today')) {
            $prochaineRelance = $datePromesse;
        }

        $litiges->update($litigeId, [
            'derniere_relance_at'  => $dateAction,
            'prochaine_relance_at' => $prochaineRelance,
        ]);

        (new LitigeHistorique())->log($litigeId, $userId, 'relance', 'Relance', null, $type->label(), trim($objet . ' — ' . $contenu));
        Audit::log('relance_ajout', 'litige', $litigeId, ['type' => $type->value, 'canal' => $canal->value]);

        WebhookService::emettre('relance.ajoutee', [
            'litige_id' => $litigeId,
            'reference' => $litige['reference'],
            'type'      => $type->value,
            'canal'     => $canal->value,
            'objet'     => $objet !== '' ? $objet : $type->label(),
            'resultat'  => $data['resultat'] ?? null,
            'promesse'  => $datePromesse ? ['date' => $datePromesse, 'montant' => $montantPromis] : null,
        ]);

        return $id;
    }

    private function date(string $valeur): ?string
    {
        $valeur = trim($valeur);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) ? $valeur : null;
    }

    private function dateHeure(string $valeur): ?string
    {
        $valeur = trim(str_replace('T', ' ', $valeur));
        if ($valeur === '') {
            return null;
        }
        $ts = strtotime($valeur);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
