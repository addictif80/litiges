<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\Audit;
use App\Core\Database;
use App\Enums\Priorite;
use App\Enums\TypeLitige;
use App\Exceptions\ValidationException;
use App\Models\Agence;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Litige;
use App\Models\LitigeStatut;
use App\Models\User;
use App\Services\LitigeService;
use App\Services\PaiementService;

/**
 * Import de clients, de litiges (factures impayees) et de reglements depuis
 * un fichier CSV / Excel, avec correspondance des colonnes, simulation et rapport.
 */
class ImportService
{
    public const TYPES = [
        'clients'    => 'Clients',
        'litiges'    => 'Litiges (factures impayées)',
        'reglements' => 'Règlements (paiements reçus)',
    ];

    private const MESSAGES_MAX = 300;

    /**
     * Champs importables par type : cle => [libelle, requis, synonymes d'en-tetes].
     *
     * @return array<string,array{0:string,1:bool,2:string[]}>
     */
    public static function champs(string $type): array
    {
        return match ($type) {
            'clients' => [
                'raison_sociale'    => ['Raison sociale', true, ['raison sociale', 'societe', 'nom', 'client', 'nom client', 'denomination', 'designation']],
                'siren'             => ['SIREN', false, ['siren', 'n siren', 'numero siren']],
                'siret'             => ['SIRET (le SIREN en est déduit)', false, ['siret', 'n siret', 'numero siret']],
                'code_erp'          => ['Code client (ERP)', false, ['code client', 'code', 'n client', 'numero client', 'tiers', 'code tiers', 'compte']],
                'tva_intra'         => ['N° TVA', false, ['tva', 'n tva', 'tva intra', 'numero tva']],
                'adresse'           => ['Adresse', false, ['adresse', 'adresse 1', 'rue']],
                'code_postal'       => ['Code postal', false, ['code postal', 'cp']],
                'ville'             => ['Ville', false, ['ville', 'commune']],
                'forme_juridique'   => ['Forme juridique', false, ['forme juridique', 'forme']],
                'contact_nom'       => ['Contact : nom', false, ['contact', 'nom contact', 'interlocuteur']],
                'contact_email'     => ['Contact : e-mail', false, ['email', 'e mail', 'mail', 'courriel', 'email contact']],
                'contact_telephone' => ['Contact : téléphone', false, ['telephone', 'tel', 'mobile', 'portable', 'telephone contact']],
            ],
            'litiges' => [
                'client_raison_sociale' => ['Client : raison sociale', false, ['raison sociale', 'client', 'nom client', 'societe', 'tiers', 'nom']],
                'client_siren'      => ['Client : SIREN', false, ['siren', 'n siren', 'siret']],
                'client_code_erp'   => ['Client : code ERP', false, ['code client', 'n client', 'numero client', 'code tiers', 'compte', 'code']],
                'numero_facture'    => ['N° de facture', false, ['n facture', 'numero facture', 'facture', 'n de facture', 'piece', 'n piece', 'no facture']],
                'date_facture'      => ['Date de facture', false, ['date facture', 'date de facture', 'date piece', 'date']],
                'date_echeance'     => ["Date d'échéance", false, ['echeance', 'date echeance', 'date d echeance']],
                'montant_principal' => ['Montant dû', true, ['reste a payer', 'solde', 'montant du', 'reste du', 'montant ttc', 'montant', 'total ttc', 'impaye']],
                'objet'             => ['Objet', false, ['objet', 'libelle', 'designation', 'description']],
                'numero_contrat'    => ['N° de contrat', false, ['contrat', 'n contrat', 'numero contrat', 'dossier', 'n dossier']],
                'materiel'          => ['Matériel', false, ['materiel', 'machine', 'engin', 'vehicule', 'article']],
                'agence'            => ['Agence', false, ['agence', 'etablissement', 'site', 'depot']],
                'responsable'       => ['Responsable (identifiant)', false, ['responsable', 'gestionnaire', 'commercial', 'utilisateur']],
                'priorite'          => ['Priorité', false, ['priorite']],
                'type_litige'       => ['Type de litige', false, ['type', 'type litige']],
                'statut'            => ['Statut', false, ['statut']],
                'date_ouverture'    => ["Date d'ouverture", false, ['date ouverture', 'ouverture']],
                'reference_externe' => ['Référence externe', false, ['reference', 'reference externe', 'ref']],
            ],
            'reglements' => [
                'numero_facture'  => ['N° de facture', true, ['n facture', 'numero facture', 'facture', 'n de facture', 'piece', 'n piece', 'no facture', 'facture reglee']],
                'client_code_erp' => ['Client : code ERP (aide au rapprochement)', false, ['code client', 'n client', 'numero client', 'code tiers', 'compte']],
                'date_paiement'   => ['Date du règlement', true, ['date reglement', 'date paiement', 'date', 'date encaissement', 'date de reglement']],
                'montant'         => ['Montant réglé', true, ['montant', 'montant regle', 'reglement', 'encaissement', 'credit', 'montant ttc']],
                'mode'            => ['Mode de règlement', false, ['mode', 'mode reglement', 'mode de reglement', 'moyen paiement']],
                'reference'       => ['Référence du règlement', false, ['reference', 'n reglement', 'numero reglement', 'n piece reglement', 'n cheque', 'libelle']],
            ],
            default => [],
        };
    }

    /**
     * Propose une correspondance champ => index de colonne a partir des en-tetes.
     *
     * @param string[] $entetes
     * @return array<string,int|null>
     */
    public static function proposerMapping(string $type, array $entetes): array
    {
        $normalises = array_map([ImportParser::class, 'normaliserEntete'], $entetes);
        $mapping = [];
        $utilises = [];
        foreach (self::champs($type) as $champ => [$libelle, $requis, $synonymes]) {
            $mapping[$champ] = null;
            foreach ($synonymes as $synonyme) {
                $index = array_search($synonyme, $normalises, true);
                if ($index !== false && !isset($utilises[$index])) {
                    $mapping[$champ] = (int) $index;
                    $utilises[$index] = true;
                    break;
                }
            }
        }
        return $mapping;
    }

    /**
     * Lit un fichier CSV / TXT / XLSX.
     *
     * @return array{entetes:string[], lignes:array<int,array<int,string>>}
     */
    public static function lireFichier(string $chemin, string $nomOriginal): array
    {
        $extension = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            $toutes = XlsxReader::lire($chemin);
            $entetes = array_shift($toutes) ?? [];
            return ['entetes' => $entetes, 'lignes' => $toutes];
        }
        if (!in_array($extension, ['csv', 'txt'], true)) {
            throw new \RuntimeException('Format non pris en charge : utilisez un fichier .csv, .txt ou .xlsx.');
        }
        $analyse = ImportParser::lireCsvTexte((string) file_get_contents($chemin));
        return ['entetes' => $analyse['entetes'], 'lignes' => $analyse['lignes']];
    }

    /**
     * Mapping a partir d'un profil enregistre (champ => nom d'en-tete), complete
     * par la detection automatique pour les champs absents du profil.
     *
     * @param string[]             $entetes
     * @param array<string,string> $profil
     * @return array<string,int|null>
     */
    public static function mappingDepuisProfil(string $type, array $entetes, array $profil): array
    {
        $normalises = array_map([ImportParser::class, 'normaliserEntete'], $entetes);
        $auto = self::proposerMapping($type, $entetes);
        $mapping = [];
        foreach (array_keys(self::champs($type)) as $champ) {
            $mapping[$champ] = $auto[$champ] ?? null;
            if (!empty($profil[$champ])) {
                $index = array_search(ImportParser::normaliserEntete((string) $profil[$champ]), $normalises, true);
                if ($index !== false) {
                    $mapping[$champ] = (int) $index;
                }
            }
        }
        return $mapping;
    }

    /**
     * Execute (ou simule) l'import.
     *
     * @param string[]                     $entetes
     * @param array<int,array<int,string>> $lignes
     * @param array<string,int|null>       $mapping champ => index de colonne
     * @param array{doublons?:string, creer_clients?:bool, notifier?:bool} $options
     * @return array{lignes:int, crees:int, mis_a_jour:int, ignores:int, erreurs:int, messages:array<int,array{ligne:int,niveau:string,texte:string}>}
     */
    public function importer(string $type, array $lignes, array $mapping, array $options, bool $ecrire, ?int $userId, string $source = 'manuel'): array
    {
        $rapport = ['lignes' => count($lignes), 'crees' => 0, 'mis_a_jour' => 0, 'ignores' => 0, 'erreurs' => 0, 'messages' => []];
        $contexte = [
            'options'  => $options + ['doublons' => 'ignorer', 'creer_clients' => false, 'notifier' => false],
            'ecrire'   => $ecrire,
            'userId'   => $userId,
            'source'   => $source,
            'virtuels' => [],
        ];

        foreach ($lignes as $i => $ligne) {
            $numeroLigne = $i + 2; // ligne 1 = en-tetes
            $donnees = [];
            foreach ($mapping as $champ => $index) {
                $donnees[$champ] = ($index !== null && isset($ligne[$index])) ? trim((string) $ligne[$index]) : '';
            }

            try {
                $resultat = match ($type) {
                    'clients'    => $this->ligneClient($donnees, $contexte),
                    'litiges'    => $this->ligneLitige($donnees, $contexte),
                    'reglements' => $this->ligneReglement($donnees, $contexte),
                    default      => throw new ValidationException(['type' => 'Type d\'import inconnu.']),
                };
                $rapport[$resultat['etat']]++;
                if (!empty($resultat['message'])) {
                    $this->message($rapport, $numeroLigne, $resultat['etat'] === 'ignores' ? 'info' : 'ok', $resultat['message']);
                }
            } catch (ValidationException $e) {
                $rapport['erreurs']++;
                $this->message($rapport, $numeroLigne, 'erreur', implode(' ', $e->erreurs));
            } catch (\Throwable $e) {
                $rapport['erreurs']++;
                $this->message($rapport, $numeroLigne, 'erreur', 'Erreur inattendue : ' . $e->getMessage());
            }
        }

        if ($ecrire) {
            Audit::log('import_' . $type, 'import', null, [
                'source' => $source, 'lignes' => $rapport['lignes'], 'crees' => $rapport['crees'],
                'maj' => $rapport['mis_a_jour'], 'erreurs' => $rapport['erreurs'],
            ]);
        }
        return $rapport;
    }

    // ------------------------------------------------------------------
    //  Clients
    // ------------------------------------------------------------------

    /** @return array{etat:string, message?:string} */
    private function ligneClient(array $d, array &$ctx): array
    {
        if ($d['raison_sociale'] === '') {
            throw new ValidationException(['raison_sociale' => 'La raison sociale est vide.']);
        }

        $siren = ImportParser::siren($d['siren'] ?? '') ?? ImportParser::siren($d['siret'] ?? '');
        $d['siren'] = $siren ?? '';
        $data = array_filter([
            'raison_sociale'  => mb_substr($d['raison_sociale'], 0, 190),
            'siren'           => $siren,
            'siret'           => preg_match('/^\d{14}$/', preg_replace('/\s+/', '', (string) ($d['siret'] ?? ''))) ? preg_replace('/\s+/', '', $d['siret']) : null,
            'code_erp'        => ($d['code_erp'] ?? '') !== '' ? mb_substr($d['code_erp'], 0, 40) : null,
            'tva_intra'       => ($d['tva_intra'] ?? '') !== '' ? mb_substr($d['tva_intra'], 0, 20) : null,
            'adresse'         => ($d['adresse'] ?? '') !== '' ? mb_substr($d['adresse'], 0, 255) : null,
            'code_postal'     => ($d['code_postal'] ?? '') !== '' ? mb_substr($d['code_postal'], 0, 12) : null,
            'ville'           => ($d['ville'] ?? '') !== '' ? mb_substr($d['ville'], 0, 120) : null,
            'forme_juridique' => ($d['forme_juridique'] ?? '') !== '' ? mb_substr($d['forme_juridique'], 0, 120) : null,
        ], static fn ($v): bool => $v !== null);

        $clients  = new Client();
        $existant = $this->trouverClient($d, $ctx);

        if ($existant) {
            if ($ctx['options']['doublons'] === 'ignorer') {
                return ['etat' => 'ignores', 'message' => $d['raison_sociale'] . ' : déjà présent'];
            }
            if ($ctx['ecrire']) {
                $maj = $data;
                unset($maj['raison_sociale']); // on ne renomme pas un client existant
                if ($maj !== []) {
                    $clients->update((int) $existant['id'], $maj);
                }
                $this->contact((int) $existant['id'], $d);
            }
            return ['etat' => 'mis_a_jour', 'message' => $d['raison_sociale'] . ' : mis à jour'];
        }

        if ($ctx['ecrire']) {
            $data['created_by'] = $ctx['userId'];
            $id = $clients->create($data);
            $this->contact($id, $d);
        } else {
            $ctx['virtuels'][$this->cleClient($d)] = true;
        }
        return ['etat' => 'crees', 'message' => $d['raison_sociale'] . ' : créé'];
    }

    private function contact(int $clientId, array $d): void
    {
        $nom = trim((string) ($d['contact_nom'] ?? ''));
        if ($nom === '') {
            return;
        }
        $email = trim((string) ($d['contact_email'] ?? ''));
        $deja = Database::exists('contacts', 'client_id = :c AND (nom = :n' . ($email !== '' ? ' OR email = :e' : '') . ')',
            ['c' => $clientId, 'n' => mb_substr($nom, 0, 120)] + ($email !== '' ? ['e' => $email] : []));
        if ($deja) {
            return;
        }
        (new Contact())->create([
            'client_id' => $clientId,
            'nom'       => mb_substr($nom, 0, 120),
            'email'     => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'telephone' => ($t = trim((string) ($d['contact_telephone'] ?? ''))) !== '' ? mb_substr($t, 0, 40) : null,
            'principal' => Database::exists('contacts', 'client_id = :c AND principal = 1', ['c' => $clientId]) ? 0 : 1,
        ]);
    }

    // ------------------------------------------------------------------
    //  Litiges
    // ------------------------------------------------------------------

    /** @return array{etat:string, message?:string} */
    private function ligneLitige(array $d, array &$ctx): array
    {
        $montant = ImportParser::nombre($d['montant_principal'] ?? '');
        if ($montant === null || $montant <= 0) {
            return ['etat' => 'ignores', 'message' => 'Montant nul ou invalide : ligne ignorée'];
        }

        $cle = [
            'raison_sociale' => $d['client_raison_sociale'] ?? '',
            'siren'          => $d['client_siren'] ?? '',
            'code_erp'       => $d['client_code_erp'] ?? '',
        ];
        $client = $this->trouverClient($cle, $ctx);
        if (!$client) {
            if (!$ctx['options']['creer_clients'] || $cle['raison_sociale'] === '') {
                throw new ValidationException(['client' => 'Client introuvable (' . ($cle['raison_sociale'] ?: $cle['code_erp'] ?: $cle['siren'] ?: 'non renseigné') . ').']);
            }
            if ($ctx['ecrire']) {
                $client = ['id' => (new Client())->create(array_filter([
                    'raison_sociale' => mb_substr($cle['raison_sociale'], 0, 190),
                    'siren'          => ImportParser::siren($cle['siren']),
                    'code_erp'       => $cle['code_erp'] !== '' ? mb_substr($cle['code_erp'], 0, 40) : null,
                    'created_by'     => $ctx['userId'],
                ], static fn ($v): bool => $v !== null))];
            } else {
                $ctx['virtuels'][$this->cleClient($cle)] = true;
                return ['etat' => 'crees', 'message' => 'Nouveau client « ' . $cle['raison_sociale'] . ' » et litige à créer'];
            }
        }

        $facture = $d['numero_facture'] ?? '';
        $litiges = new Litige();

        // Rapprochement d'un litige existant sur (client, numero de facture).
        if ($facture !== '') {
            $existant = Database::fetch(
                'SELECT * FROM litiges WHERE client_id = :c AND numero_facture = :f LIMIT 1',
                ['c' => (int) $client['id'], 'f' => $facture]
            );
            if ($existant) {
                if ($ctx['options']['doublons'] === 'ignorer') {
                    return ['etat' => 'ignores', 'message' => 'Facture ' . $facture . ' : litige ' . $existant['reference'] . ' déjà présent'];
                }
                $maj = ['montant_principal' => $montant];
                if (($e = ImportParser::date($d['date_echeance'] ?? '')) !== null) {
                    $maj['date_echeance'] = $e;
                }
                if ($ctx['ecrire'] && abs((float) $existant['montant_principal'] - $montant) >= 0.005) {
                    $litiges->update((int) $existant['id'], $maj);
                    return ['etat' => 'mis_a_jour', 'message' => 'Facture ' . $facture . ' : montant mis à jour'];
                }
                return ['etat' => $ctx['ecrire'] ? 'ignores' : 'mis_a_jour', 'message' => 'Facture ' . $facture . ' : ' . ($ctx['ecrire'] ? 'inchangée' : 'serait mise à jour')];
            }
        }

        $statutModel = new LitigeStatut();
        $statutId = $statutModel->defaultStatutId();
        if (($d['statut'] ?? '') !== '') {
            $trouve = Database::value("SELECT id FROM litige_statuts WHERE categorie='statut' AND (LOWER(libelle)=LOWER(:s) OR code=:c) LIMIT 1", ['s' => $d['statut'], 'c' => $d['statut']]);
            $statutId = $trouve ? (int) $trouve : $statutId;
        }

        $agenceId = null;
        if (($d['agence'] ?? '') !== '') {
            $a = Database::value('SELECT id FROM agences WHERE LOWER(nom) = LOWER(:n) OR LOWER(code) = LOWER(:n2) LIMIT 1', ['n' => $d['agence'], 'n2' => $d['agence']]);
            $agenceId = $a ? (int) $a : null;
        }
        $responsableId = null;
        if (($d['responsable'] ?? '') !== '') {
            $u = Database::value('SELECT id FROM users WHERE actif = 1 AND (LOWER(username)=LOWER(:u) OR LOWER(full_name)=LOWER(:u2)) LIMIT 1', ['u' => $d['responsable'], 'u2' => $d['responsable']]);
            $responsableId = $u ? (int) $u : null;
        }

        $priorite = strtolower($d['priorite'] ?? '');
        $priorite = in_array($priorite, Priorite::valeurs(), true) ? $priorite : 'normale';

        $type = null;
        $typeSaisi = mb_strtolower($d['type_litige'] ?? '');
        foreach (TypeLitige::options() as $valeur => $libelle) {
            if ($typeSaisi !== '' && ($typeSaisi === $valeur || $typeSaisi === mb_strtolower($libelle))) {
                $type = $valeur;
            }
        }

        $objet = ($d['objet'] ?? '') !== '' ? $d['objet'] : ($facture !== '' ? 'Impayé facture ' . $facture : 'Impayé importé');

        if (!$ctx['ecrire']) {
            return ['etat' => 'crees', 'message' => 'Litige à créer : ' . $objet];
        }

        $id = (new LitigeService())->creer([
            'client_id'         => (int) $client['id'],
            'agence_id'         => $agenceId,
            'objet'             => mb_substr($objet, 0, 190),
            'type_litige'       => $type ?? 'impaye',
            'montant_principal' => $montant,
            'date_ouverture'    => ImportParser::date($d['date_ouverture'] ?? '') ?? date('Y-m-d'),
            'date_echeance'     => ImportParser::date($d['date_echeance'] ?? ''),
            'date_facture'      => ImportParser::date($d['date_facture'] ?? ''),
            'statut_id'         => $statutId,
            'responsable_id'    => $responsableId,
            'priorite'          => $priorite,
            'numero_facture'    => $facture !== '' ? $facture : null,
            'numero_contrat'    => ($d['numero_contrat'] ?? '') ?: null,
            'materiel'          => ($d['materiel'] ?? '') ?: null,
            'reference_externe' => ($d['reference_externe'] ?? '') ?: null,
            'delai_relance_jours' => (int) \App\Core\Settings::get('delai_relance_defaut_jours', 15),
        ], $ctx['userId'], true, (bool) $ctx['options']['notifier']);

        $ref = $litiges->find($id)['reference'] ?? '';
        return ['etat' => 'crees', 'message' => 'Litige ' . $ref . ' créé'];
    }

    // ------------------------------------------------------------------
    //  Reglements
    // ------------------------------------------------------------------

    /** @return array{etat:string, message?:string} */
    private function ligneReglement(array $d, array &$ctx): array
    {
        $facture = $d['numero_facture'] ?? '';
        $montant = ImportParser::nombre($d['montant'] ?? '');
        $date    = ImportParser::date($d['date_paiement'] ?? '');

        if ($facture === '' || $montant === null || $montant <= 0 || $date === null) {
            return ['etat' => 'ignores', 'message' => 'Règlement incomplet (facture, date ou montant manquant/invalide)'];
        }

        $params = ['f' => $facture];
        $sql = 'SELECT l.id, l.reference FROM litiges l';
        $where = ' WHERE l.numero_facture = :f';
        if (($d['client_code_erp'] ?? '') !== '') {
            $sql .= ' JOIN clients c ON c.id = l.client_id';
            $where .= ' AND c.code_erp = :ce';
            $params['ce'] = $d['client_code_erp'];
        }
        $trouves = Database::fetchAll($sql . $where . ' LIMIT 3', $params);

        if (count($trouves) === 0) {
            throw new ValidationException(['facture' => 'Aucun litige pour la facture ' . $facture . ' (règlement non rapproché).']);
        }
        if (count($trouves) > 1) {
            throw new ValidationException(['facture' => 'Facture ' . $facture . ' présente sur plusieurs litiges : précisez le code client.']);
        }

        $litigeId = (int) $trouves[0]['id'];
        $reference = $d['reference'] ?? '';
        $sourceRef = substr(sha1($facture . '|' . $date . '|' . number_format($montant, 2, '.', '') . '|' . $reference), 0, 40);

        if (!$ctx['ecrire']) {
            $deja = Database::exists('paiements', 'litige_id = :l AND source = :s AND source_ref = :r', ['l' => $litigeId, 's' => $ctx['source'] === 'blg' ? 'blg' : 'import', 'r' => $sourceRef]);
            return ['etat' => $deja ? 'ignores' : 'crees', 'message' => $trouves[0]['reference'] . ($deja ? ' : règlement déjà pris en compte' : ' : règlement à enregistrer')];
        }

        $id = (new PaiementService())->enregistrer($litigeId, [
            'date_paiement' => $date,
            'montant'       => $montant,
            'mode'          => $this->modePaiement($d['mode'] ?? ''),
            'reference'     => $reference,
            'commentaire'   => 'Importé (' . $ctx['source'] . ')',
        ], $ctx['userId'], $ctx['source'] === 'blg' ? 'blg' : 'import', $sourceRef);

        return $id === 0
            ? ['etat' => 'ignores', 'message' => $trouves[0]['reference'] . ' : règlement déjà pris en compte']
            : ['etat' => 'crees', 'message' => $trouves[0]['reference'] . ' : règlement de ' . number_format($montant, 2, ',', ' ') . ' € enregistré'];
    }

    private function modePaiement(string $libelle): string
    {
        $l = ImportParser::normaliserEntete($libelle);
        return match (true) {
            str_contains($l, 'vir')   => 'virement',
            str_contains($l, 'cheq')  => 'cheque',
            str_contains($l, 'carte'), $l === 'cb' => 'cb',
            str_contains($l, 'prel')  => 'prelevement',
            str_contains($l, 'esp')   => 'especes',
            default                   => 'autre',
        };
    }

    // ------------------------------------------------------------------
    //  Utilitaires
    // ------------------------------------------------------------------

    /** Recherche d'un client par SIREN, code ERP puis raison sociale exacte. */
    private function trouverClient(array $d, array $ctx): ?array
    {
        $siren = ImportParser::siren($d['siren'] ?? '');
        if ($siren !== null && ($c = (new Client())->findBySiren($siren))) {
            return $c;
        }
        $code = trim((string) ($d['code_erp'] ?? ''));
        if ($code !== '' && ($c = (new Client())->findByCodeErp($code))) {
            return $c;
        }
        $nom = trim((string) ($d['raison_sociale'] ?? ''));
        if ($nom !== '') {
            $c = Database::fetch('SELECT * FROM clients WHERE LOWER(raison_sociale) = LOWER(:n) LIMIT 1', ['n' => $nom]);
            if ($c) {
                return $c;
            }
        }
        // Client cree plus haut pendant une simulation.
        if (isset($ctx['virtuels'][$this->cleClient($d)])) {
            return ['id' => 0, 'virtuel' => true];
        }
        return null;
    }

    private function cleClient(array $d): string
    {
        return mb_strtolower(trim((string) ($d['siren'] ?? '')) . '|' . trim((string) ($d['code_erp'] ?? '')) . '|' . trim((string) ($d['raison_sociale'] ?? '')));
    }

    /** @param array<string,mixed> $rapport */
    private function message(array &$rapport, int $ligne, string $niveau, string $texte): void
    {
        if (count($rapport['messages']) < self::MESSAGES_MAX) {
            $rapport['messages'][] = ['ligne' => $ligne, 'niveau' => $niveau, 'texte' => $texte];
        }
    }
}
