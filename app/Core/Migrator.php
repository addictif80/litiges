<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gestionnaire de migrations de base de donnees.
 *
 * A chaque chargement de page, les fichiers presents dans
 * database/migrations/ qui n'ont pas encore ete joues sont executes puis
 * enregistres dans la table `migrations`. Un petit fichier d'etat
 * (storage/migrations.state) evite toute requete SQL lorsque rien n'a change.
 *
 * Conventions d'ecriture d'une migration :
 *   - un fichier = une evolution, nomme `AAAA-MM-JJ-description.sql` ;
 *   - le SQL est decoupe sur les points-virgules (chaines et commentaires
 *     geres) : NE PAS utiliser DELIMITER ni de procedures stockees ;
 *   - une migration doit pouvoir etre relancee sans dommage (idempotente),
 *     car elle peut etre rejouee apres une erreur partielle ;
 *   - ne jamais modifier une migration deja appliquee : en creer une nouvelle.
 */
class Migrator
{
    public const TABLE = 'migrations';

    private string $dir;
    private string $stateFile;

    /** @var array<string,array<string,mixed>> */
    private array $applied        = [];
    private bool  $appliedLoaded  = false;

    public function __construct(?string $dir = null)
    {
        $this->dir       = $dir ?? (APP_ROOT . '/database/migrations');
        $this->stateFile = APP_ROOT . '/storage/migrations.state';
    }

    /**
     * Point d'entree appele a chaque chargement de page : applique les
     * migrations en attente sans jamais faire echouer l'application.
     */
    public static function autoRun(): void
    {
        // Les deploiements qui preferent lancer "php cron/migrate.php" peuvent
        // desactiver l'application automatique : app.auto_migrate = false.
        if (!(bool) Config::get('app.auto_migrate', true)) {
            return;
        }
        try {
            $migrator = new self();
            if ($migrator->isUpToDate()) {
                return;
            }
            $migrator->run();
        } catch (\Throwable $e) {
            self::logError($e);
        }
    }

    /** @return string[] Chemins complets des fichiers de migration, tries. */
    public function files(): array
    {
        if (!is_dir($this->dir)) {
            return [];
        }
        $files = array_merge(glob($this->dir . '/*.sql') ?: [], glob($this->dir . '/*.php') ?: []);
        sort($files, SORT_STRING);
        return $files;
    }

    /** Des migrations sont-elles en attente d'application (sans requete SQL) ? */
    public function enAttenteRapide(): bool
    {
        return !$this->isUpToDate();
    }

    /**
     * Rien a faire ? On compare l'empreinte des fichiers a celle mémorisée
     * lors de la derniere execution. Aucune requete SQL n'est faite ici.
     */
    public function isUpToDate(): bool
    {
        $fingerprint = $this->fingerprint();
        if ($fingerprint === null) {
            return true;
        }
        $state = @file_get_contents($this->stateFile);
        return is_string($state) && trim($state) === $fingerprint;
    }

    /**
     * Execute les migrations non encore appliquees.
     *
     * @return array<int,array{nom:string,statut:string,erreur?:string}>
     */
    public function run(): array
    {
        $this->ensureTable();

        if (!$this->acquireLock()) {
            return []; // une autre requete est deja en train de migrer
        }

        $rapport = [];
        try {
            $applied = $this->appliedMap();
            foreach ($this->files() as $file) {
                $nom = basename($file);
                if (isset($applied[$nom])) {
                    continue;
                }

                $sql = (string) @file_get_contents($file);
                try {
                    if (str_ends_with($file, '.php')) {
                        $this->executerPhp($file);
                    } else {
                        $this->execute($sql);
                    }
                    $this->markApplied($nom, md5($sql));
                    $this->forgetError($nom);
                    $rapport[] = ['nom' => $nom, 'statut' => 'appliquee'];
                    Audit::log('migration_appliquee', 'migration', null, ['fichier' => $nom]);
                } catch (\Throwable $e) {
                    $rapport[] = ['nom' => $nom, 'statut' => 'erreur', 'erreur' => $e->getMessage()];
                    self::logError($e, $nom);
                    break; // on s'arrete : les suivantes peuvent en dependre
                }
            }
        } finally {
            $this->writeState();
            $this->releaseLock();
        }

        return $rapport;
    }

    /**
     * Etat de chaque fichier de migration (pour la page d'administration).
     *
     * @return array<int,array{migration:string,applied:bool,applied_at:?string,taille:int,erreur:?string}>
     */
    public function status(): array
    {
        $this->ensureTable();
        $applied = $this->appliedMap();
        $derriere = $this->lastErrors();

        $lignes = [];
        foreach ($this->files() as $file) {
            $nom = basename($file);
            $lignes[] = [
                'migration'  => $nom,
                'applied'    => isset($applied[$nom]),
                'applied_at' => isset($applied[$nom]) ? (string) $applied[$nom]['applied_at'] : null,
                'taille'     => (int) @filesize($file),
                'erreur'     => $derriere[$nom] ?? null,
            ];
        }
        return $lignes;
    }

    public function pendingCount(): int
    {
        $this->ensureTable();
        $applied = $this->appliedMap();
        $n = 0;
        foreach ($this->files() as $file) {
            if (!isset($applied[basename($file)])) {
                $n++;
            }
        }
        return $n;
    }

    /** Force la reprise : oublie le cache pour re-tenter au prochain appel. */
    public function force(): array
    {
        @unlink($this->stateFile);
        return $this->run();
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    public function ensureTable(): void
    {
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration`  VARCHAR(255) NOT NULL,
                `checksum`   CHAR(32)     DEFAULT NULL,
                `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_migrations_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string,array<string,mixed>> */
    private function appliedMap(): array
    {
        if (!$this->appliedLoaded) {
            $rows          = Database::fetchAll('SELECT `migration`, `checksum`, `applied_at` FROM `' . self::TABLE . '`');
            $this->applied = [];
            foreach ($rows as $row) {
                $this->applied[(string) $row['migration']] = $row;
            }
            $this->appliedLoaded = true;
        }
        return $this->applied;
    }

    private function markApplied(string $nom, string $checksum): void
    {
        Database::run(
            'INSERT INTO `' . self::TABLE . '` (`migration`, `checksum`) VALUES (:m, :c)
             ON DUPLICATE KEY UPDATE `checksum` = VALUES(`checksum`)',
            ['m' => $nom, 'c' => $checksum]
        );
        $this->appliedLoaded = false;
    }

    /**
     * Migration PHP : le fichier retourne une fonction (PDO $pdo): void.
     * Utile pour les evolutions conditionnelles (voir App\Core\Schema).
     */
    private function executerPhp(string $file): void
    {
        $migration = require $file;
        if (!is_callable($migration)) {
            throw new \RuntimeException(basename($file) . ' doit retourner une fonction.');
        }
        $migration(Database::pdo());
    }

    private function execute(string $sql): void
    {
        // Les migrations ne contiennent pas de parametres lies : on execute
        // directement (evite toute interpretation des ":" par PDO).
        $pdo = Database::pdo();
        foreach ($this->splitStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * Decoupe un script SQL en instructions, en respectant les chaines de
     * caracteres, les identifiants entre accents graves et les commentaires
     * SQL (ligne et bloc).
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer     = '';
        $len        = strlen($sql);

        $inSimple = $inDouble = $inBacktick = false;
        $inLineComment = $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch   = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($ch === "\n") {
                    $inLineComment = false;
                    $buffer .= "\n";
                }
                continue;
            }

            if ($inBlockComment) {
                if ($ch === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if ($inSimple || $inDouble || $inBacktick) {
                $buffer .= $ch;
                if ($ch === '\\' && ($inSimple || $inDouble)) {
                    if ($next !== '') {
                        $buffer .= $next;
                        $i++;
                    }
                    continue;
                }
                if ($inSimple && $ch === "'") {
                    if ($next === "'") { $buffer .= $next; $i++; continue; }
                    $inSimple = false;
                } elseif ($inDouble && $ch === '"') {
                    if ($next === '"') { $buffer .= $next; $i++; continue; }
                    $inDouble = false;
                } elseif ($inBacktick && $ch === '`') {
                    if ($next === '`') { $buffer .= $next; $i++; continue; }
                    $inBacktick = false;
                }
                continue;
            }

            // Hors chaine / commentaire.
            if ($ch === '-' && $next === '-' && ($i + 2 >= $len || str_contains(" \t\r\n", $sql[$i + 2]))) {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($ch === '#') {
                $inLineComment = true;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
            if ($ch === "'") { $inSimple = true;   $buffer .= $ch; continue; }
            if ($ch === '"') { $inDouble = true;   $buffer .= $ch; continue; }
            if ($ch === '`') { $inBacktick = true; $buffer .= $ch; continue; }
            if ($ch === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    private function fingerprint(): ?string
    {
        $files = $this->files();
        if ($files === []) {
            return null;
        }
        $parts = [];
        foreach ($files as $file) {
            $parts[] = basename($file) . ':' . (int) @filesize($file) . ':' . (int) @filemtime($file);
        }
        return md5(implode('|', $parts));
    }

    private function writeState(): void
    {
        $fingerprint = $this->fingerprint();
        if ($fingerprint === null) {
            @unlink($this->stateFile);
            return;
        }
        @file_put_contents($this->stateFile, $fingerprint);
    }

    private function acquireLock(): bool
    {
        try {
            return (int) Database::value("SELECT GET_LOCK('litiges_migrations', 10)") === 1;
        } catch (\Throwable) {
            return true; // pas de verrou disponible : on continue quand meme
        }
    }

    private function releaseLock(): void
    {
        try {
            Database::run("SELECT RELEASE_LOCK('litiges_migrations')");
        } catch (\Throwable) {
            // ignore
        }
    }

    /** @return array<string,string> Dernieres erreurs connues, par fichier. */
    private function lastErrors(): array
    {
        $file = $this->errorsFile();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function errorsFile(): string
    {
        return APP_ROOT . '/storage/logs/migrations.json';
    }

    /** Oublie l'erreur memorisee pour une migration desormais appliquee. */
    private function forgetError(string $nom): void
    {
        $json = $this->errorsFile();
        if (!is_file($json)) {
            return;
        }
        $data = json_decode((string) @file_get_contents($json), true);
        if (!is_array($data) || !isset($data[$nom])) {
            return;
        }
        unset($data[$nom]);
        if ($data === []) {
            @unlink($json);
        } else {
            @file_put_contents($json, (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    private static function logError(\Throwable $e, ?string $migration = null): void
    {
        try {
            Audit::log('migration_erreur', 'migration', null, [
                'fichier' => $migration,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
            // ignore
        }

        try {
            $dir = APP_ROOT . '/storage/logs';
            if (is_dir($dir)) {
                @file_put_contents(
                    $dir . '/migrations.log',
                    date('c') . ' ' . ($migration !== null ? "[{$migration}] " : '') . $e->getMessage() . "\n",
                    FILE_APPEND
                );
                if ($migration !== null) {
                    $json = $dir . '/migrations.json';
                    $data = is_file($json) ? json_decode((string) @file_get_contents($json), true) : [];
                    $data = is_array($data) ? $data : [];
                    $data[$migration] = $e->getMessage();
                    @file_put_contents($json, (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
            }
        } catch (\Throwable) {
            // ignore
        }
    }
}
