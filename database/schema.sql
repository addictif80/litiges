-- =====================================================================
--  Locavente - Gestion des litiges (location materiel BTP / vehicules)
--  Schema MySQL 8 / MariaDB 10.4+
--  Encodage : utf8mb4
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Agences / etablissements
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS agences (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom           VARCHAR(120)  NOT NULL,
    code          VARCHAR(30)   DEFAULT NULL,
    adresse       VARCHAR(255)  DEFAULT NULL,
    ville         VARCHAR(120)  DEFAULT NULL,
    telephone     VARCHAR(40)   DEFAULT NULL,
    email         VARCHAR(190)  DEFAULT NULL,
    actif         TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_agences_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Utilisateurs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username             VARCHAR(60)   NOT NULL,
    email                VARCHAR(190)  DEFAULT NULL,
    password_hash        VARCHAR(255)  NOT NULL,
    full_name            VARCHAR(150)  DEFAULT NULL,
    role                 ENUM('admin','gestionnaire','consultant','lecteur') NOT NULL DEFAULT 'gestionnaire',
    agence_id            INT UNSIGNED  DEFAULT NULL,
    actif                TINYINT(1)    NOT NULL DEFAULT 1,
    must_change_password TINYINT(1)    NOT NULL DEFAULT 0,
    failed_attempts      INT UNSIGNED  NOT NULL DEFAULT 0,
    locked_until         DATETIME      DEFAULT NULL,
    last_login_at        DATETIME      DEFAULT NULL,
    reset_token          VARCHAR(64)   DEFAULT NULL,
    reset_expires        DATETIME      DEFAULT NULL,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_users_username (username),
    UNIQUE KEY uk_users_email (email),
    KEY idx_users_agence (agence_id),
    CONSTRAINT fk_users_agence FOREIGN KEY (agence_id) REFERENCES agences (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Clients (entreprise cliente - alimente par l'API gouvernementale / Pappers)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    raison_sociale     VARCHAR(190) NOT NULL,
    forme_juridique    VARCHAR(120) DEFAULT NULL,
    siren              CHAR(9)      DEFAULT NULL,
    siret              CHAR(14)     DEFAULT NULL,
    tva_intra          VARCHAR(20)  DEFAULT NULL,
    ape_naf            VARCHAR(10)  DEFAULT NULL,
    libelle_ape        VARCHAR(190) DEFAULT NULL,
    adresse            VARCHAR(255) DEFAULT NULL,
    complement_adresse VARCHAR(255) DEFAULT NULL,
    code_postal        VARCHAR(12)  DEFAULT NULL,
    ville              VARCHAR(120) DEFAULT NULL,
    pays               VARCHAR(80)  DEFAULT 'France',
    effectif           VARCHAR(60)  DEFAULT NULL,
    date_creation      DATE         DEFAULT NULL,
    capital            DECIMAL(14,2) DEFAULT NULL,
    chiffre_affaires   DECIMAL(16,2) DEFAULT NULL,
    resultat_net       DECIMAL(16,2) DEFAULT NULL,
    annee_finances     SMALLINT     DEFAULT NULL,
    statut_entreprise  VARCHAR(80)  DEFAULT NULL,
    procedure_collective VARCHAR(190) DEFAULT NULL,
    score_solvabilite  VARCHAR(30)  DEFAULT NULL,
    ia_score           TINYINT      DEFAULT NULL,
    ia_decision        VARCHAR(30)  DEFAULT NULL,
    ia_analyse         LONGTEXT     DEFAULT NULL,
    ia_synced_at       DATETIME     DEFAULT NULL,
    source_api         VARCHAR(30)  DEFAULT NULL,
    raw_data           LONGTEXT     DEFAULT NULL,
    data_synced_at     DATETIME     DEFAULT NULL,
    notes              TEXT         DEFAULT NULL,
    actif              TINYINT(1)   NOT NULL DEFAULT 1,
    created_by         INT UNSIGNED DEFAULT NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_clients_siren (siren),
    KEY idx_clients_raison (raison_sociale),
    KEY idx_clients_ville (ville),
    CONSTRAINT fk_clients_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Contacts chez le client
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contacts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id  INT UNSIGNED NOT NULL,
    civilite   VARCHAR(20)  DEFAULT NULL,
    nom        VARCHAR(120) NOT NULL,
    prenom     VARCHAR(120) DEFAULT NULL,
    fonction   VARCHAR(120) DEFAULT NULL,
    email      VARCHAR(190) DEFAULT NULL,
    telephone  VARCHAR(40)  DEFAULT NULL,
    mobile     VARCHAR(40)  DEFAULT NULL,
    type       VARCHAR(60)  DEFAULT NULL,
    principal  TINYINT(1)   NOT NULL DEFAULT 0,
    notes      TEXT         DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contacts_client (client_id),
    CONSTRAINT fk_contacts_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Referentiel statuts & finalites (configurable par l'administrateur)
--   categorie = 'statut'   -> etape du litige
--   categorie = 'finalite' -> issue / resultat de cloture
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS litige_statuts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    categorie  ENUM('statut','finalite') NOT NULL DEFAULT 'statut',
    libelle    VARCHAR(80)  NOT NULL,
    code       VARCHAR(40)  NOT NULL,
    couleur    VARCHAR(20)  NOT NULL DEFAULT '#6c757d',
    ordre      INT          NOT NULL DEFAULT 0,
    cloturant  TINYINT(1)   NOT NULL DEFAULT 0,
    systeme    TINYINT(1)   NOT NULL DEFAULT 0,
    actif      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_statuts_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Litiges
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS litiges (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference          VARCHAR(30)  NOT NULL,
    client_id          INT UNSIGNED NOT NULL,
    agence_id          INT UNSIGNED DEFAULT NULL,
    objet              VARCHAR(190) NOT NULL,
    description        TEXT         DEFAULT NULL,
    type_litige        VARCHAR(60)  DEFAULT NULL,
    montant_principal  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    montant_penalites  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    montant_recupere   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    devise             CHAR(3)      NOT NULL DEFAULT 'EUR',
    date_ouverture     DATE         NOT NULL,
    date_echeance      DATE         DEFAULT NULL,
    date_cloture       DATE         DEFAULT NULL,
    statut_id          INT UNSIGNED NOT NULL,
    finalite_id        INT UNSIGNED DEFAULT NULL,
    responsable_id     INT UNSIGNED DEFAULT NULL,
    priorite           ENUM('basse','normale','haute','critique') NOT NULL DEFAULT 'normale',
    numero_facture     VARCHAR(60)  DEFAULT NULL,
    date_facture       DATE         DEFAULT NULL,
    numero_contrat     VARCHAR(60)  DEFAULT NULL,
    materiel           VARCHAR(190) DEFAULT NULL,
    reference_externe  VARCHAR(90)  DEFAULT NULL,
    prochaine_relance_at DATE      DEFAULT NULL,
    delai_relance_jours  INT UNSIGNED NOT NULL DEFAULT 15,
    derniere_relance_at  DATETIME  DEFAULT NULL,
    created_by         INT UNSIGNED DEFAULT NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_litiges_reference (reference),
    KEY idx_litiges_client (client_id),
    KEY idx_litiges_agence (agence_id),
    KEY idx_litiges_statut (statut_id),
    KEY idx_litiges_finalite (finalite_id),
    KEY idx_litiges_responsable (responsable_id),
    KEY idx_litiges_relance (prochaine_relance_at),
    CONSTRAINT fk_litiges_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT,
    CONSTRAINT fk_litiges_agence FOREIGN KEY (agence_id) REFERENCES agences (id) ON DELETE SET NULL,
    CONSTRAINT fk_litiges_statut FOREIGN KEY (statut_id) REFERENCES litige_statuts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_litiges_finalite FOREIGN KEY (finalite_id) REFERENCES litige_statuts (id) ON DELETE SET NULL,
    CONSTRAINT fk_litiges_responsable FOREIGN KEY (responsable_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_litiges_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Relances / contacts (historique des actions)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS relances (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    litige_id        INT UNSIGNED NOT NULL,
    contact_id       INT UNSIGNED DEFAULT NULL,
    type_action      ENUM('relance','appel','email','courrier','mise_en_demeure','promesse_paiement','echelonnement','visite','autre') NOT NULL DEFAULT 'relance',
    canal            ENUM('telephone','email','courrier','sms','physique','autre') NOT NULL DEFAULT 'telephone',
    date_action      DATETIME     NOT NULL,
    objet            VARCHAR(190) DEFAULT NULL,
    contenu          TEXT         DEFAULT NULL,
    resultat         VARCHAR(190) DEFAULT NULL,
    montant_promis   DECIMAL(12,2) DEFAULT NULL,
    date_promesse    DATE         DEFAULT NULL,
    prochaine_action_at DATE      DEFAULT NULL,
    created_by       INT UNSIGNED DEFAULT NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_relances_litige (litige_id),
    KEY idx_relances_date (date_action),
    KEY idx_relances_promesse (date_promesse),
    CONSTRAINT fk_relances_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE,
    CONSTRAINT fk_relances_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE SET NULL,
    CONSTRAINT fk_relances_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Notes internes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    litige_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED DEFAULT NULL,
    contenu    TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notes_litige (litige_id),
    CONSTRAINT fk_notes_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pieces jointes (stockees hors racine web, dans /storage/uploads)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    litige_id     INT UNSIGNED DEFAULT NULL,
    client_id     INT UNSIGNED DEFAULT NULL,
    categorie     VARCHAR(60)  DEFAULT NULL,
    nom_original  VARCHAR(255) NOT NULL,
    nom_stocke    VARCHAR(255) NOT NULL,
    mime          VARCHAR(120) DEFAULT NULL,
    taille        INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by   INT UNSIGNED DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_documents_litige (litige_id),
    KEY idx_documents_client (client_id),
    CONSTRAINT fk_documents_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Historique des modifications d'un litige (changelog)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS litige_historique (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    litige_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED DEFAULT NULL,
    action          VARCHAR(40)  NOT NULL,
    champ           VARCHAR(60)  DEFAULT NULL,
    ancienne_valeur VARCHAR(255) DEFAULT NULL,
    nouvelle_valeur VARCHAR(255) DEFAULT NULL,
    commentaire     TEXT         DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_histo_litige (litige_id),
    CONSTRAINT fk_histo_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE,
    CONSTRAINT fk_histo_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Journal d'audit global
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED DEFAULT NULL,
    username   VARCHAR(60)  DEFAULT NULL,
    action     VARCHAR(60)  NOT NULL,
    entite     VARCHAR(60)  DEFAULT NULL,
    entite_id  INT UNSIGNED DEFAULT NULL,
    ip         VARCHAR(45)  DEFAULT NULL,
    details    TEXT         DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_entite (entite, entite_id),
    KEY idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Notifications internes (rappels automatiques)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED DEFAULT NULL,
    litige_id  INT UNSIGNED DEFAULT NULL,
    type       VARCHAR(40)  NOT NULL,
    message    VARCHAR(255) NOT NULL,
    lien       VARCHAR(255) DEFAULT NULL,
    lu         TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id, lu),
    KEY idx_notif_litige (litige_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Partages en lecture seule (codes d'acces pour les agences)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS partages (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(32)  NOT NULL,
    token        VARCHAR(64)  DEFAULT NULL,
    libelle      VARCHAR(150) NOT NULL,
    scope        ENUM('global','agence','client','litige') NOT NULL DEFAULT 'agence',
    scope_id     INT UNSIGNED DEFAULT NULL,
    expires_at   DATETIME     DEFAULT NULL,
    actif        TINYINT(1)   NOT NULL DEFAULT 1,
    created_by   INT UNSIGNED DEFAULT NULL,
    last_used_at DATETIME     DEFAULT NULL,
    vues         INT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_partages_code (code),
    UNIQUE KEY uk_partages_token (token),
    KEY idx_partages_scope (scope, scope_id),
    CONSTRAINT fk_partages_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Notes sur la fiche client (visibles en lecture seule par les agences)
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

-- ---------------------------------------------------------------------
-- Messagerie interne : conversations (1-a-1, groupe, canal d'agence)
-- ---------------------------------------------------------------------
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

-- Participants d'une conversation (et suivi de lecture)
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

-- Messages d'une conversation
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
-- Parametres applicatifs (cle / valeur)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    cle     VARCHAR(80)  NOT NULL,
    valeur  TEXT         DEFAULT NULL,
    PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Migrations deja appliquees (suivi automatique, voir app/Core/Migrator.php)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS migrations (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration  VARCHAR(255) NOT NULL,
    checksum   CHAR(32)     DEFAULT NULL,
    applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_migrations_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  Donnees de reference
-- =====================================================================

INSERT INTO litige_statuts (categorie, libelle, code, couleur, ordre, cloturant, systeme) VALUES
    ('statut', 'Nouveau',                'nouveau',            '#0d6efd', 10, 0, 1),
    ('statut', 'En cours',               'en_cours',           '#fd7e14', 20, 0, 1),
    ('statut', 'Relance envoyee',        'relance_envoyee',    '#ffc107', 30, 0, 0),
    ('statut', 'Mise en demeure',        'mise_en_demeure',    '#dc3545', 40, 0, 0),
    ('statut', 'En recouvrement',        'en_recouvrement',    '#6f42c1', 50, 0, 0),
    ('statut', 'Procedure contentieuse', 'contentieux',        '#842029', 60, 0, 0),
    ('statut', 'Suspendu',               'suspendu',           '#6c757d', 70, 0, 0),
    ('statut', 'Cloture',                'cloture',            '#198754', 80, 1, 1)
ON DUPLICATE KEY UPDATE libelle = VALUES(libelle);

INSERT INTO litige_statuts (categorie, libelle, code, couleur, ordre, cloturant, systeme) VALUES
    ('finalite', 'Reglement total',        'solde_total',        '#198754', 10, 1, 0),
    ('finalite', 'Reglement partiel',      'solde_partiel',      '#20c997', 20, 1, 0),
    ('finalite', 'Echelonnement en cours', 'echelonnement',      '#0dcaf0', 30, 0, 0),
    ('finalite', 'Irrecouvrable',          'irrecouvrable',      '#dc3545', 40, 1, 0),
    ('finalite', 'Prescription',           'prescription',       '#6c757d', 50, 1, 0),
    ('finalite', 'Abandon / geste commercial', 'abandon',        '#adb5bd', 60, 1, 0),
    ('finalite', 'Procedure collective',   'procedure_collective','#842029', 70, 1, 0),
    ('finalite', 'Erreur de facturation',  'erreur_facturation', '#ffc107', 80, 1, 0)
ON DUPLICATE KEY UPDATE libelle = VALUES(libelle);

INSERT INTO settings (cle, valeur) VALUES
    ('app_nom',                    'Locavente - Gestion des litiges'),
    ('societe_nom',                'Locavente'),
    ('delai_relance_defaut_jours', '15'),
    ('seuil_alerte_retard_jours',  '7'),
    ('company_api_provider',       'auto'),
    ('pappers_api_token',          ''),
    ('recuperation_auto_active',   '1'),
    ('bodacc_actif',               '1'),
    ('score_interne_actif',        '1'),
    ('oneminai_enabled',           '0'),
    ('oneminai_api_key',           ''),
    ('oneminai_model',             'gpt-4o-mini'),
    ('smtp_host',                  ''),
    ('smtp_port',                  '587'),
    ('smtp_user',                  ''),
    ('smtp_pass',                  ''),
    ('smtp_secure',                'tls'),
    ('smtp_from',                  ''),
    ('smtp_from_nom',              'Locavente Litiges'),
    ('email_notifications',        '0'),
    ('verrouillage_tentatives',    '5'),
    ('verrouillage_minutes',       '15'),
    ('max_upload_mo',              '10'),
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
    ('perm_lecteur_chat',          '1'),
    ('install_date',               '')
ON DUPLICATE KEY UPDATE valeur = valeur;
