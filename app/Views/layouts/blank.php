<?php
/** Gabarit minimal (pages d'authentification, erreurs publiques). */
$flash = $flash ?? [];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre ?? 'Connexion') ?> · <?= e($appName ?? 'Gestion des litiges') ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<?php if (!empty($flash)): ?>
    <div style="position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:99;width:min(680px,92vw)">
        <?php foreach ($flash as $type => $messages): ?>
            <?php foreach ((array) $messages as $message): ?>
                <div class="alerte alerte-<?= e($type) ?>" style="box-shadow:var(--ombre)"><?= $message ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?= $content ?>
</body>
</html>
