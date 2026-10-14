<?php
/**
 * Changement de mot de passe de l'utilisateur connecte.
 * @var bool $force  Vrai lorsqu'un changement est impose (premiere connexion).
 */
$force    = (bool) ($force ?? false);
$minLen   = (int) \App\Core\Config::get('security.password_min_length', 10);
?>
<div class="grille" style="grid-template-columns:2fr 1fr;align-items:start">

    <div class="card">
        <div class="card-entete">
            <h3><?= $force ? 'Définissez votre nouveau mot de passe' : 'Changer mon mot de passe' ?></h3>
        </div>
        <form method="post" action="<?= url('/profil/mot-de-passe') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="card-corps">
                <?php if ($force): ?>
                    <div class="alerte alerte-warning mb-2">
                        Pour des raisons de sécurité, vous devez définir un nouveau mot de passe avant de continuer.
                    </div>
                <?php endif; ?>

                <?php if (!$force): ?>
                    <div class="form-groupe">
                        <label for="current_password">Mot de passe actuel <span class="req">*</span></label>
                        <input type="password" id="current_password" name="current_password" required autofocus>
                        <?php if (!empty($errors['current_password'])): ?><div class="msg-erreur"><?= e($errors['current_password']) ?></div><?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="form-groupe">
                    <label for="password">Nouveau mot de passe <span class="req">*</span></label>
                    <input type="password" id="password" name="password" required <?= $force ? 'autofocus' : '' ?>>
                    <div class="aide">Au moins <?= (int) $minLen ?> caractères.</div>
                    <?php if (!empty($errors['password'])): ?><div class="msg-erreur"><?= e($errors['password']) ?></div><?php endif; ?>
                </div>

                <div class="form-groupe">
                    <label for="password_confirm">Confirmation <span class="req">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                    <?php if (!empty($errors['password_confirm'])): ?><div class="msg-erreur"><?= e($errors['password_confirm']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="card-pied">
                <button type="submit" class="btn">Enregistrer le nouveau mot de passe</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-entete"><h3>🔒 Conseils de sécurité</h3></div>
        <div class="card-corps petit texte-doux">
            <ul style="padding-left:18px;margin:0">
                <li>Utilisez une phrase de passe longue et unique.</li>
                <li>Mélangez majuscules, minuscules, chiffres et symboles.</li>
                <li>Ne réutilisez pas un mot de passe déjà employé ailleurs.</li>
                <li>Ne partagez jamais vos identifiants.</li>
            </ul>
        </div>
    </div>

</div>
