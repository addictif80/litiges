<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Settings;
use App\Services\RgpdService;

/** Registre des traitements et durees de conservation (administrateurs). */
class RgpdController extends Controller
{
    public function index(): void
    {
        $this->view('rgpd/index', [
            'titre'     => 'RGPD — registre et conservation',
            'registre'  => Database::fetchAll('SELECT * FROM rgpd_registre ORDER BY ordre ASC, id ASC'),
            'simulation' => $_SESSION['_rgpd_simulation'] ?? null,
        ]);
        unset($_SESSION['_rgpd_simulation']);
    }

    public function parametres(): void
    {
        $nombre = fn (string $cle): string => (string) max(0, min(600, $this->int($cle)));
        Settings::setMany([
            'rgpd_retention_mois'      => $nombre('rgpd_retention_mois'),
            'rgpd_anonymiser_contacts' => $this->post('rgpd_anonymiser_contacts') !== null ? '1' : '0',
            'rgpd_supprimer_litiges'   => $this->post('rgpd_supprimer_litiges') !== null ? '1' : '0',
            'audit_retention_mois'     => $nombre('audit_retention_mois'),
        ]);
        Audit::log('rgpd_parametres', 'settings');
        flash('success', 'Durées de conservation enregistrées.');
        $this->redirect('/rgpd');
    }

    public function simuler(): void
    {
        $_SESSION['_rgpd_simulation'] = (new RgpdService())->purger(true);
        $this->redirect('/rgpd');
    }

    public function purger(): void
    {
        $stats = (new RgpdService())->purger(false);
        flash('success', sprintf(
            'Purge effectuée : %d contact(s) anonymisé(s), %d litige(s) supprimé(s), %d ligne(s) d\'audit purgée(s).',
            $stats['contacts_anonymises'], $stats['litiges_supprimes'], $stats['audit_purge']
        ));
        $this->redirect('/rgpd');
    }

    public function enregistrer(): void
    {
        $id = $this->int('id');
        $data = [
            'traitement'         => mb_substr((string) $this->post('traitement', ''), 0, 190),
            'finalite'           => (string) $this->post('finalite', ''),
            'base_legale'        => mb_substr((string) $this->post('base_legale', ''), 0, 190),
            'categories_donnees' => (string) $this->post('categories_donnees', ''),
            'personnes'          => mb_substr((string) $this->post('personnes', ''), 0, 190),
            'destinataires'      => mb_substr((string) $this->post('destinataires', ''), 0, 255) ?: null,
            'duree_conservation' => mb_substr((string) $this->post('duree_conservation', ''), 0, 190),
            'mesures_securite'   => (string) $this->post('mesures_securite', '') ?: null,
            'ordre'              => $this->int('ordre', 100),
        ];
        if ($data['traitement'] === '' || $data['finalite'] === '' || $data['base_legale'] === '') {
            flash('error', 'Traitement, finalité et base légale sont obligatoires.');
            $this->redirect('/rgpd');
        }

        if ($id > 0) {
            Database::update('rgpd_registre', $data, 'id = :id', ['id' => $id]);
        } else {
            Database::insert('rgpd_registre', $data);
        }
        Audit::log('rgpd_registre_modifie', 'rgpd', $id ?: null);
        flash('success', 'Registre mis à jour.');
        $this->redirect('/rgpd');
    }

    public function supprimer(string $id): void
    {
        Database::delete('rgpd_registre', 'id = :id', ['id' => (int) $id]);
        Audit::log('rgpd_registre_supprime', 'rgpd', (int) $id);
        flash('success', 'Traitement supprimé du registre.');
        $this->redirect('/rgpd');
    }
}
