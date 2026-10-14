<?php
/** Page d'erreur generique. */
$code = (int) ($code ?? 500);
$message = (string) ($message ?? 'Une erreur est survenue.');
$titres = [403 => 'Accès refusé', 404 => 'Page introuvable', 405 => 'Méthode non autorisée', 419 => 'Session expirée', 500 => 'Erreur interne'];
?>
<div class="vide" style="padding:70px 20px">
    <div style="font-size:52px;font-weight:800;color:var(--bleu);opacity:.35"><?= $code ?></div>
    <h1 style="margin-top:10px"><?= e($titres[$code] ?? 'Erreur') ?></h1>
    <p class="texte-doux"><?= e($message) ?></p>
    <p class="mt-2">
        <a href="<?= url('/') ?>" class="btn">Retour au tableau de bord</a>
    </p>
</div>
