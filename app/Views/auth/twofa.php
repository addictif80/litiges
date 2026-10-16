<?php /** @var array $errors */ ?>
<div class="page-connexion">
    <div class="carte-connexion">
        <div class="entete">
            <h1>🔐 Vérification en deux étapes</h1>
            <p>Saisissez le code à 6 chiffres de votre application d'authentification.</p>
        </div>
        <div class="corps">
            <?php if (!empty($errors['code'])): ?>
                <div class="alerte alerte-error mb-2"><?= e($errors['code']) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= url('/login/2fa') ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-groupe">
                    <label for="code">Code de vérification</label>
                    <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="14" required autofocus placeholder="123 456">
                    <div class="aide">Vous avez perdu votre téléphone ? Saisissez un code de secours (format XXXXXX-XXXXXX).</div>
                </div>
                <button type="submit" class="btn">Valider</button>
            </form>

            <div class="lien-secondaire">
                <a href="<?= url('/login') ?>">Retour à la connexion</a>
            </div>
        </div>
    </div>
</div>
