<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Settings;
use App\Enums\CanalAction;
use App\Enums\TypeAction;
use App\Exceptions\ValidationException;
use App\Models\Contact;
use App\Models\Courrier;
use App\Models\Litige;
use App\Models\LitigeHistorique;
use App\Models\ModeleCourrier;
use App\Models\Paiement;

/**
 * Courriers types (relance, mise en demeure, echeancier, declaration de creance) :
 * fusion des variables, archivage dans les documents du litige, suivi d'envoi
 * et d'accuse de reception.
 */
class CourrierService
{
    /** Variables disponibles dans les modeles (cle => description). */
    public const VARIABLES = [
        'societe.nom'              => 'Nom de votre société',
        'societe.adresse'          => 'Adresse de votre société',
        'societe.telephone'        => 'Téléphone',
        'societe.email'            => 'E-mail',
        'societe.iban'             => 'IBAN pour les virements',
        'client.raison_sociale'    => 'Raison sociale du client',
        'client.adresse'           => 'Adresse du client',
        'client.siren'             => 'SIREN du client',
        'client.siret'             => 'SIRET du client',
        'client.procedure'         => 'Procédure collective connue',
        'contact.nom'              => 'Contact principal du client',
        'litige.reference'         => 'Référence du litige',
        'litige.objet'             => 'Objet du litige',
        'litige.numero_facture'    => 'N° de facture',
        'litige.date_facture'      => 'Date de facture',
        'litige.date_echeance'     => "Date d'échéance",
        'litige.numero_contrat'    => 'N° de contrat',
        'litige.materiel'          => 'Matériel / véhicule',
        'litige.montant_principal' => 'Montant principal',
        'litige.montant_penalites' => 'Pénalités et frais',
        'litige.montant_recupere'  => 'Montant déjà réglé',
        'litige.encours'           => 'Solde restant dû',
        'date.jour'                => "Date du jour",
        'delai.jours'              => 'Délai accordé (jours)',
        'auteur.nom'               => 'Nom de l\'utilisateur qui génère le courrier',
        'echeancier.tableau'       => "Tableau de l'échéancier (HTML)",
        'paiements.liste'          => 'Liste des paiements reçus (HTML)',
        'penalites.detail'         => 'Détail du calcul des pénalités',
    ];

    /** Variables dont la valeur est deja du HTML sur (jamais echappee). */
    private const HTML = ['societe.adresse', 'client.adresse', 'echeancier.tableau', 'paiements.liste'];

    /**
     * Remplace les {{variables}} d'un gabarit. Valeurs echappees (sauf variables HTML
     * fabriquees par l'application). Une variable inconnue devient une chaine vide.
     *
     * @param array<string,string> $valeurs cle => valeur (texte brut)
     * @param array<string,string> $html    cle => HTML deja sur
     */
    public static function rendre(string $gabarit, array $valeurs, array $html = []): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            static function (array $m) use ($valeurs, $html): string {
                $cle = strtolower($m[1]);
                if (isset($html[$cle])) {
                    return $html[$cle];
                }
                return htmlspecialchars($valeurs[$cle] ?? '', ENT_QUOTES, 'UTF-8');
            },
            $gabarit
        );
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [valeurs texte, valeurs html] */
    public function variables(array $litige): array
    {
        $client  = Database::fetch('SELECT * FROM clients WHERE id = :id', ['id' => (int) $litige['client_id']]) ?? [];
        $contact = null;
        foreach ((new Contact())->forClient((int) $litige['client_id']) as $c) {
            if ((int) $c['principal'] === 1) {
                $contact = $c;
                break;
            }
        }

        $user = Auth::user();
        $adresseClient = array_filter([
            $client['adresse'] ?? '', $client['complement_adresse'] ?? '',
            trim(($client['code_postal'] ?? '') . ' ' . ($client['ville'] ?? '')),
        ], static fn ($l): bool => trim((string) $l) !== '');

        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $penalites = (new PenaltyService())->calculer($litige);

        $valeurs = [
            'societe.nom'              => (string) Settings::get('societe_nom', ''),
            'societe.telephone'        => (string) Settings::get('societe_telephone', ''),
            'societe.email'            => (string) Settings::get('societe_email', ''),
            'societe.iban'             => (string) Settings::get('societe_iban', '—'),
            'client.raison_sociale'    => (string) ($client['raison_sociale'] ?? ''),
            'client.siren'             => (string) ($client['siren'] ?? ''),
            'client.siret'             => (string) ($client['siret'] ?? ''),
            'client.procedure'         => (string) ($client['procedure_collective'] ?? ''),
            'contact.nom'              => $contact ? trim(($contact['civilite'] ?? '') . ' ' . ($contact['prenom'] ?? '') . ' ' . $contact['nom']) : '',
            'litige.reference'         => (string) $litige['reference'],
            'litige.objet'             => (string) $litige['objet'],
            'litige.numero_facture'    => (string) ($litige['numero_facture'] ?? '—'),
            'litige.date_facture'      => !empty($litige['date_facture']) ? date_fr($litige['date_facture']) : '—',
            'litige.date_echeance'     => !empty($litige['date_echeance']) ? date_fr($litige['date_echeance']) : '—',
            'litige.numero_contrat'    => (string) ($litige['numero_contrat'] ?? '—'),
            'litige.materiel'          => (string) ($litige['materiel'] ?? '—'),
            'litige.montant_principal' => money($litige['montant_principal']),
            'litige.montant_penalites' => money($litige['montant_penalites']),
            'litige.montant_recupere'  => money($litige['montant_recupere']),
            'litige.encours'           => money($litige['encours'] ?? ((float) $litige['montant_principal'] + (float) $litige['montant_penalites'] - (float) $litige['montant_recupere'])),
            'date.jour'                => date('d/m/Y'),
            'delai.jours'              => (string) max(1, (int) Settings::get('courrier_delai_jours', 8)),
            'auteur.nom'               => (string) ($user['full_name'] ?? $user['username'] ?? ''),
            'penalites.detail'         => sprintf(
                'Intérêts de retard au taux annuel de %s %% depuis le %s : %s ; indemnité forfaitaire de recouvrement : %s.',
                number_format($penalites['taux'], 2, ',', ' '),
                date_fr($penalites['depuis']),
                money($penalites['interets']),
                money($penalites['indemnite'])
            ),
        ];

        $html = [
            'societe.adresse'    => nl2br($e((string) Settings::get('societe_adresse', ''))),
            'client.adresse'     => implode('<br>', array_map($e, $adresseClient)),
            'echeancier.tableau' => $this->tableauEcheancier((int) $litige['id']),
            'paiements.liste'    => $this->listePaiements((int) $litige['id']),
        ];

        return [$valeurs, $html];
    }

    /**
     * Genere un courrier a partir d'un modele et l'archive.
     *
     * @return int identifiant du courrier
     * @throws ValidationException
     */
    public function generer(int $litigeId, int $modeleId, ?int $userId, bool $enregistrerRelance = true): int
    {
        $litige = (new Litige())->findFull($litigeId);
        $modele = (new ModeleCourrier())->find($modeleId);
        if (!$litige || !$modele || (int) $modele['actif'] !== 1) {
            throw new ValidationException(['modele' => 'Litige ou modèle introuvable.']);
        }

        [$valeurs, $html] = $this->variables($litige);
        // L'objet est du texte brut : on retire les balises puis les entites ajoutees par la fusion.
        $sujet   = trim(html_entity_decode(strip_tags(self::rendre((string) $modele['sujet'], $valeurs, $html)), ENT_QUOTES, 'UTF-8'));
        $contenu = self::rendre((string) $modele['contenu'], $valeurs, $html);

        $courriers = new Courrier();
        $numero    = $courriers->prochainNumero();
        $page      = self::pageHtml($sujet !== '' ? $sujet : (string) $modele['nom'], $contenu, $numero);

        $document = (new UploadService())->storeContenu(
            $page,
            $numero . '.html',
            'html',
            'text/html',
            $litigeId,
            null,
            'courrier'
        );

        $id = $courriers->create([
            'numero'      => $numero,
            'litige_id'   => $litigeId,
            'modele_id'   => $modeleId,
            'type'        => (string) $modele['type'],
            'sujet'       => mb_substr($sujet, 0, 190),
            'contenu'     => $contenu,
            'document_id' => $document['id'] ?? null,
            'statut_ar'   => 'non_envoye',
            'created_by'  => $userId,
        ]);

        (new LitigeHistorique())->log($litigeId, $userId, 'courrier', 'Courrier', null, $numero, $modele['nom'] . ' généré');
        Audit::log('courrier_genere', 'litige', $litigeId, ['numero' => $numero, 'modele' => $modele['code']]);

        if ($enregistrerRelance) {
            $type = $modele['type'] === 'mise_en_demeure' ? TypeAction::MiseEnDemeure : TypeAction::Courrier;
            (new RelanceService())->enregistrer($litigeId, [
                'type_action' => $type->value,
                'canal'       => CanalAction::Courrier->value,
                'objet'       => $sujet !== '' ? $sujet : $modele['nom'],
                'contenu'     => 'Courrier ' . $numero . ' généré à partir du modèle « ' . $modele['nom'] . ' ».',
            ], $userId);
        }

        return $id;
    }

    /**
     * Met a jour le suivi d'envoi et l'accuse de reception d'un courrier.
     *
     * @param array{mode_envoi?:?string, numero_suivi?:?string, date_envoi?:?string, date_ar?:?string, statut_ar?:?string} $data
     * @throws ValidationException
     */
    public function majSuivi(int $courrierId, array $data, ?int $userId): void
    {
        $courriers = new Courrier();
        $courrier  = $courriers->find($courrierId);
        if (!$courrier) {
            throw new ValidationException(['courrier' => 'Courrier introuvable.']);
        }

        $date = static function (mixed $v): ?string {
            $v = trim((string) $v);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        };

        $mode = (string) ($data['mode_envoi'] ?? '');
        $maj = [
            'mode_envoi'   => isset(Courrier::MODES_ENVOI[$mode]) ? $mode : null,
            'numero_suivi' => ($t = trim((string) ($data['numero_suivi'] ?? ''))) !== '' ? mb_substr($t, 0, 80) : null,
            'date_envoi'   => $date($data['date_envoi'] ?? null),
            'date_ar'      => $date($data['date_ar'] ?? null),
        ];

        $statut = (string) ($data['statut_ar'] ?? '');
        if (!isset(Courrier::STATUTS_AR[$statut])) {
            $statut = (string) $courrier['statut_ar'];
        }
        // Coherence automatique des statuts a partir des dates saisies.
        if ($maj['date_ar'] !== null && $statut !== 'refuse' && $statut !== 'non_reclame') {
            $statut = 'recu';
        } elseif ($maj['date_envoi'] !== null && $statut === 'non_envoye') {
            $statut = 'en_attente';
        }
        $maj['statut_ar'] = $statut;

        $courriers->update($courrierId, $maj);
        Audit::log('courrier_suivi', 'litige', (int) $courrier['litige_id'], ['numero' => $courrier['numero'], 'statut_ar' => $statut]);

        if ($statut !== $courrier['statut_ar']) {
            (new LitigeHistorique())->log(
                (int) $courrier['litige_id'],
                $userId,
                'courrier',
                'Accusé de réception',
                Courrier::STATUTS_AR[$courrier['statut_ar']] ?? $courrier['statut_ar'],
                Courrier::STATUTS_AR[$statut],
                $courrier['numero']
            );
        }

        // Accuse recu sur une mise en demeure : le delai accorde commence a courir.
        if ($statut === 'recu' && $courrier['statut_ar'] !== 'recu' && $courrier['type'] === 'mise_en_demeure' && $maj['date_ar']) {
            $delai = max(1, (int) Settings::get('courrier_delai_jours', 8));
            $limite = date('Y-m-d', strtotime($maj['date_ar'] . " +{$delai} days"));
            (new Litige())->update((int) $courrier['litige_id'], ['prochaine_relance_at' => $limite]);
            (new LitigeHistorique())->log(
                (int) $courrier['litige_id'],
                $userId,
                'courrier',
                'Délai de mise en demeure',
                null,
                $limite,
                "Prochaine relance fixée à l'expiration du délai de {$delai} jours."
            );
        }
    }

    /** Page HTML autonome (impression, archivage, export). */
    public static function pageHtml(string $titre, string $contenu, string $numero = ''): string
    {
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<style>body{font-family:Arial,Helvetica,sans-serif;font-size:12pt;line-height:1.5;color:#111;max-width:760px;margin:30px auto;padding:0 20px}'
            . 'table{border-collapse:collapse;width:100%;margin:10px 0}th,td{border:1px solid #999;padding:6px 8px;text-align:left}th{background:#eee}'
            . '.ref{font-size:9pt;color:#666;margin-top:30px}@media print{body{margin:0}}</style></head><body>'
            . $contenu
            . ($numero !== '' ? '<p class="ref">Réf. courrier ' . htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') . '</p>' : '')
            . '</body></html>';
    }

    /** Version .doc : du HTML que Word ouvre nativement. */
    public static function versDoc(string $titre, string $contenu): string
    {
        return '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
            . '<head><meta charset="utf-8"><title>' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->'
            . '<style>body{font-family:Calibri,Arial,sans-serif;font-size:11pt}table{border-collapse:collapse}th,td{border:1px solid #777;padding:4px 6px}</style>'
            . '</head><body>' . $contenu . '</body></html>';
    }

    private function tableauEcheancier(int $litigeId): string
    {
        $lignes = (new EcheanceService())->avecStatuts($litigeId);
        if ($lignes === []) {
            return '<p><em>Aucun échéancier enregistré.</em></p>';
        }
        $html = '<table><thead><tr><th>N°</th><th>Date</th><th>Montant</th></tr></thead><tbody>';
        foreach ($lignes as $l) {
            $html .= '<tr><td>' . (int) $l['numero'] . '</td><td>' . date_fr((string) $l['date_echeance']) . '</td><td>'
                . htmlspecialchars(money($l['montant']), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    private function listePaiements(int $litigeId): string
    {
        $paiements = (new Paiement())->forLitige($litigeId);
        if ($paiements === []) {
            return '<p><em>Aucun paiement reçu à ce jour.</em></p>';
        }
        $html = '<ul>';
        foreach ($paiements as $p) {
            $html .= '<li>' . date_fr((string) $p['date_paiement']) . ' : '
                . htmlspecialchars(money($p['montant']), ENT_QUOTES, 'UTF-8') . '</li>';
        }
        return $html . '</ul>';
    }
}
