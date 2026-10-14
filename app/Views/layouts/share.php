<?php
/** Gabarit de la consultation publique en lecture seule (acces par code). */
$flash = $flash ?? [];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre ?? 'Consultation') ?> · <?= e($appName ?? 'Gestion des litiges') ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<header class="topbar" style="position:sticky;top:0">
    <div class="titre-page">⚖️ <?= e($appName ?? 'Gestion des litiges') ?></div>
    <span class="badge badge-contour">Lecture seule</span>
    <div class="esp"></div>
    <?php if (!empty($partage)): ?>
        <span class="petit texte-doux"><?= e((new \App\Models\Partage())->scopeLabel($partage)) ?></span>
        <form method="post" action="<?= url('/partage/quitter') ?>" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondaire btn-petit">Quitter</button>
        </form>
    <?php endif; ?>
</header>
<div class="contenu" style="max-width:1200px;margin:auto">
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
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
