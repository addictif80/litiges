<?php
/**
 * Gabarit principal de l'application.
 *
 * @var string $content
 * @var string $titre
 * @var array|null $authUser
 * @var array $flash
 */
$titre = $titre ?? '';
$authUser = $authUser ?? null;
$flash = $flash ?? [];

// Compteurs affiches dans la barre de navigation.
$nbNotifications = 0;
$nbRelancesDues  = 0;
try {
    if ($authUser) {
        $nbNotifications = (new \App\Models\Notification())->countNonLues((int) $authUser['id']);
        $nbRelancesDues  = (int) \App\Core\Database::value(
            "SELECT COUNT(*) FROM litiges l JOIN litige_statuts s ON s.id = l.statut_id
             WHERE s.cloturant = 0 AND l.prochaine_relance_at IS NOT NULL AND l.prochaine_relance_at <= CURDATE()",
            [],
            0
        );
    }
} catch (\Throwable $e) {
    // Pas bloquant pour l'affichage.
}

$estAdmin = ($authUser['role'] ?? '') === 'admin';
$peutEcrire = in_array($authUser['role'] ?? '', ['admin', 'gestionnaire'], true);
$icones = [
    'dashboard' => '▦', 'litiges' => '⚖', 'clients' => '🏢', 'relances' => '🔔',
    'users' => '👥', 'agences' => '🏬', 'partages' => '🔗', 'statuts' => '⚙',
    'parametres' => '🛠', 'audit' => '📋', 'profil' => '👤',
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre) ?> · <?= e($appName ?? 'Gestion des litiges') ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚖️</text></svg>">
</head>
<body>
<div class="app">

    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <strong>⚖️ <?= e($appName ?? 'Gestion des litiges') ?></strong>
            <span>Location BTP &amp; véhicules</span>
        </div>
        <nav class="nav">
            <a href="<?= url('/') ?>" class="<?= $currentPath === '/' ? 'is-active' : '' ?>">
                <span class="ic"><?= $icones['dashboard'] ?></span> Tableau de bord
            </a>
            <a href="<?= url('/litiges') ?>" class="<?= is_active_menu('/litiges') ?>">
                <span class="ic"><?= $icones['litiges'] ?></span> Litiges
                <?php if ($nbRelancesDues > 0): ?><span class="badge-nb"><?= (int) $nbRelancesDues ?></span><?php endif; ?>
            </a>
            <a href="<?= url('/clients') ?>" class="<?= is_active_menu('/clients') ?>">
                <span class="ic"><?= $icones['clients'] ?></span> Clients
            </a>
            <?php if ($peutEcrire): ?>
                <a href="<?= url('/litiges?relance_due=1&etat=ouverts') ?>" class="<?= is_active_menu('/relances') ?>">
                    <span class="ic"><?= $icones['relances'] ?></span> Relances à faire
                    <?php if ($nbRelancesDues > 0): ?><span class="badge-nb"><?= (int) $nbRelancesDues ?></span><?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if ($estAdmin): ?>
                <div class="nav-section">Administration</div>
                <a href="<?= url('/utilisateurs') ?>" class="<?= is_active_menu('/utilisateurs') ?>">
                    <span class="ic"><?= $icones['users'] ?></span> Utilisateurs
                </a>
                <a href="<?= url('/agences') ?>" class="<?= is_active_menu('/agences') ?>">
                    <span class="ic"><?= $icones['agences'] ?></span> Agences
                </a>
            <?php endif; ?>

            <?php if ($peutEcrire): ?>
                <a href="<?= url('/partages') ?>" class="<?= is_active_menu('/partages') ?>">
                    <span class="ic"><?= $icones['partages'] ?></span> Accès lecture seule
                </a>
            <?php endif; ?>

            <?php if ($estAdmin): ?>
                <a href="<?= url('/parametres/statuts') ?>" class="<?= is_active_menu('/parametres/statuts') ?>">
                    <span class="ic"><?= $icones['statuts'] ?></span> Statuts &amp; finalités
                </a>
                <a href="<?= url('/parametres') ?>" class="<?= $currentPath === '/parametres' ? 'is-active' : '' ?>">
                    <span class="ic"><?= $icones['parametres'] ?></span> Paramètres
                </a>
                <a href="<?= url('/audit') ?>" class="<?= is_active_menu('/audit') ?>">
                    <span class="ic"><?= $icones['audit'] ?></span> Journal d'audit
                </a>
            <?php endif; ?>
        </nav>
        <div style="padding:14px 18px;font-size:11.5px;color:#7fa2c7;border-top:1px solid rgba(255,255,255,.1)">
            Version 1.0 · <?= date('Y') ?>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="burger" type="button" data-burger aria-label="Menu">☰</button>
            <div class="titre-page"><?= e($titre) ?></div>
            <div class="esp"></div>

            <a href="<?= url('/notifications') ?>" title="Notifications" style="position:relative;font-size:19px">
                🔔
                <?php if ($nbNotifications > 0): ?>
                    <span class="badge-nb" style="position:absolute;top:-6px;right:-10px;background:var(--rouge);color:#fff;border-radius:20px;padding:0 6px;font-size:11px"><?= (int) $nbNotifications ?></span>
                <?php endif; ?>
            </a>

            <details style="position:relative">
                <summary style="list-style:none;cursor:pointer">
                    <span class="user-chip">
                        <span class="avatar"><?= e(initials($authUser['full_name'] ?? ($authUser['username'] ?? ''))) ?></span>
                        <span>
                            <span style="display:block;font-weight:600;font-size:13.5px"><?= e($authUser['full_name'] ?: $authUser['username']) ?></span>
                            <span class="petit texte-doux" style="text-transform:capitalize"><?= e($authUser['role']) ?></span>
                        </span>
                    </span>
                </summary>
                <div class="card" style="position:absolute;right:0;top:46px;width:220px;margin:0;z-index:50">
                    <div class="card-corps" style="padding:8px">
                        <a href="<?= url('/profil') ?>" style="display:block;padding:9px 12px;border-radius:7px;color:var(--texte)">Mon profil</a>
                        <a href="<?= url('/profil/mot-de-passe') ?>" style="display:block;padding:9px 12px;border-radius:7px;color:var(--texte)">Changer de mot de passe</a>
                        <form method="post" action="<?= url('/logout') ?>" style="margin:0">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-lien rouge" style="padding:9px 12px;width:100%;text-align:left">Déconnexion</button>
                        </form>
                    </div>
                </div>
            </details>
        </header>

        <div class="contenu">
            <?php if (!empty($flash)): ?>
                <div class="alertes">
                    <?php foreach ($flash as $type => $messages): ?>
                        <?php foreach ((array) $messages as $message): ?>
                            <div class="alerte alerte-<?= e($type) ?>" data-auto><?= $message ?></div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
