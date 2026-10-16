<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Workflow par statut : transitions autorisees depuis chaque statut et
 * pre-requis a remplir avant d'entrer dans un statut.
 *
 * Par defaut (aucune regle configuree) tout est permis : le comportement
 * historique de l'application est conserve.
 */
class WorkflowService
{
    /** Regles de pre-requis disponibles (cle => libelle affiche a l'utilisateur). */
    public const REGLES = [
        'relance'         => 'au moins une relance enregistrée',
        'mise_en_demeure' => 'une mise en demeure (relance ou courrier généré)',
        'courrier_envoye' => 'un courrier envoyé (date d\'envoi renseignée)',
        'ar_recu'         => 'l\'accusé de réception d\'un courrier',
        'document'        => 'au moins une pièce jointe',
        'echeancier'      => 'un échéancier de paiement',
        'paiement'        => 'au moins un paiement enregistré',
        'responsable'     => 'un gestionnaire affecté',
    ];

    /**
     * @return string[] messages d'erreur ; vide si le passage est autorise
     */
    public function verifier(array $litige, int $versStatutId): array
    {
        $erreurs = [];
        $deId    = (int) $litige['statut_id'];

        if ($versStatutId === $deId) {
            return [];
        }

        $autorises = $this->transitionsDepuis($deId);
        if ($autorises !== null && !in_array($versStatutId, $autorises, true)) {
            $erreurs[] = 'Ce changement de statut n\'est pas autorisé depuis le statut actuel.';
        }

        foreach ($this->prerequis($versStatutId) as $regle) {
            if (!$this->regleRespectee($regle, $litige)) {
                $erreurs[] = 'Pré-requis manquant : ' . (self::REGLES[$regle] ?? $regle) . '.';
            }
        }

        return $erreurs;
    }

    /** @return int[]|null identifiants des statuts autorises, null si aucune restriction */
    public function transitionsDepuis(int $statutId): ?array
    {
        $lignes = Database::fetchAll(
            'SELECT vers_statut_id FROM statut_transitions WHERE de_statut_id = :id',
            ['id' => $statutId]
        );
        if ($lignes === []) {
            return null;
        }
        return array_map(static fn (array $l): int => (int) $l['vers_statut_id'], $lignes);
    }

    /** @return string[] */
    public function prerequis(int $statutId): array
    {
        $lignes = Database::fetchAll('SELECT regle FROM statut_prerequis WHERE statut_id = :id', ['id' => $statutId]);
        return array_values(array_filter(
            array_map(static fn (array $l): string => (string) $l['regle'], $lignes),
            static fn (string $r): bool => isset(self::REGLES[$r])
        ));
    }

    /**
     * Enregistre la configuration d'un statut.
     *
     * @param int[]    $transitions identifiants des statuts de destination (vide = tout autoriser)
     * @param string[] $regles
     */
    public function configurer(int $statutId, array $transitions, array $regles): void
    {
        Database::begin();
        try {
            Database::delete('statut_transitions', 'de_statut_id = :id', ['id' => $statutId]);
            foreach (array_unique(array_map('intval', $transitions)) as $vers) {
                if ($vers > 0 && $vers !== $statutId) {
                    Database::insert('statut_transitions', ['de_statut_id' => $statutId, 'vers_statut_id' => $vers]);
                }
            }
            Database::delete('statut_prerequis', 'statut_id = :id', ['id' => $statutId]);
            foreach (array_unique($regles) as $regle) {
                if (isset(self::REGLES[$regle])) {
                    Database::insert('statut_prerequis', ['statut_id' => $statutId, 'regle' => $regle]);
                }
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    private function regleRespectee(string $regle, array $litige): bool
    {
        $id = (int) $litige['id'];
        return match ($regle) {
            'relance'         => Database::exists('relances', 'litige_id = :id', ['id' => $id]),
            'mise_en_demeure' => Database::exists('relances', "litige_id = :id AND type_action = 'mise_en_demeure'", ['id' => $id])
                || Database::exists('courriers', "litige_id = :id AND type = 'mise_en_demeure'", ['id' => $id]),
            'courrier_envoye' => Database::exists('courriers', 'litige_id = :id AND date_envoi IS NOT NULL', ['id' => $id]),
            'ar_recu'         => Database::exists('courriers', "litige_id = :id AND statut_ar = 'recu'", ['id' => $id]),
            'document'        => Database::exists('documents', 'litige_id = :id', ['id' => $id]),
            'echeancier'      => Database::exists('echeances', 'litige_id = :id', ['id' => $id]),
            'paiement'        => Database::exists('paiements', 'litige_id = :id', ['id' => $id]),
            'responsable'     => (int) ($litige['responsable_id'] ?? 0) > 0,
            default           => true,
        };
    }
}
