<?php /** @var array $errors */ ?>
<div class="page-connexion">
    <div class="carte-connexion">
        <div class="entete">
            <h1>⚖️ Gestion des litiges</h1>
            <p>Location de matériel BTP, véhicules et machines de chantier</p>
        </div>
        <div class="corps">
            <?php if (!empty($errors['global'])): ?>
                <div class="alerte alerte-error mb-2"><?= e($errors['global']) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= url('/login') ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-groupe">
                    <label for="username">Identifiant ou email</label>
                    <input type="text" id="username" name="username" value="<?= e(old('username')) ?>" required autofocus>
                </div>
                <div class="form-groupe">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Se connecter</button>
            </form>

            <div class="lien-secondaire">
                <a href="<?= url('/forgot') ?>">Mot de passe oublié ?</a>
                &nbsp;·&nbsp;
                <a href="<?= url('/partage') ?>">Accès lecture seule</a>
            </div>
        </div>
    </div>
</div>
