<?php
/**
 * Migration - Modeles de courriers, courriers generes (suivi d'envoi et
 * accuse de reception), taches, filtres enregistres et workflow par statut.
 * Idempotente : peut etre rejouee sans dommage.
 */

use App\Core\Schema;

return static function (\PDO $pdo): void {
    // --- Modeles de courriers -------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS modeles_courrier (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code       VARCHAR(40)  NOT NULL,
            nom        VARCHAR(150) NOT NULL,
            type       VARCHAR(30)  NOT NULL DEFAULT \'autre\',
            sujet      VARCHAR(190) DEFAULT NULL,
            contenu    LONGTEXT     NOT NULL,
            actif      TINYINT(1)   NOT NULL DEFAULT 1,
            systeme    TINYINT(1)   NOT NULL DEFAULT 0,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_modeles_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Courriers generes ------------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS courriers (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            numero        VARCHAR(30)  NOT NULL,
            litige_id     INT UNSIGNED NOT NULL,
            modele_id     INT UNSIGNED DEFAULT NULL,
            type          VARCHAR(30)  NOT NULL DEFAULT \'autre\',
            sujet         VARCHAR(190) DEFAULT NULL,
            contenu       LONGTEXT     NOT NULL,
            document_id   INT UNSIGNED DEFAULT NULL,
            mode_envoi    VARCHAR(20)  DEFAULT NULL,
            numero_suivi  VARCHAR(80)  DEFAULT NULL,
            date_envoi    DATE         DEFAULT NULL,
            date_ar       DATE         DEFAULT NULL,
            statut_ar     VARCHAR(20)  NOT NULL DEFAULT \'non_envoye\',
            created_by    INT UNSIGNED DEFAULT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_courriers_numero (numero),
            KEY idx_courriers_litige (litige_id),
            KEY idx_courriers_statut_ar (statut_ar),
            CONSTRAINT fk_courriers_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE,
            CONSTRAINT fk_courriers_modele FOREIGN KEY (modele_id) REFERENCES modeles_courrier (id) ON DELETE SET NULL,
            CONSTRAINT fk_courriers_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Taches --------------------------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS taches (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            litige_id   INT UNSIGNED DEFAULT NULL,
            client_id   INT UNSIGNED DEFAULT NULL,
            titre       VARCHAR(190) NOT NULL,
            description TEXT         DEFAULT NULL,
            assigne_a   INT UNSIGNED DEFAULT NULL,
            echeance    DATE         DEFAULT NULL,
            priorite    ENUM(\'basse\',\'normale\',\'haute\',\'critique\') NOT NULL DEFAULT \'normale\',
            statut      ENUM(\'a_faire\',\'en_cours\',\'faite\') NOT NULL DEFAULT \'a_faire\',
            done_at     DATETIME     DEFAULT NULL,
            created_by  INT UNSIGNED DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_taches_assigne (assigne_a, statut),
            KEY idx_taches_litige (litige_id),
            KEY idx_taches_echeance (echeance),
            CONSTRAINT fk_taches_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE,
            CONSTRAINT fk_taches_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
            CONSTRAINT fk_taches_assigne FOREIGN KEY (assigne_a) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_taches_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Filtres enregistres ----------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS filtres_enregistres (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    INT UNSIGNED NOT NULL,
            module     VARCHAR(30)  NOT NULL DEFAULT \'litiges\',
            nom        VARCHAR(100) NOT NULL,
            parametres TEXT         NOT NULL,
            partage    TINYINT(1)   NOT NULL DEFAULT 0,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_filtres_user (user_id, module),
            CONSTRAINT fk_filtres_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Workflow par statut --------------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS statut_transitions (
            de_statut_id   INT UNSIGNED NOT NULL,
            vers_statut_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (de_statut_id, vers_statut_id),
            CONSTRAINT fk_trans_de FOREIGN KEY (de_statut_id) REFERENCES litige_statuts (id) ON DELETE CASCADE,
            CONSTRAINT fk_trans_vers FOREIGN KEY (vers_statut_id) REFERENCES litige_statuts (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS statut_prerequis (
            id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
            statut_id INT UNSIGNED NOT NULL,
            regle     VARCHAR(40)  NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_prerequis (statut_id, regle),
            CONSTRAINT fk_prerequis_statut FOREIGN KEY (statut_id) REFERENCES litige_statuts (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Notifications : colonnes d'appoint ---------------------------------------
    Schema::ajouterIndex('notifications', 'idx_notif_user_created', ['user_id', 'created_at']);

    Schema::parametresParDefaut([
        'societe_adresse'        => '',
        'societe_telephone'      => '',
        'societe_email'          => '',
        'societe_signataire'     => '',
        'societe_iban'           => '',
        'courrier_delai_jours'   => '8',
    ]);

    // --- Modeles de courriers fournis ---------------------------------------------
    $modeles = [
        [
            'code' => 'relance_amiable', 'nom' => 'Relance amiable', 'type' => 'relance',
            'sujet' => 'Relance - facture {{litige.numero_facture}} - {{litige.reference}}',
            'contenu' => <<<'HTML'
<p>{{societe.nom}}<br>{{societe.adresse}}</p>
<p style="text-align:right">{{client.raison_sociale}}<br>{{client.adresse}}</p>
<p style="text-align:right">Le {{date.jour}}</p>
<p><strong>Objet : relance - facture n° {{litige.numero_facture}}</strong><br>Notre référence : {{litige.reference}}</p>
<p>Madame, Monsieur,</p>
<p>Sauf erreur ou omission de notre part, nous n'avons pas reçu le règlement de notre facture n° {{litige.numero_facture}} du {{litige.date_facture}} relative à : {{litige.objet}}.</p>
<p>Le solde restant dû s'élève à <strong>{{litige.encours}}</strong>.</p>
<p>Nous vous remercions de bien vouloir procéder à son règlement dans les meilleurs délais. Si votre paiement a été effectué entre-temps, merci de ne pas tenir compte de ce courrier et de nous en communiquer la référence.</p>
<p>Restant à votre disposition pour toute information, nous vous prions d'agréer, Madame, Monsieur, nos salutations distinguées.</p>
<p>{{auteur.nom}}<br>{{societe.nom}}</p>
HTML,
        ],
        [
            'code' => 'mise_en_demeure', 'nom' => 'Mise en demeure de payer', 'type' => 'mise_en_demeure',
            'sujet' => 'Mise en demeure de payer - {{litige.reference}}',
            'contenu' => <<<'HTML'
<p>{{societe.nom}}<br>{{societe.adresse}}</p>
<p style="text-align:right">{{client.raison_sociale}}<br>{{client.adresse}}</p>
<p style="text-align:right">Le {{date.jour}}</p>
<p><strong>LETTRE RECOMMANDÉE AVEC ACCUSÉ DE RÉCEPTION</strong></p>
<p><strong>Objet : mise en demeure de payer</strong><br>Notre référence : {{litige.reference}} - facture n° {{litige.numero_facture}} du {{litige.date_facture}}</p>
<p>Madame, Monsieur,</p>
<p>Malgré nos précédentes relances, la facture référencée ci-dessus (contrat {{litige.numero_contrat}} - {{litige.materiel}}) demeure impayée à ce jour.</p>
<p>Le montant restant dû est le suivant :</p>
<ul>
<li>Principal : {{litige.montant_principal}}</li>
<li>Pénalités et frais : {{litige.montant_penalites}}</li>
<li>Déjà réglé : {{litige.montant_recupere}}</li>
<li><strong>Solde dû : {{litige.encours}}</strong></li>
</ul>
<p>En conséquence, nous vous mettons en demeure de nous régler cette somme dans un délai de <strong>{{delai.jours}} jours</strong> à compter de la réception de la présente.</p>
<p>Nous vous rappelons que des pénalités de retard sont exigibles de plein droit, ainsi qu'une indemnité forfaitaire pour frais de recouvrement (articles L.441-10 et D.441-5 du Code de commerce).</p>
<p>À défaut de règlement dans ce délai, nous engagerons sans autre avis toutes les procédures de recouvrement utiles, y compris judiciaires, à vos frais.</p>
<p>Veuillez agréer, Madame, Monsieur, l'expression de nos salutations distinguées.</p>
<p>{{auteur.nom}}<br>{{societe.nom}}</p>
<p style="font-size:11px;color:#666">Modèle fourni à titre indicatif : à faire valider par votre conseil avant utilisation.</p>
HTML,
        ],
        [
            'code' => 'accord_echeancier', 'nom' => 'Confirmation d\'échéancier de paiement', 'type' => 'echeancier',
            'sujet' => 'Échéancier de paiement - {{litige.reference}}',
            'contenu' => <<<'HTML'
<p>{{societe.nom}}<br>{{societe.adresse}}</p>
<p style="text-align:right">{{client.raison_sociale}}<br>{{client.adresse}}</p>
<p style="text-align:right">Le {{date.jour}}</p>
<p><strong>Objet : confirmation de l'échéancier de paiement</strong><br>Notre référence : {{litige.reference}}</p>
<p>Madame, Monsieur,</p>
<p>Suite à nos échanges, nous vous confirmons l'échéancier de règlement convenu pour la somme de {{litige.encours}} :</p>
{{echeancier.tableau}}
<p>Le non-respect d'une seule échéance rendra immédiatement exigible le solde restant dû.</p>
<p>Règlement par virement : {{societe.iban}}</p>
<p>Veuillez agréer, Madame, Monsieur, nos salutations distinguées.</p>
<p>{{auteur.nom}}<br>{{societe.nom}}</p>
HTML,
        ],
        [
            'code' => 'declaration_creance', 'nom' => 'Déclaration de créance (procédure collective)', 'type' => 'creance',
            'sujet' => 'Déclaration de créance - {{client.raison_sociale}}',
            'contenu' => <<<'HTML'
<p>{{societe.nom}}<br>{{societe.adresse}}</p>
<p style="text-align:right">À l'attention de Maître le Mandataire judiciaire<br>de la société {{client.raison_sociale}} (SIREN {{client.siren}})</p>
<p style="text-align:right">Le {{date.jour}}</p>
<p><strong>LETTRE RECOMMANDÉE AVEC ACCUSÉ DE RÉCEPTION</strong></p>
<p><strong>Objet : déclaration de créance</strong> (articles L.622-24 et R.622-24 du Code de commerce)<br>Procédure : {{client.procedure}}</p>
<p>Maître,</p>
<p>Nous avons l'honneur de déclarer au passif de la société {{client.raison_sociale}} la créance suivante :</p>
<ul>
<li>Nature : {{litige.objet}} (facture n° {{litige.numero_facture}} du {{litige.date_facture}}, contrat {{litige.numero_contrat}})</li>
<li>Montant principal : {{litige.montant_principal}}</li>
<li>Pénalités et frais : {{litige.montant_penalites}}</li>
<li>Acomptes perçus : {{litige.montant_recupere}}</li>
<li><strong>Montant total déclaré : {{litige.encours}}</strong></li>
</ul>
<p>Vous trouverez ci-joint les pièces justificatives (factures, contrat de location, relances). Nous sollicitons l'admission de cette créance à titre chirographaire, sous réserve de toute sûreté ou droit de propriété sur le matériel loué.</p>
<p>Nous vous prions d'agréer, Maître, l'expression de nos salutations distinguées.</p>
<p>{{auteur.nom}}<br>{{societe.nom}}</p>
<p style="font-size:11px;color:#666">Modèle indicatif : à faire valider par votre conseil. Le délai de déclaration court à compter de la publication au BODACC.</p>
HTML,
        ],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO modeles_courrier (code, nom, type, sujet, contenu, actif, systeme)
         VALUES (:code, :nom, :type, :sujet, :contenu, 1, 1)
         ON DUPLICATE KEY UPDATE code = code'
    );
    foreach ($modeles as $m) {
        $stmt->execute($m);
    }
};
