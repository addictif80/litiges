<?php
declare(strict_types=1);

/**
 * Installeur web. A SUPPRIMER apres l'installation.
 *
 * Etapes : verification de l'environnement, saisie des acces base de donnees
 * et du compte administrateur, creation du fichier config/config.php,
 * import du schema et creation de l'administrateur.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root         = dirname(__DIR__);
$configFile   = $root . '/config/config.php';
$configExample = $root . '/config/config.example.php';
$schemaFile   = $root . '/database/schema.sql';

session_start();

// --- Deja installe ? ---------------------------------------------------
$dejaInstalle = false;
if (is_file($configFile)) {
    try {
        $cfg = require $configFile;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['db']['host'] ?? '127.0.0.1', (int) ($cfg['db']['port'] ?? 3306), $cfg['db']['database'] ?? '');
        $test = new PDO($dsn, $cfg['db']['username'] ?? '', $cfg['db']['password'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $dejaInstalle = (int) $test->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (Throwable $e) {
        $dejaInstalle = false;
    }
}

// --- Traitement du formulaire -----------------------------------------
$erreurs = [];
$etape   = 'formulaire';
$recap   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$dejaInstalle) {
    $in = static fn (string $k, string $d = '') => trim((string) ($_POST[$k] ?? $d));

    $dbHost = $in('db_host', '127.0.0.1');
    $dbPort = (int) $in('db_port', '3306');
    $dbName = $in('db_name');
    $dbUser = $in('db_user');
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $appUrl = rtrim($in('app_url'), '/');
    $appNom = $in('app_nom', 'Locavente - Gestion des litiges');
    $adminUser = $in('admin_user', 'admin');
    $adminEmail = $in('admin_email');
    $adminNom  = $in('admin_nom', 'Administrateur');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');

    // Validations
    if ($dbName === '')  $erreurs[] = 'Le nom de la base de donnees est obligatoire.';
    if ($dbUser === '')  $erreurs[] = "L'utilisateur MySQL est obligatoire.";
    if ($adminUser === '' || strlen($adminUser) < 3) $erreurs[] = "L'identifiant administrateur doit contenir au moins 3 caracteres.";
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $erreurs[] = "L'email administrateur est invalide.";
    if (strlen($adminPass) < 10) $erreurs[] = 'Le mot de passe administrateur doit contenir au moins 10 caracteres.';
    if (!is_file($schemaFile)) $erreurs[] = 'Fichier database/schema.sql introuvable.';

    if ($erreurs === []) {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort),
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . str_replace('`', '', $dbName) . '`');

            // Import du schema
            $sql = (string) file_get_contents($schemaFile);
            executerSchema($pdo, $sql);

            // Applique les migrations (tables, colonnes et donnees de reference ajoutees
            // apres schema.sql). Elles sont idempotentes ; le Migrator les enregistre et
            // memorise l'etat (storage/migrations.state) pour ne pas les rejouer.
            if (!defined('APP_ROOT')) {
                define('APP_ROOT', $root);
            }
            spl_autoload_register(static function (string $classe) use ($root): void {
                if (str_starts_with($classe, 'App\\')) {
                    $fichier = $root . '/app/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
                    if (is_file($fichier)) {
                        require_once $fichier;
                    }
                }
            });
            if (!is_dir($root . '/storage/logs')) {
                @mkdir($root . '/storage/logs', 0775, true);
            }
            \App\Core\Database::init([
                'host' => $dbHost, 'port' => $dbPort, 'database' => $dbName,
                'username' => $dbUser, 'password' => $dbPass, 'charset' => 'utf8mb4',
            ]);
            $rapportMigrations = (new \App\Core\Migrator())->force();
            foreach ($rapportMigrations as $ligneMigration) {
                if (($ligneMigration['statut'] ?? '') === 'erreur') {
                    throw new RuntimeException('Migration ' . $ligneMigration['nom'] . ' : ' . ($ligneMigration['erreur'] ?? 'erreur inconnue'));
                }
            }

            // Creation de l'administrateur
            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (username, email, full_name, password_hash, role, actif)
                                   VALUES (:u, :e, :n, :p, :r, 1)
                                   ON DUPLICATE KEY UPDATE email = VALUES(email), password_hash = VALUES(password_hash), full_name = VALUES(full_name)');
            $stmt->execute(['u' => $adminUser, 'e' => $adminEmail, 'n' => $adminNom, 'p' => $hash, 'r' => 'admin']);

            // Date d'installation
            $pdo->prepare('INSERT INTO settings (cle, valeur) VALUES (:c, :v) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')
                ->execute(['c' => 'install_date', 'v' => date('Y-m-d H:i:s')]);
            $pdo->prepare('INSERT INTO settings (cle, valeur) VALUES (:c, :v) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')
                ->execute(['c' => 'app_nom', 'v' => $appNom]);

            // Ecriture du fichier de configuration
            if (is_file($configExample)) {
                $config = require $configExample;
            } else {
                $config = [];
            }
            $config['app']['name'] = $appNom;
            $config['app']['url']  = $appUrl !== '' ? $appUrl : detecterUrl();
            $config['app']['key']  = bin2hex(random_bytes(32));
            $config['app']['env']  = 'production';
            $config['app']['debug'] = false;
            $config['db'] = [
                'host' => $dbHost, 'port' => $dbPort, 'database' => $dbName,
                'username' => $dbUser, 'password' => $dbPass,
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            ];

            if (!is_dir($root . '/config')) {
                mkdir($root . '/config', 0775, true);
            }
            $contenu = "<?php\n\n/**\n * Configuration generee par l'installeur le " . date('d/m/Y H:i') . ".\n * Ce fichier est confidentiel : il ne doit pas etre accessible depuis le web.\n */\n\nreturn "
                . var_export($config, true) . ";\n";
            file_put_contents($configFile, $contenu);
            @chmod($configFile, 0640);

            // Repertoires de travail
            foreach (['storage', 'storage/uploads', 'storage/logs'] as $dir) {
                if (!is_dir($root . '/' . $dir)) {
                    @mkdir($root . '/' . $dir, 0775, true);
                }
            }

            $etape  = 'succes';
            $recap  = ['app_url' => $config['app']['url'], 'admin' => $adminUser];
        } catch (Throwable $e) {
            $erreurs[] = 'Erreur : ' . $e->getMessage();
        }
    }
}

/** Devine l'URL de base de l'application. */
function detecterUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . $dir;
}

/** Execute un script SQL decoupe en instructions (commentaires -- retires). */
function executerSchema(PDO $pdo, string $sql): void
{
    $lignes = preg_split('/\r\n|\r|\n/', $sql) ?: [];
    $propre = [];
    foreach ($lignes as $ligne) {
        if (preg_match('/^\s*--/', $ligne)) {
            continue;
        }
        $propre[] = $ligne;
    }
    $instructions = explode(';', implode("\n", $propre));
    foreach ($instructions as $instruction) {
        $instruction = trim($instruction);
        if ($instruction !== '') {
            $pdo->exec($instruction);
        }
    }
}

/** Verifications de l'environnement. */
$checks = [
    'PHP >= 8.1'          => version_compare(PHP_VERSION, '8.1.0', '>='),
    'Extension PDO'       => extension_loaded('pdo'),
    'Pilote pdo_mysql'    => extension_loaded('pdo_mysql'),
    'Extension json'      => extension_loaded('json'),
    'Extension mbstring'  => extension_loaded('mbstring'),
    'Extension openssl'   => extension_loaded('openssl'),
    'config/ accessible en ecriture' => is_writable($root . '/config') || !is_dir($root . '/config'),
];
$envOk = !in_array(false, $checks, true);

$valeurs = static fn (string $k, string $d = '') => htmlspecialchars((string) ($_POST[$k] ?? $d), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation — Gestion des litiges</title>
    <style>
        :root { --bleu:#12467b; --bleu-clair:#1d6fb8; --gris:#5b6b7c; --fond:#eef2f7; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:var(--fond); color:#1f2933; padding:32px 16px; }
        .boite { max-width: 760px; margin: auto; background:#fff; border-radius:14px; box-shadow:0 10px 30px rgba(18,70,123,.12); overflow:hidden; }
        .entete { background:linear-gradient(135deg, var(--bleu), var(--bleu-clair)); color:#fff; padding:26px 30px; }
        .entete h1 { margin:0 0 4px; font-size:22px; }
        .entete p { margin:0; opacity:.85; font-size:14px; }
        .corps { padding:26px 30px 34px; }
        h2 { font-size:16px; color:var(--bleu); border-bottom:1px solid #e3e8ef; padding-bottom:8px; margin:26px 0 14px; }
        h2:first-child { margin-top:0; }
        label { display:block; font-size:13px; font-weight:600; margin:12px 0 4px; color:#374151; }
        input[type=text], input[type=password], input[type=email], input[type=number] { width:100%; padding:10px 12px; border:1px solid #cfd8e3; border-radius:8px; font-size:14px; }
        input:focus { outline:none; border-color:var(--bleu-clair); box-shadow:0 0 0 3px rgba(29,111,184,.15); }
        .grille { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .grille-3 { display:grid; grid-template-columns:2fr 1fr; gap:14px; }
        .aide { font-size:12px; color:var(--gris); margin-top:4px; }
        button { margin-top:24px; width:100%; background:var(--bleu); color:#fff; border:0; padding:13px; border-radius:9px; font-size:15px; font-weight:600; cursor:pointer; }
        button:hover { background:var(--bleu-clair); }
        .liste { list-style:none; padding:0; margin:0; }
        .liste li { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eef2f7; font-size:14px; }
        .ok { color:#198754; font-weight:700; }
        .ko { color:#dc3545; font-weight:700; }
        .alerte { padding:12px 16px; border-radius:9px; font-size:14px; margin-bottom:16px; }
        .alerte-err { background:#fdecec; color:#8a1c1c; border:1px solid #f5c2c2; }
        .alerte-ok  { background:#e8f6ee; color:#14653f; border:1px solid #b7e2c9; }
        .alerte-warn{ background:#fff7e6; color:#8a5a00; border:1px solid #ffe0a3; }
        code { background:#f1f5f9; padding:2px 6px; border-radius:5px; font-size:13px; }
        a { color:var(--bleu-clair); }
    </style>
</head>
<body>
<div class="boite">
    <div class="entete">
        <h1>Gestion des litiges — Installation</h1>
        <p>Location de materiel BTP, vehicules et machines de chantier</p>
    </div>
    <div class="corps">

    <?php if ($dejaInstalle): ?>
        <div class="alerte alerte-warn">
            <strong>Application deja installee.</strong><br>
            Pour des raisons de securite, l'installeur ne peut pas etre reexecute.
            Merci de supprimer <code>public/install.php</code>.
        </div>
        <p><a href="index.php">&rarr; Acceder a l'application</a></p>

    <?php elseif ($etape === 'succes'): ?>
        <div class="alerte alerte-ok">
            <strong>Installation terminee avec succes !</strong>
        </div>
        <h2>A faire maintenant</h2>
        <ol style="font-size:14px;line-height:1.8">
            <li><strong>Supprimez</strong> le fichier <code>public/install.php</code>.</li>
            <li>Verifiez que <code>config/config.php</code> n'est pas accessible depuis le navigateur.</li>
            <li>Configurez une tache <em>cron</em> quotidienne : <code>php <?= htmlspecialchars($root) ?>/cron/rappels.php</code></li>
            <li>Connectez-vous avec l'identifiant <strong><?= htmlspecialchars((string) ($recap['admin'] ?? '')) ?></strong>.</li>
        </ol>
        <p style="margin-top:24px"><a href="index.php">&rarr; Acceder a l'application</a></p>

    <?php else: ?>
        <?php foreach ($erreurs as $e): ?>
            <div class="alerte alerte-err"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <h2>1. Verification de l'environnement</h2>
        <ul class="liste">
            <?php foreach ($checks as $libelle => $ok): ?>
                <li><span><?= htmlspecialchars($libelle) ?></span>
                    <span class="<?= $ok ? 'ok' : 'ko' ?>"><?= $ok ? 'OK' : 'MANQUANT' ?></span></li>
            <?php endforeach; ?>
        </ul>

        <form method="post" autocomplete="off">
            <h2>2. Base de donnees MySQL</h2>
            <div class="grille-3">
                <div>
                    <label for="db_host">Serveur</label>
                    <input type="text" id="db_host" name="db_host" value="<?= $valeurs('db_host', '127.0.0.1') ?>" required>
                </div>
                <div>
                    <label for="db_port">Port</label>
                    <input type="number" id="db_port" name="db_port" value="<?= $valeurs('db_port', '3306') ?>" required>
                </div>
            </div>
            <label for="db_name">Nom de la base (elle sera creee si absente)</label>
            <input type="text" id="db_name" name="db_name" value="<?= $valeurs('db_name', 'locavente_litiges') ?>" required>
            <label for="db_user">Utilisateur MySQL</label>
            <input type="text" id="db_user" name="db_user" value="<?= $valeurs('db_user') ?>" required>
            <label for="db_pass">Mot de passe MySQL</label>
            <input type="password" id="db_pass" name="db_pass" value="">

            <h2>3. Application</h2>
            <label for="app_nom">Nom de l'application</label>
            <input type="text" id="app_nom" name="app_nom" value="<?= $valeurs('app_nom', 'Locavente - Gestion des litiges') ?>">
            <label for="app_url">URL de base</label>
            <input type="text" id="app_url" name="app_url" value="<?= $valeurs('app_url', detecterUrl()) ?>">
            <div class="aide">Adresse publique de l'application, sans slash final.</div>

            <h2>4. Compte administrateur</h2>
            <div class="grille">
                <div>
                    <label for="admin_user">Identifiant</label>
                    <input type="text" id="admin_user" name="admin_user" value="<?= $valeurs('admin_user', 'admin') ?>" required>
                </div>
                <div>
                    <label for="admin_nom">Nom complet</label>
                    <input type="text" id="admin_nom" name="admin_nom" value="<?= $valeurs('admin_nom', 'Administrateur') ?>">
                </div>
            </div>
            <label for="admin_email">Email</label>
            <input type="email" id="admin_email" name="admin_email" value="<?= $valeurs('admin_email') ?>" required>
            <label for="admin_pass">Mot de passe (10 caracteres minimum)</label>
            <input type="password" id="admin_pass" name="admin_pass" required>

            <button type="submit" <?= $envOk ? '' : 'disabled' ?>>Installer l'application</button>
        </form>
    <?php endif; ?>
    </div>
</div>
</body>
</html>
