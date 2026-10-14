<?php
/**
 * Configuration de l'application.
 *
 * Copiez ce fichier en "config.php" puis renseignez vos identifiants.
 * Le fichier config.php ne doit JAMAIS etre versionne ni accessible depuis le web.
 */

return [
    // --- Application ---------------------------------------------------
    'app' => [
        'name'      => 'Locavente - Gestion des litiges',
        'env'       => 'production',            // production | dev
        'debug'     => false,
        'url'       => 'https://litiges.mondomaine.fr', // URL de base SANS slash final
        'timezone'  => 'Europe/Paris',
        'locale'    => 'fr_FR',
        'key'       => 'CHANGEZ-MOI-chaine-aleatoire-64-caracteres',
    ],

    // --- Base de donnees ----------------------------------------------
    'db' => [
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'database'  => 'locavente_litiges',
        'username'  => 'locavente',
        'password'  => '',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // --- Chemins -------------------------------------------------------
    'paths' => [
        'storage'   => dirname(__DIR__) . '/storage',
        'uploads'   => dirname(__DIR__) . '/storage/uploads',
        'logs'      => dirname(__DIR__) . '/storage/logs',
    ],

    // --- Uploads -------------------------------------------------------
    'uploads' => [
        'max_size'   => 10 * 1024 * 1024, // 10 Mo
        'extensions' => ['pdf','jpg','jpeg','png','gif','webp','doc','docx','xls','xlsx','csv','txt','zip','eml','msg'],
    ],

    // --- Securite ------------------------------------------------------
    'security' => [
        'session_name'        => 'locavente_litiges_sid',
        'session_lifetime'    => 60 * 60 * 4, // 4 h d'inactivite
        'max_login_attempts'  => 5,
        'lockout_minutes'     => 15,
        'password_min_length' => 10,
        'force_https'         => true,
    ],

    // --- APIs entreprises ---------------------------------------------
    // La source gratuite de l'Etat est utilisee par defaut (aucune cle requise).
    // Pappers est utilise automatiquement si un jeton est renseigne (global ou par utilisateur).
    'apis' => [
        'recherche_entreprises' => [
            'base_url' => 'https://recherche-entreprises.api.gouv.fr',
            'timeout'  => 8,
            'actif'    => true,
        ],
        // BODACC : annonces legales (procedures collectives, radiations...).
        // Donnees ouvertes, gratuites et sans cle.
        'bodacc' => [
            'base_url' => 'https://bodacc-datadila.opendatasoft.com',
            'dataset'  => 'annonces-commerciales',
            'timeout'  => 8,
            'actif'    => true,   // surcharge possible via les Parametres (bodacc_actif)
        ],
        'pappers' => [
            'base_url' => 'https://api.pappers.fr/v2',
            'api_token' => '',   // jeton Pappers (peut etre surcharge dans les Parametres)
            'timeout'  => 8,
            'actif'    => false,
        ],
    ],

    // --- Courriel (SMTP optionnel) ------------------------------------
    'mail' => [
        'enabled'   => false,
        'host'      => '',
        'port'      => 587,
        'username'  => '',
        'password'  => '',
        'secure'    => 'tls',  // tls | ssl | ''
        'from'      => 'litiges@mondomaine.fr',
        'from_name' => 'Locavente Litiges',
    ],
];
