<?php /** @var array $errors */ ?>
<div class="page-connexion" style="min-height:calc(100vh - 130px);background:none">
    <div class="carte-connexion">
        <div class="entete">
            <h1>🔒 Accès protégé</h1>
            <p>Saisissez le mot de passe qui vous a été communiqué</p>
        </div>
        <div class="corps">
            <?php if (!empty($errors['mot_de_passe'])): ?>
                <div class="alerte alerte-error mb-2"><?= e($errors['mot_de_passe']) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= url('/partage/mot-de-passe') ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-groupe">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" required autofocus autocomplete="off">
                </div>
                <button type="submit" class="btn">Accéder</button>
            </form>
            <div class="lien-secondaire"><a href="<?= url('/partage') ?>">Retour</a></div>
        </div>
    </div>
</div>
