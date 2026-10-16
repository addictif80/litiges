<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Exceptions\ValidationException;
use App\Models\Echeance;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Services\Calcul\EcheancierCalculator;

/**
 * Echeanciers de paiement : creation, imputation automatique des paiements
 * recus apres la creation du plan, statuts (payee / partielle / en retard).
 */
class EcheanceService
{
    /**
     * @param array{nombre:int, premiere_date:string, periodicite:string, montant_total?:float|string, premier_montant?:float|string|null} $params
     * @return int nombre d'echeances creees
     */
    public function creerPlan(int $litigeId, array $params, ?int $userId): int
    {
        $litige = (new Litige())->findFull($litigeId);
        if (!$litige) {
            throw new ValidationException(['litige' => 'Litige introuvable.']);
        }

        $erreurs = [];
        $nombre  = (int) ($params['nombre'] ?? 0);
        if ($nombre < 1 || $nombre > 120) {
            $erreurs['nombre'] = 'Le nombre d\'échéances doit être compris entre 1 et 120.';
        }
        $premiere = (string) ($params['premiere_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $premiere) || strtotime($premiere) === false) {
            $erreurs['premiere_date'] = 'La date de première échéance est invalide.';
        }
        $periodicite = (string) ($params['periodicite'] ?? 'mensuelle');
        if (!isset(EcheancierCalculator::PERIODICITES[$periodicite])) {
            $erreurs['periodicite'] = 'Périodicité invalide.';
        }
        $total = $this->nombre($params['montant_total'] ?? null) ?? (float) $litige['encours'];
        if ($total <= 0) {
            $erreurs['montant_total'] = 'Le montant à échelonner doit être positif.';
        }
        $premierMontant = $this->nombre($params['premier_montant'] ?? null);
        if ($premierMontant !== null && ($premierMontant <= 0 || $premierMontant >= $total)) {
            $erreurs['premier_montant'] = 'Le premier versement doit être inférieur au montant total.';
        }
        if ($erreurs !== []) {
            throw new ValidationException($erreurs);
        }

        $plan = EcheancierCalculator::generer($total, $nombre, $premiere, $periodicite, $premierMontant);

        Database::begin();
        try {
            (new Echeance())->supprimerPlan($litigeId);
            foreach ($plan as $ligne) {
                Database::insert('echeances', [
                    'litige_id'     => $litigeId,
                    'numero'        => $ligne['numero'],
                    'date_echeance' => $ligne['date'],
                    'montant'       => $ligne['montant'],
                    'montant_regle' => 0,
                    'created_by'    => $userId,
                ]);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $this->majProchaineRelance($litigeId);

        (new LitigeHistorique())->log(
            $litigeId,
            $userId,
            'echeancier',
            'Échéancier',
            null,
            count($plan) . ' échéance(s)',
            sprintf('Plan de %s € en %d échéance(s) (%s), à partir du %s',
                number_format($total, 2, ',', ' '),
                count($plan),
                strtolower(EcheancierCalculator::PERIODICITES[$periodicite]),
                date_fr($premiere))
        );
        Audit::log('echeancier_creation', 'litige', $litigeId, ['nombre' => count($plan), 'total' => $total]);

        return count($plan);
    }

    public function supprimerPlan(int $litigeId, ?int $userId): void
    {
        (new Echeance())->supprimerPlan($litigeId);
        (new LitigeHistorique())->log($litigeId, $userId, 'echeancier', 'Échéancier', null, null, 'Échéancier supprimé');
        Audit::log('echeancier_suppression', 'litige', $litigeId);
    }

    /**
     * Impute sur les echeances les paiements enregistres depuis la creation du plan.
     * (Ce qui a ete paye avant le plan a deja ete deduit du montant echelonne.)
     */
    public function reallouer(int $litigeId): void
    {
        $echeances = (new Echeance())->forLitige($litigeId);
        if ($echeances === []) {
            return;
        }

        $depuis = (string) Database::value('SELECT MIN(created_at) FROM echeances WHERE litige_id = :id', ['id' => $litigeId]);
        $paye = (float) Database::value(
            'SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE litige_id = :id AND created_at >= :depuis',
            ['id' => $litigeId, 'depuis' => $depuis],
            0
        );

        $regles = EcheancierCalculator::repartir($echeances, $paye);
        foreach ($echeances as $i => $echeance) {
            if (abs($regles[$i] - (float) $echeance['montant_regle']) >= 0.005) {
                Database::update('echeances', ['montant_regle' => $regles[$i]], 'id = :id', ['id' => (int) $echeance['id']]);
            }
        }

        $this->majProchaineRelance($litigeId);
    }

    /** @return array<int,array<string,mixed>> echeances avec la cle "statut" calculee */
    public function avecStatuts(int $litigeId): array
    {
        $aujourdhui = date('Y-m-d');
        $liste = (new Echeance())->forLitige($litigeId);
        foreach ($liste as &$e) {
            $e['statut'] = EcheancierCalculator::statut((float) $e['montant'], (float) $e['montant_regle'], (string) $e['date_echeance'], $aujourdhui);
        }
        unset($e);
        return $liste;
    }

    /** La prochaine relance devient la premiere echeance impayee du plan. */
    public function majProchaineRelance(int $litigeId): void
    {
        $litige = (new Litige())->findFull($litigeId);
        if (!$litige || (int) $litige['statut_cloturant'] === 1) {
            return;
        }
        $date = Database::value(
            'SELECT MIN(date_echeance) FROM echeances WHERE litige_id = :id AND montant_regle + 0.005 < montant',
            ['id' => $litigeId]
        );
        if ($date) {
            Database::update('litiges', ['prochaine_relance_at' => $date], 'id = :id', ['id' => $litigeId]);
        }
    }

    private function nombre(mixed $valeur): ?float
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        $propre = str_replace([' ', ','], ['', '.'], (string) $valeur);
        return is_numeric($propre) ? (float) $propre : null;
    }
}
