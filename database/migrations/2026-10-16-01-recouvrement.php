<?php
/**
 * Migration - Recouvrement : journal des paiements, echeanciers, penalites
 * de retard automatiques, suivi des procedures collectives (BODACC).
 * Idempotente : peut etre rejouee sans dommage.
 */

use App\Core\Schema;

return static function (\PDO $pdo): void {
    // --- Paiements -----------------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS paiements (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            litige_id     INT UNSIGNED NOT NULL,
            date_paiement DATE         NOT NULL,
            montant       DECIMAL(12,2) NOT NULL,
            mode          VARCHAR(30)  NOT NULL DEFAULT \'virement\',
            reference     VARCHAR(120) DEFAULT NULL,
            commentaire   VARCHAR(255) DEFAULT NULL,
            source        VARCHAR(20)  NOT NULL DEFAULT \'manuel\',
            source_ref    VARCHAR(120) DEFAULT NULL,
            created_by    INT UNSIGNED DEFAULT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_paiements_source (litige_id, source, source_ref),
            KEY idx_paiements_date (date_paiement),
            CONSTRAINT fk_paiements_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE,
            CONSTRAINT fk_paiements_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // Reprise : les montants deja saisis a la main deviennent un paiement unique.
    $pdo->exec(
        "INSERT INTO paiements (litige_id, date_paiement, montant, mode, commentaire, source, source_ref)
         SELECT l.id, COALESCE(l.date_cloture, DATE(l.updated_at)), l.montant_recupere, 'autre',
                'Reprise du solde saisi avant le journal des paiements', 'reprise', 'reprise'
         FROM litiges l
         WHERE l.montant_recupere > 0
           AND NOT EXISTS (SELECT 1 FROM paiements p WHERE p.litige_id = l.id)"
    );

    // --- Echeanciers ---------------------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS echeances (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            litige_id     INT UNSIGNED NOT NULL,
            numero        SMALLINT UNSIGNED NOT NULL,
            date_echeance DATE         NOT NULL,
            montant       DECIMAL(12,2) NOT NULL,
            montant_regle DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_by    INT UNSIGNED DEFAULT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_echeances_numero (litige_id, numero),
            KEY idx_echeances_date (date_echeance),
            CONSTRAINT fk_echeances_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE,
            CONSTRAINT fk_echeances_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Penalites de retard automatiques -----------------------------------
    Schema::ajouterColonne('litiges', 'penalites_auto', 'TINYINT(1) NOT NULL DEFAULT 0');
    Schema::ajouterColonne('litiges', 'taux_penalite', 'DECIMAL(5,2) DEFAULT NULL');
    Schema::ajouterColonne('litiges', 'indemnite_forfaitaire', 'TINYINT(1) NOT NULL DEFAULT 0');
    Schema::ajouterColonne('litiges', 'penalites_depuis', 'DATE DEFAULT NULL');
    Schema::ajouterColonne('litiges', 'penalites_calculees_le', 'DATE DEFAULT NULL');
    Schema::ajouterColonne('litiges', 'creance_declaree_le', 'DATE DEFAULT NULL');

    // --- Procedures collectives (surveillance BODACC) -----------------------
    Schema::ajouterColonne('clients', 'procedure_date_parution', 'DATE DEFAULT NULL');
    Schema::ajouterColonne('clients', 'bodacc_derniere_annonce', 'VARCHAR(120) DEFAULT NULL');
    Schema::ajouterColonne('clients', 'bodacc_checked_at', 'DATETIME DEFAULT NULL');
    Schema::ajouterIndex('clients', 'idx_clients_bodacc_check', ['bodacc_checked_at']);

    Schema::parametresParDefaut([
        'penalite_taux_annuel'          => '12.00',   // a ajuster : 3x le taux d'interet legal ou taux contractuel
        'penalite_indemnite_forfaitaire' => '40.00',  // art. D.441-5 du Code de commerce
        'penalite_activer_cron'         => '1',
        'bodacc_surveillance_active'    => '1',
        'bodacc_surveillance_lot'       => '40',      // clients controles par execution du cron
        'creance_delai_mois'            => '2',       // art. R.622-24 (4 mois hors metropole)
    ]);
};
