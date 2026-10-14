<?php
/**
 * Saisie du code d'acces pour la consultation publique (lecture seule).
 * @var array|null $partage
 */
?>
<div class="page-connexion" style="min-height:calc(100vh - 130px);background:none">
    <div class="carte-connexion">
        <div class="entete">
            <h1>Consultation en lecture seule</h1>
            <p>Accès réservé aux agences, par code d'accès</p>
        </div>
        <div class="corps">
            <?php if (!empty($errors['code'])): ?>
                <div class="alerte alerte-error mb-2"><?= e($errors['code']) ?></div>
            <?php endif; ?>

            <?php if (!empty($partage)): ?>
                <p class="texte-doux mb-2">Vous êtes déjà connecté à l'accès « <?= e($partage['libelle']) ?> ».</p>
                <a href="<?= url('/partage/consultation') ?>" class="btn" style="width:100%;justify-content:center">Reprendre la consultation</a>
            <?php else: ?>
                <form method="post" action="<?= url('/partage') ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="form-groupe">
                        <label for="code">Code d'accès</label>
                        <input type="text" id="code" name="code" value="<?= e(old('code')) ?>"
                               placeholder="Ex : A1B2C3D4E5" required autofocus
                               style="text-transform:uppercase;letter-spacing:2px;font-family:monospace">
                        <div class="aide">Le code vous a été communiqué par votre gestionnaire.</div>
                    </div>
                    <button type="submit" class="btn">Accéder à la consultation</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
