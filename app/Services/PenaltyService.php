<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Settings;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\Paiement;
use App\Services\Calcul\PenaltyCalculator;

/**
 * Penalites de retard : interets au taux annuel sur le capital restant du
 * + indemnite forfaitaire de recouvrement, recalcules chaque jour par le cron
 * pour les litiges dont le calcul automatique est active.
 */
class PenaltyService
{
    /**
     * Calcule (sans enregistrer) les penalites d'un litige a une date donnee.
     *
     * @return array{interets:float, indemnite:float, total:float, jours:int, capital_restant:float, depuis:string, taux:float}
     */
    public function calculer(array $litige, ?string $aujourdhui = null): array
    {
        $aujourdhui ??= date('Y-m-d');
        $depuis = (string) ($litige['penalites_depuis'] ?: $litige['date_echeance'] ?: $litige['date_facture'] ?: $litige['date_ouverture']);
        $taux   = $litige['taux_penalite'] !== null && $litige['taux_penalite'] !== ''
            ? (float) $litige['taux_penalite']
            : (float) Settings::get('penalite_taux_annuel', 12.0);

        $resultat = PenaltyCalculator::calculer(
            (float) $litige['montant_principal'],
            $depuis,
            $aujourdhui,
            (new Paiement())->chronologie((int) $litige['id']),
            $taux,
            (int) ($litige['indemnite_forfaitaire'] ?? 0) === 1,
            (float) Settings::get('penalite_indemnite_forfaitaire', 40.0)
        );

        return $resultat + ['depuis' => substr($depuis, 0, 10), 'taux' => $taux];
    }

    /** Recalcule et enregistre les penalites si le calcul automatique est actif. */
    public function recalculerSiAuto(int $litigeId, ?int $userId = null): bool
    {
        $litige = (new Litige())->find($litigeId);
        if (!$litige || (int) $litige['penalites_auto'] !== 1) {
            return false;
        }
        return $this->appliquer($litige, $userId);
    }

    /** Applique le calcul (meme si le mode auto est desactive : action explicite). */
    public function appliquer(array $litige, ?int $userId = null): bool
    {
        $calcul   = $this->calculer($litige);
        $ancien   = (float) $litige['montant_penalites'];
        $nouveau  = $calcul['total'];

        Database::update('litiges', [
            'montant_penalites'      => $nouveau,
            'penalites_calculees_le' => date('Y-m-d'),
        ], 'id = :id', ['id' => (int) $litige['id']]);

        if (abs($nouveau - $ancien) < 0.005) {
            return false;
        }

        (new LitigeHistorique())->log(
            (int) $litige['id'],
            $userId,
            'penalites',
            'Pénalités',
            number_format($ancien, 2, '.', ''),
            number_format($nouveau, 2, '.', ''),
            sprintf('Recalcul : %s € d\'intérêts à %s %% depuis le %s + indemnité %s €',
                number_format($calcul['interets'], 2, ',', ' '),
                number_format($calcul['taux'], 2, ',', ' '),
                date_fr($calcul['depuis']),
                number_format($calcul['indemnite'], 2, ',', ' '))
        );
        return true;
    }

    /**
     * Recalcul quotidien (cron). @return array{traites:int, modifies:int}
     */
    public function recalculerTous(bool $simulation = false): array
    {
        $litiges = Database::fetchAll(
            'SELECT l.* FROM litiges l JOIN litige_statuts s ON s.id = l.statut_id
             WHERE l.penalites_auto = 1 AND s.cloturant = 0'
        );

        $stats = ['traites' => 0, 'modifies' => 0];
        foreach ($litiges as $litige) {
            $stats['traites']++;
            if ($simulation) {
                $calcul = $this->calculer($litige);
                if (abs($calcul['total'] - (float) $litige['montant_penalites']) >= 0.005) {
                    $stats['modifies']++;
                }
                continue;
            }
            if ($this->appliquer($litige, null)) {
                $stats['modifies']++;
            }
        }

        if (!$simulation && $stats['modifies'] > 0) {
            Audit::log('penalites_recalcul_auto', 'litige', null, $stats);
        }
        return $stats;
    }
}
