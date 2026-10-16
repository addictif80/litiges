<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Enums\ModePaiement;
use App\Exceptions\ValidationException;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\Paiement;

/**
 * Journal des paiements d'un litige. Le montant "recupere" du litige est
 * toujours la somme des paiements : il n'est plus saisi a la main.
 */
class PaiementService
{
    /**
     * @param array{date_paiement?:string, montant?:mixed, mode?:string, reference?:?string, commentaire?:?string} $data
     * @return int identifiant du paiement (0 si ignore car deja importe)
     */
    public function enregistrer(int $litigeId, array $data, ?int $userId, string $source = 'manuel', ?string $sourceRef = null): int
    {
        $litige = (new Litige())->findFull($litigeId);
        if (!$litige) {
            throw new ValidationException(['litige' => 'Litige introuvable.']);
        }

        $erreurs = [];
        $montant = $this->nombre($data['montant'] ?? null);
        if ($montant === null || $montant <= 0) {
            $erreurs['montant'] = 'Le montant du paiement doit être un nombre positif.';
        }
        $date = (string) ($data['date_paiement'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            $erreurs['date_paiement'] = 'La date du paiement est invalide.';
        } elseif ($date > date('Y-m-d')) {
            $erreurs['date_paiement'] = 'La date du paiement ne peut pas être dans le futur.';
        }
        if ($erreurs !== []) {
            throw new ValidationException($erreurs);
        }

        $paiements = new Paiement();
        if ($sourceRef !== null && $paiements->existeSource($litigeId, $source, $sourceRef)) {
            return 0; // deja pris en compte (import rejoue)
        }

        $mode = ModePaiement::tryFrom((string) ($data['mode'] ?? 'virement'))?->value ?? ModePaiement::Autre->value;

        Database::begin();
        try {
            $id = $paiements->create([
                'litige_id'     => $litigeId,
                'date_paiement' => $date,
                'montant'       => round($montant, 2),
                'mode'          => $mode,
                'reference'     => $this->texte($data['reference'] ?? null, 120),
                'commentaire'   => $this->texte($data['commentaire'] ?? null, 255),
                'source'        => $source,
                'source_ref'    => $sourceRef,
                'created_by'    => $userId,
            ]);
            $this->recalculer($litigeId, $userId);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        (new LitigeHistorique())->log(
            $litigeId,
            $userId,
            'paiement',
            'Paiement',
            null,
            number_format($montant, 2, '.', ''),
            sprintf('%s du %s%s', ModePaiement::tryFrom($mode)?->label() ?? $mode, date_fr($date),
                !empty($data['reference']) ? ' (réf. ' . $data['reference'] . ')' : '')
        );
        Audit::log('paiement_ajout', 'litige', $litigeId, ['paiement_id' => $id, 'montant' => $montant, 'source' => $source]);

        $apres = (new Litige())->findFull($litigeId);
        WebhookService::emettre('paiement.enregistre', [
            'litige_id' => $litigeId,
            'reference' => $litige['reference'],
            'montant'   => round($montant, 2),
            'date'      => $date,
            'mode'      => $mode,
            'encours'   => (float) ($apres['encours'] ?? 0),
        ]);

        if ($apres && (float) $apres['encours'] <= 0.005 && (int) $apres['statut_cloturant'] === 0) {
            (new NotificationService())->litigeSolde($apres);
        }

        return $id;
    }

    public function supprimer(int $paiementId, ?int $userId): void
    {
        $paiements = new Paiement();
        $paiement  = $paiements->find($paiementId);
        if (!$paiement) {
            throw new ValidationException(['paiement' => 'Paiement introuvable.']);
        }

        Database::begin();
        try {
            $paiements->delete($paiementId);
            $this->recalculer((int) $paiement['litige_id'], $userId);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        (new LitigeHistorique())->log(
            (int) $paiement['litige_id'],
            $userId,
            'paiement',
            'Paiement supprimé',
            number_format((float) $paiement['montant'], 2, '.', ''),
            null,
            'Paiement du ' . date_fr((string) $paiement['date_paiement'])
        );
        Audit::log('paiement_suppression', 'litige', (int) $paiement['litige_id'], ['paiement_id' => $paiementId]);
    }

    /** Recalcule le montant recupere, l'imputation sur l'echeancier et les penalites automatiques. */
    public function recalculer(int $litigeId, ?int $userId = null): void
    {
        $total = (new Paiement())->total($litigeId);
        Database::update('litiges', ['montant_recupere' => $total], 'id = :id', ['id' => $litigeId]);

        (new EcheanceService())->reallouer($litigeId);
        (new PenaltyService())->recalculerSiAuto($litigeId, $userId);
    }

    private function nombre(mixed $valeur): ?float
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        $propre = str_replace([' ', ' ', ','], ['', '', '.'], (string) $valeur);
        return is_numeric($propre) ? (float) $propre : null;
    }

    private function texte(mixed $valeur, int $max): ?string
    {
        $texte = trim((string) $valeur);
        return $texte === '' ? null : mb_substr($texte, 0, $max);
    }
}
