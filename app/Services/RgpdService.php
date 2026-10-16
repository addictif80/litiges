<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Settings;

/**
 * Conformite RGPD : export des donnees d'un contact, droit a l'effacement
 * (anonymisation) et purge planifiee selon la duree de conservation choisie.
 */
class RgpdService
{
    public const NOM_ANONYME = 'Contact anonymisé';

    /**
     * Toutes les donnees detenues sur un contact (droit d'acces / portabilite).
     *
     * @return array<string,mixed>|null
     */
    public function exporterContact(int $contactId): ?array
    {
        $contact = Database::fetch('SELECT * FROM contacts WHERE id = :id', ['id' => $contactId]);
        if (!$contact) {
            return null;
        }

        $client = Database::fetch(
            'SELECT id, raison_sociale, siren, adresse, code_postal, ville FROM clients WHERE id = :id',
            ['id' => (int) $contact['client_id']]
        );
        $relances = Database::fetchAll(
            'SELECT r.date_action, r.type_action, r.canal, r.objet, r.contenu, r.resultat, l.reference AS litige
             FROM relances r JOIN litiges l ON l.id = r.litige_id
             WHERE r.contact_id = :id ORDER BY r.date_action ASC',
            ['id' => $contactId]
        );

        return [
            'genere_le' => date('c'),
            'contact'   => array_intersect_key($contact, array_flip(['id', 'civilite', 'nom', 'prenom', 'fonction', 'email', 'telephone', 'mobile', 'type', 'notes', 'created_at'])),
            'entreprise' => $client,
            'echanges'  => $relances,
        ];
    }

    /** Droit a l'effacement : supprime les donnees personnelles en conservant la trace du dossier. */
    public function anonymiserContact(int $contactId, ?int $userId = null): bool
    {
        $contact = Database::fetch('SELECT id, client_id, nom, principal FROM contacts WHERE id = :id', ['id' => $contactId]);
        if (!$contact || $contact['nom'] === self::NOM_ANONYME) {
            return false;
        }

        Database::update('contacts', [
            'civilite'  => null,
            'nom'       => self::NOM_ANONYME,
            'prenom'    => null,
            'fonction'  => null,
            'email'     => null,
            'telephone' => null,
            'mobile'    => null,
            'notes'     => null,
            'principal' => 0,
        ], 'id = :id', ['id' => $contactId]);

        // Les comptes rendus d'echanges peuvent citer la personne : on les neutralise.
        Database::run(
            "UPDATE relances SET contenu = '[contenu supprimé - RGPD]' WHERE contact_id = :id AND contenu IS NOT NULL AND contenu <> ''",
            ['id' => $contactId]
        );

        Audit::log('rgpd_contact_anonymise', 'contact', $contactId, ['par' => $userId]);
        return true;
    }

    /**
     * Purge planifiee selon les durees de conservation.
     *
     * @return array{contacts_anonymises:int, litiges_supprimes:int, audit_purge:int}
     */
    public function purger(bool $simulation = false): array
    {
        $stats = ['contacts_anonymises' => 0, 'litiges_supprimes' => 0, 'audit_purge' => 0];

        $mois = (int) Settings::get('rgpd_retention_mois', 0);
        if ($mois > 0) {
            $limite = date('Y-m-d', strtotime("-{$mois} months"));

            // Clients dont tous les litiges sont clos depuis plus longtemps que la retention.
            if (Settings::bool('rgpd_anonymiser_contacts', true)) {
                $contacts = Database::fetchAll(
                    "SELECT ct.id FROM contacts ct
                     WHERE ct.nom <> :anon
                       AND EXISTS (SELECT 1 FROM litiges l WHERE l.client_id = ct.client_id)
                       AND NOT EXISTS (
                            SELECT 1 FROM litiges l JOIN litige_statuts s ON s.id = l.statut_id
                            WHERE l.client_id = ct.client_id AND (s.cloturant = 0 OR l.date_cloture IS NULL OR l.date_cloture >= :limite))",
                    ['anon' => self::NOM_ANONYME, 'limite' => $limite]
                );
                foreach ($contacts as $c) {
                    if ($simulation || $this->anonymiserContact((int) $c['id'], null)) {
                        $stats['contacts_anonymises']++;
                    }
                }
            }

            if (Settings::bool('rgpd_supprimer_litiges', false)) {
                $litiges = Database::fetchAll(
                    'SELECT l.id FROM litiges l JOIN litige_statuts s ON s.id = l.statut_id
                     WHERE s.cloturant = 1 AND l.date_cloture IS NOT NULL AND l.date_cloture < :limite',
                    ['limite' => $limite]
                );
                $service = new LitigeService();
                foreach ($litiges as $l) {
                    if (!$simulation) {
                        $service->supprimer((int) $l['id']);
                    }
                    $stats['litiges_supprimes']++;
                }
            }
        }

        $moisAudit = (int) Settings::get('audit_retention_mois', 0);
        if ($moisAudit > 0) {
            $limiteAudit = date('Y-m-d H:i:s', strtotime("-{$moisAudit} months"));
            if ($simulation) {
                $stats['audit_purge'] = (int) Database::value('SELECT COUNT(*) FROM audit_log WHERE created_at < :l', ['l' => $limiteAudit], 0);
            } else {
                $stats['audit_purge'] = Database::run('DELETE FROM audit_log WHERE created_at < :l', ['l' => $limiteAudit])->rowCount();
            }
        }

        if (!$simulation && array_sum($stats) > 0) {
            Audit::log('rgpd_purge', 'rgpd', null, $stats);
        }
        return $stats;
    }
}
