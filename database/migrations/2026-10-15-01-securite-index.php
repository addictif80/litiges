<?php
/**
 * Migration - Securite (limitation de tentatives, double authentification),
 * jeton d'agenda iCal et index de performance.
 * Idempotente : peut etre rejouee sans dommage.
 */

use App\Core\Schema;

return static function (\PDO $pdo): void {
    // --- Limitation de tentatives ------------------------------------------
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rate_limits (
            cle          VARCHAR(120) NOT NULL,
            hits         INT UNSIGNED NOT NULL DEFAULT 0,
            window_start DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (cle),
            KEY idx_rate_limits_window (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Double authentification (TOTP) + jeton agenda ---------------------
    Schema::ajouterColonne('users', 'totp_secret', 'VARCHAR(255) DEFAULT NULL');
    Schema::ajouterColonne('users', 'totp_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
    Schema::ajouterColonne('users', 'totp_recovery', 'TEXT DEFAULT NULL');
    Schema::ajouterColonne('users', 'totp_last_step', 'BIGINT DEFAULT NULL');
    Schema::ajouterColonne('users', 'ical_token', 'VARCHAR(64) DEFAULT NULL');
    Schema::ajouterIndex('users', 'uk_users_ical_token', ['ical_token'], true);

    // --- Index de performance ----------------------------------------------
    Schema::ajouterIndex('litiges', 'idx_litiges_statut_relance', ['statut_id', 'prochaine_relance_at']);
    Schema::ajouterIndex('litiges', 'idx_litiges_client_statut', ['client_id', 'statut_id']);
    Schema::ajouterIndex('litiges', 'idx_litiges_facture', ['numero_facture']);
    Schema::ajouterIndex('litiges', 'idx_litiges_ref_externe', ['reference_externe']);
    Schema::ajouterIndex('litiges', 'idx_litiges_ouverture', ['date_ouverture']);
    Schema::ajouterIndex('litiges', 'idx_litiges_echeance', ['date_echeance']);
    Schema::ajouterIndex('relances', 'idx_relances_litige_promesse', ['litige_id', 'date_promesse']);
    Schema::ajouterIndex('notifications', 'idx_notif_dedup', ['user_id', 'litige_id', 'type', 'created_at']);
    Schema::ajouterIndex('clients', 'idx_clients_siret', ['siret']);

    Schema::parametresParDefaut([
        '2fa_obligatoire'     => '0',     // 0 | admin | tous
        'clamav_actif'        => '0',
        'clamav_adresse'      => '',      // ex. unix:///var/run/clamav/clamd.ctl ou tcp://127.0.0.1:3310
        'clamav_bloquer_si_indisponible' => '1',
    ]);
};
