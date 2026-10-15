-- =====================================================================
--  Migration - Roles/permissions, messagerie, notes client, partage par
--  lien et analyse IA (1min.ai).
--
--  Cette migration est appliquee AUTOMATIQUEMENT au chargement des pages
--  par app/Core/Migrator.php (aucune action necessaire apres un git pull).
--  Elle peut aussi etre lancee manuellement depuis l'administration
--  (Administration > Base de donnees > Appliquer maintenant).
--
--  IMPORTANT : ce script n'utilise NI procedures stockees NI commande
--  DELIMITER. Il est donc compatible phpMyAdmin et ne necessite pas le
--  privilege CREATE ROUTINE (souvent absent en mutualise). Il est
--  idempotent : il peut etre relance sans erreur.
--
--  Pour une installation neuve, database/schema.sql suffit.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Role "consultant"
-- ---------------------------------------------------------------------
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','gestionnaire','consultant','lecteur') NOT NULL DEFAULT 'gestionnaire';

-- Portee "litige" pour les partages par lien (MODIFY est idempotent).
ALTER TABLE partages
    MODIFY COLUMN scope ENUM('global','agence','client','litige') NOT NULL DEFAULT 'agence';

-- ---------------------------------------------------------------------
-- 2. Colonnes ajoutees (idempotent, sans procedure stockee)
--    La commande ALTER n'est executee que si la colonne est absente.
-- ---------------------------------------------------------------------

-- clients.ia_score
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'ia_score') = 0,
    'ALTER TABLE `clients` ADD COLUMN `ia_score` TINYINT DEFAULT NULL AFTER `score_solvabilite`',
    'SET @_litiges_noop = 1');
PREPARE _litiges_stmt FROM @sql;
EXECUTE _litiges_stmt;
DEALLOCATE PREPARE _litiges_stmt;

-- clients.ia_decision
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'ia_decision') = 0,
    'ALTER TABLE `clients` ADD COLUMN `ia_decision` VARCHAR(30) DEFAULT NULL AFTER `ia_score`',
    'SET @_litiges_noop = 1');
PREPARE _litiges_stmt FROM @sql;
EXECUTE _litiges_stmt;
DEALLOCATE PREPARE _litiges_stmt;

-- clients.ia_analyse
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'ia_analyse') = 0,
    'ALTER TABLE `clients` ADD COLUMN `ia_analyse` LONGTEXT DEFAULT NULL AFTER `ia_decision`',
    'SET @_litiges_noop = 1');
PREPARE _litiges_stmt FROM @sql;
EXECUTE _litiges_stmt;
DEALLOCATE PREPARE _litiges_stmt;

-- clients.ia_synced_at
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'ia_synced_at') = 0,
    'ALTER TABLE `clients` ADD COLUMN `ia_synced_at` DATETIME DEFAULT NULL AFTER `ia_analyse`',
    'SET @_litiges_noop = 1');
PREPARE _litiges_stmt FROM @sql;
EXECUTE _litiges_stmt;
DEALLOCATE PREPARE _litiges_stmt;

-- partages.token
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partages' AND COLUMN_NAME = 'token') = 0,
    'ALTER TABLE `partages` ADD COLUMN `token` VARCHAR(64) DEFAULT NULL AFTER `code`',
    'SET @_litiges_noop = 1');
PREPARE _litiges_stmt FROM @sql;
EXECUTE _litiges_stmt;
DEALLOCATE PREPARE _litiges_stmt;

-- notifications.lien (cible du clic : client, litige, message...)
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'lien') = 0,
    'ALTER TABLE `notifications` ADD COLUMN `lien` VARCHAR(255) DEFAULT NULL AFTER `message`',
    'SET @_litiges_noop = 1');
PREPARE _litiges_stmt FROM @sql;
EXECUTE _litiges_stmt;
DEALLOCATE PREPARE _litiges_stmt;

-- Index unique sur le jeton de partage (ajoute seulement s'il manque).
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partages' AND INDEX_NAME = 'uk_partages_token') = 0,
    'ALTER TABLE `partages` ADD UNIQUE KEY `uk_partages_token` (`token`)',
    'SET @_litiges_noop = 1');
PREPARE _litiges_stmt FROM @sql;
EXECUTE _litiges_stmt;
DEALLOCATE PREPARE _litiges_stmt;

-- ---------------------------------------------------------------------
-- 3. Nouvelles tables
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS client_notes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED DEFAULT NULL,
    contenu    TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client_notes_client (client_id),
    CONSTRAINT fk_client_notes_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_client_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chats (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type       ENUM('direct','groupe','agence') NOT NULL DEFAULT 'direct',
    titre      VARCHAR(150) DEFAULT NULL,
    agence_id  INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chats_type (type),
    KEY idx_chats_agence (agence_id),
    CONSTRAINT fk_chats_agence FOREIGN KEY (agence_id) REFERENCES agences (id) ON DELETE CASCADE,
    CONSTRAINT fk_chats_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_membres (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    chat_id      INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    last_read_at DATETIME     DEFAULT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_chat_membres (chat_id, user_id),
    KEY idx_chat_membres_user (user_id),
    CONSTRAINT fk_chat_membres_chat FOREIGN KEY (chat_id) REFERENCES chats (id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_membres_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    chat_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED DEFAULT NULL,
    contenu    TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_messages_chat (chat_id, id),
    CONSTRAINT fk_chat_messages_chat FOREIGN KEY (chat_id) REFERENCES chats (id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Parametres : permissions par role + analyse IA
-- ---------------------------------------------------------------------
INSERT INTO settings (cle, valeur) VALUES
    ('oneminai_enabled',           '0'),
    ('oneminai_api_key',           ''),
    ('oneminai_model',             'gpt-4o-mini'),
    ('perm_gestionnaire_litiges',  '1'),
    ('perm_gestionnaire_clients',  '1'),
    ('perm_gestionnaire_relances', '1'),
    ('perm_gestionnaire_chat',     '1'),
    ('perm_consultant_litiges',    '1'),
    ('perm_consultant_clients',    '1'),
    ('perm_consultant_relances',   '0'),
    ('perm_consultant_chat',       '1'),
    ('perm_lecteur_litiges',       '1'),
    ('perm_lecteur_clients',       '1'),
    ('perm_lecteur_relances',      '0'),
    ('perm_lecteur_chat',          '1')
ON DUPLICATE KEY UPDATE valeur = valeur;
