<?php
/**
 * Migration - API REST (jetons), webhooks, journal des imports (ERP BLG, CSV),
 * registre RGPD.
 * Idempotente : peut etre rejouee sans dommage.
 */

use App\Core\Schema;

return static function (\PDO $pdo): void {
    // --- Jetons d'API ------------------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS api_tokens (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nom          VARCHAR(120) NOT NULL,
            prefixe      VARCHAR(12)  NOT NULL,
            token_hash   CHAR(64)     NOT NULL,
            user_id      INT UNSIGNED NOT NULL,
            ecriture     TINYINT(1)   NOT NULL DEFAULT 0,
            expires_at   DATETIME     DEFAULT NULL,
            last_used_at DATETIME     DEFAULT NULL,
            actif        TINYINT(1)   NOT NULL DEFAULT 1,
            created_by   INT UNSIGNED DEFAULT NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_api_tokens_hash (token_hash),
            CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Webhooks ------------------------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS webhooks (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nom          VARCHAR(120) NOT NULL,
            url          VARCHAR(500) NOT NULL,
            secret       TEXT         NOT NULL,
            evenements   TEXT         NOT NULL,
            actif        TINYINT(1)   NOT NULL DEFAULT 1,
            dernier_statut SMALLINT   DEFAULT NULL,
            dernier_envoi_at DATETIME DEFAULT NULL,
            echecs_consecutifs INT UNSIGNED NOT NULL DEFAULT 0,
            created_by   INT UNSIGNED DEFAULT NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS webhook_livraisons (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_id    INT UNSIGNED NOT NULL,
            evenement     VARCHAR(60)  NOT NULL,
            charge        MEDIUMTEXT   NOT NULL,
            statut        ENUM(\'en_attente\',\'livre\',\'echec\',\'abandonne\') NOT NULL DEFAULT \'en_attente\',
            tentatives    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            code_http     SMALLINT     DEFAULT NULL,
            erreur        VARCHAR(255) DEFAULT NULL,
            prochaine_tentative_at DATETIME DEFAULT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_livraisons_statut (statut, prochaine_tentative_at),
            KEY idx_livraisons_webhook (webhook_id),
            CONSTRAINT fk_livraisons_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Journal des imports (CSV, ERP BLG) ----------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS import_journal (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            type        VARCHAR(30)  NOT NULL,
            fichier     VARCHAR(255) DEFAULT NULL,
            empreinte   CHAR(40)     DEFAULT NULL,
            lignes      INT UNSIGNED NOT NULL DEFAULT 0,
            crees       INT UNSIGNED NOT NULL DEFAULT 0,
            mis_a_jour  INT UNSIGNED NOT NULL DEFAULT 0,
            ignores     INT UNSIGNED NOT NULL DEFAULT 0,
            erreurs     INT UNSIGNED NOT NULL DEFAULT 0,
            details     MEDIUMTEXT   DEFAULT NULL,
            source      VARCHAR(20)  NOT NULL DEFAULT \'manuel\',
            created_by  INT UNSIGNED DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_import_empreinte (type, empreinte),
            CONSTRAINT fk_import_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Identifiant ERP sur les clients / litiges ---------------------------------
    Schema::ajouterColonne('clients', 'code_erp', 'VARCHAR(40) DEFAULT NULL');
    Schema::ajouterIndex('clients', 'idx_clients_code_erp', ['code_erp']);

    // --- Registre RGPD -----------------------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rgpd_registre (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            traitement    VARCHAR(190) NOT NULL,
            finalite      TEXT         NOT NULL,
            base_legale   VARCHAR(190) NOT NULL,
            categories_donnees TEXT    NOT NULL,
            personnes     VARCHAR(190) NOT NULL,
            destinataires VARCHAR(255) DEFAULT NULL,
            duree_conservation VARCHAR(190) NOT NULL,
            mesures_securite TEXT      DEFAULT NULL,
            ordre         INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if ((int) $pdo->query('SELECT COUNT(*) FROM rgpd_registre')->fetchColumn() === 0) {
        $securite = 'Accès par comptes nominatifs, rôles, double authentification, journal d\'audit, hébergement sécurisé, sauvegardes.';
        $lignes = [
            ['Gestion des litiges et du recouvrement', 'Suivi des impayés et litiges de location, relances, recouvrement amiable et contentieux.', 'Intérêt légitime (recouvrement des créances) - exécution du contrat', 'Identité et coordonnées des contacts clients, historique des échanges, montants dus', 'Clients (personnes morales) et leurs contacts', 'Gestionnaires habilités, conseil juridique / huissier le cas échéant', '5 ans après la clôture du litige (prescription commerciale), puis anonymisation', $securite, 10],
            ['Gestion de la relation client (fiches et contacts)', 'Tenue des fiches clients et des interlocuteurs.', 'Intérêt légitime', 'Nom, prénom, fonction, e-mail, téléphone des contacts ; données légales de l\'entreprise', 'Contacts professionnels des clients', 'Utilisateurs habilités de l\'application', 'Durée de la relation commerciale + 3 ans, puis anonymisation', $securite, 20],
            ['Gestion des comptes utilisateurs', 'Authentification, droits d\'accès, traçabilité.', 'Intérêt légitime (sécurité du système d\'information)', 'Identifiant, nom, e-mail, rôle, adresses IP, journal d\'audit', 'Salariés utilisateurs de l\'application', 'Administrateurs', 'Durée du contrat de travail ; journal d\'audit : 12 mois', $securite, 30],
            ['Vérification de la solvabilité des clients', 'Évaluation du risque client à partir de données publiques (Sirene, BODACC) et de l\'historique interne.', 'Intérêt légitime', 'Données publiques d\'entreprise, dirigeants (données publiques), score interne', 'Entreprises clientes et leurs dirigeants', 'Gestionnaires habilités', 'Durée de la relation commerciale', $securite, 40],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO rgpd_registre (traitement, finalite, base_legale, categories_donnees, personnes, destinataires, duree_conservation, mesures_securite, ordre)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lignes as $l) {
            $stmt->execute($l);
        }
    }

    Schema::parametresParDefaut([
        'rgpd_retention_mois'        => '0',   // 0 = pas de purge automatique
        'rgpd_anonymiser_contacts'   => '1',
        'rgpd_supprimer_litiges'     => '0',   // suppression complete des litiges clos depuis plus longtemps que la retention
        'audit_retention_mois'       => '0',
        'blg_actif'                  => '0',
        'blg_dossier'                => '',    // dossier surveille (relatif a storage/ ou absolu)
        'blg_profil_factures'        => '',
        'blg_profil_reglements'      => '',
        'rapport_hebdo_actif'        => '0',
        'rapport_hebdo_jour'         => '1',   // 1 = lundi
        'rapport_hebdo_destinataires' => 'admin',
    ]);
};
