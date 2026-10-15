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
        $nbMessagesNonLus = \App\Core\Auth::peut('chat') ? (new \App\Models\Chat())->nonLus((int) $authUser['id'], \App\Core\Auth::agenceId(), \App\Core\Auth::isAdmin()) : 0;
    }
} catch (\Throwable $e) {
    // Pas bloquant pour l'affichage.
}

$estAdmin = \App\Core\Auth::isAdmin();
$peutEcrire = \App\Core\Auth::canWrite();
$icones = [
    'dashboard' => '▦', 'litiges' => '⚖', 'clients' => '🏢', 'relances' => '🔔',
    'users' => '👥', 'agences' => '🏬', 'partages' => '🔗', 'statuts' => '⚙',
    'parametres' => '🛠', 'audit' => '📋', 'profil' => '👤', 'chat' => '💬',
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
    <link rel="manifest" href="<?= url('manifest.json') ?>">
    <meta name="theme-color" content="#0f7bff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Locavente Litiges">
    <link rel="apple-touch-icon" href="<?= url('assets/icons/apple-touch-icon.svg') ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Locavente Litiges">
    <meta name="format-detection" content="telephone=no">
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
            <?php if (\App\Core\Auth::peut('litiges')): ?>
            <a href="<?= url('/litiges') ?>" class="<?= is_active_menu('/litiges') ?>">
                <span class="ic"><?= $icones['litiges'] ?></span> Litiges
                <?php if ($nbRelancesDues > 0): ?><span class="badge-nb"><?= (int) $nbRelancesDues ?></span><?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if (\App\Core\Auth::peut('clients')): ?>
            <a href="<?= url('/clients') ?>" class="<?= is_active_menu('/clients') ?>">
                <span class="ic"><?= $icones['clients'] ?></span> Clients
            </a>
            <a href="<?= url('/entreprise') ?>" class="<?= is_active_menu('/entreprise') ?>">
                <span class="ic">🔎</span> Inspecter une entreprise
            </a>
            <?php endif; ?>

            <?php if (\App\Core\Auth::peut('relances') && $peutEcrire): ?>
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

            <?php if (\App\Core\Auth::peut('chat')): ?>
            <a href="<?= url('/chat') ?>" class="<?= is_active_menu('/chat') ?>">
                <span class="ic"><?= $icones['chat'] ?? '💬' ?></span> Messagerie
                <?php if (!empty($nbMessagesNonLus)): ?><span class="badge-nb"><?= (int) $nbMessagesNonLus ?></span><?php endif; ?>
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

            <?php if (\App\Core\Auth::peut('chat')): ?>
                <a href="<?= url('/chat') ?>" title="Messagerie" style="position:relative;font-size:19px;margin-right:4px">
                    💬
                    <?php if (!empty($nbMessagesNonLus)): ?>
                        <span class="badge-nb" style="position:absolute;top:-6px;right:-10px;background:var(--bleu-600);color:#fff;border-radius:20px;padding:0 6px;font-size:11px"><?= (int) $nbMessagesNonLus ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <details style="position:relative">
                <summary style="list-style:none;cursor:pointer">
                    <span class="user-chip">
                        <span class="avatar"><?= e(initials($authUser['full_name'] ?? ($authUser['username'] ?? ''))) ?></span>
                        <span>
                            <span style="display:block;font-weight:600;font-size:13.5px"><?= e($authUser['full_name'] ?: $authUser['username']) ?></span>
                            <span class="petit texte-doux" style="text-transform:capitalize"><?= e(\App\Core\Auth::roleLabel()) ?></span>
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

<div id="pwa-install-banner" class="pwa-install-banner" hidden>
    <div class="pwa-install-content">
        <div class="pwa-install-icon">📲</div>
        <div class="pwa-install-text">
            <strong>Installer l'application</strong>
            <span>Accédez-y plus vite depuis votre écran d'accueil.</span>
        </div>
        <button type="button" class="pwa-install-btn" id="pwa-install-btn">Installer</button>
        <button type="button" class="pwa-install-close" id="pwa-install-close" aria-label="Fermer">✕</button>
    </div>
</div>

<div id="pwa-ios-guide" class="pwa-modal" hidden>
    <div class="pwa-modal-backdrop" data-close></div>
    <div class="pwa-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="pwa-ios-title">
        <div class="pwa-modal-header">
            <h3 id="pwa-ios-title">Installer sur iOS (Safari)</h3>
            <button type="button" class="pwa-modal-close" data-close aria-label="Fermer">✕</button>
        </div>
        <div class="pwa-modal-body">
            <ol class="pwa-guide-list">
                <li>
                    <div class="pwa-guide-step">1</div>
                    <div>Appuyez sur l'icône <strong>Partager</strong> <span class="pwa-share-icon">⎙</span> dans la barre Safari (en bas ou en haut selon votre appareil).</div>
                </li>
                <li>
                    <div class="pwa-guide-step">2</div>
                    <div>Faites défiler et sélectionnez <strong>Sur l'écran d'accueil</strong> (Ajouter à l'écran d'accueil).</div>
                </li>
                <li>
                    <div class="pwa-guide-step">3</div>
                    <div>Validez le nom, puis appuyez sur <strong>Ajouter</strong>. L'icône apparaîtra sur votre écran d'accueil.</div>
                </li>
            </ol>
            <p class="petit texte-doux">Astuce : ouvrez ensuite l'application directement depuis cet icône pour qu'elle s'exécute en plein écran.</p>
        </div>
        <div class="pwa-modal-footer">
            <button type="button" class="btn btn-secondaire" data-close>Compris</button>
        </div>
    </div>
</div>

<div id="pwa-android-guide" class="pwa-modal" hidden>
    <div class="pwa-modal-backdrop" data-close></div>
    <div class="pwa-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="pwa-android-title">
        <div class="pwa-modal-header">
            <h3 id="pwa-android-title">Installer l'application</h3>
            <button type="button" class="pwa-modal-close" data-close aria-label="Fermer">✕</button>
        </div>
        <div class="pwa-modal-body">
            <p>Votre navigateur peut proposer l'installation automatique. Sinon, utilisez le menu <strong>⋯</strong> (trois points) → <strong>Ajouter à l'écran d'accueil</strong> ou <strong>Installer l'application</strong>.</p>
            <p class="petit texte-doux">Une fois installée, elle s'ouvrira en plein écran et fonctionnera hors ligne.</p>
        </div>
        <div class="pwa-modal-footer">
            <button type="button" class="btn btn-secondaire" data-close>Fermer</button>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
