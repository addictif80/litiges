<?php /** @var array $errors */ ?>
<div class="page-connexion">
    <div class="carte-connexion">
        <div class="entete">
            <h1>Nouveau mot de passe</h1>
            <p>Définissez votre nouveau mot de passe</p>
        </div>
        <div class="corps">
            <form method="post" action="<?= url('/reset/' . e($token)) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-groupe">
                    <label for="password">Nouveau mot de passe</label>
                    <input type="password" id="password" name="password" required autofocus>
                    <?php if (!empty($errors['password'])): ?><div class="msg-erreur"><?= e($errors['password']) ?></div><?php endif; ?>
                </div>
                <div class="form-groupe">
                    <label for="password_confirm">Confirmation</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                    <?php if (!empty($errors['password_confirm'])): ?><div class="msg-erreur"><?= e($errors['password_confirm']) ?></div><?php endif; ?>
                </div>
                <button type="submit" class="btn">Valider</button>
            </form>
        </div>
    </div>
</div>
