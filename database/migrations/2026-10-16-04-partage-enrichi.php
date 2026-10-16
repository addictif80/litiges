<?php
/**
 * Migration - Partage public enrichi : mot de passe facultatif, documents
 * choisis un par un, journal des consultations, expiration par defaut.
 * Idempotente : peut etre rejouee sans dommage.
 */

use App\Core\Schema;

return static function (\PDO $pdo): void {
    Schema::ajouterColonne('partages', 'mot_de_passe_hash', 'VARCHAR(255) DEFAULT NULL');
    Schema::ajouterColonne('partages', 'afficher_documents', 'TINYINT(1) NOT NULL DEFAULT 0');
    Schema::ajouterColonne('documents', 'partage_public', 'TINYINT(1) NOT NULL DEFAULT 0');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS partage_consultations (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            partage_id INT UNSIGNED NOT NULL,
            litige_id  INT UNSIGNED DEFAULT NULL,
            action     VARCHAR(30)  NOT NULL,
            ip         VARCHAR(45)  DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_consultations_partage (partage_id, id),
            CONSTRAINT fk_consultations_partage FOREIGN KEY (partage_id) REFERENCES partages (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    Schema::parametresParDefaut([
        'partage_expiration_defaut_jours' => '30', // 0 = jamais ; applique aux nouveaux partages sans date d'expiration
    ]);
};
