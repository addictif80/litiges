-- =====================================================================
--  Migration - Roles/permissions, messagerie, notes client, partage par
--  lien et analyse IA (1min.ai).
--
--  A executer UNE FOIS sur une installation existante, avec un client
--  MySQL/MariaDB (mysql, phpMyAdmin...). Le fichier est idempotent :
--  il peut etre relance sans erreur.
--
--  Pour une installation neuve, database/schema.sql suffit.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Ajout du role "consultant"
-- ---------------------------------------------------------------------
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','gestionnaire','consultant','lecteur') NOT NULL DEFAULT 'gestionnaire';

-- Portee "litige" pour les partages par lien (MODIFY est idempotent).
ALTER TABLE partages
    MODIFY COLUMN scope ENUM('global','agence','client','litige') NOT NULL DEFAULT 'agence';

-- ---------------------------------------------------------------------
-- 2. Colonnes ajoutees (fichier clients + jeton de partage)
--    Via une procedure pour rester idempotent (MySQL 8 et MariaDB).
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS _litiges_add_col;

DELIMITER $$
CREATE PROCEDURE _litiges_add_col(IN tbl VARCHAR(64), IN col VARCHAR(64), IN def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', def);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END$$
DELIMITER ;

CALL _litiges_add_col('clients',  'ia_score',     'TINYINT DEFAULT NULL AFTER `score_solvabilite`');
CALL _litiges_add_col('clients',  'ia_decision',  'VARCHAR(30) DEFAULT NULL AFTER `ia_score`');
CALL _litiges_add_col('clients',  'ia_analyse',   'LONGTEXT DEFAULT NULL AFTER `ia_decision`');
CALL _litiges_add_col('clients',  'ia_synced_at', 'DATETIME DEFAULT NULL AFTER `ia_analyse`');
CALL _litiges_add_col('partages', 'token',        'VARCHAR(64) DEFAULT NULL AFTER `code`');

-- Lien cible des notifications (client, litige, message...).
CALL _litiges_add_col('notifications', 'lien', 'VARCHAR(255) DEFAULT NULL AFTER `message`');

DROP PROCEDURE IF EXISTS _litiges_add_col;

-- Index unique sur le jeton de partage (ignore si deja present).
DROP PROCEDURE IF EXISTS _litiges_add_idx;
DELIMITER $$
CREATE PROCEDURE _litiges_add_idx(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = idx
    ) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD ', def);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END$$
DELIMITER ;

CALL _litiges_add_idx('partages', 'uk_partages_token', 'UNIQUE KEY `uk_partages_token` (`token`)');

DROP PROCEDURE IF EXISTS _litiges_add_idx;

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
