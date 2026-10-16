<?php
/**
 * Gestion de la double authentification.
 * @var bool $active
 * @var bool $obligatoire
 * @var int $restants
 * @var array|null $nouveauxCodes
 */
?>
<div class="fil"><a href="<?= url('/profil') ?>">Mon profil</a> / Double authentification</div>

<?php if (!empty($nouveauxCodes)): ?>
    <div class="card">
        <div class="card-entete"><h3>🔑 Vos codes de secours</h3></div>
        <div class="card-corps">
            <p><strong>Notez-les maintenant, ils ne seront plus affichés.</strong> Chaque code ne fonctionne qu'une fois et remplace le code de l'application si vous perdez votre téléphone.</p>
            <div class="grille grille-4 mono" style="gap:8px">
                <?php foreach ($nouveauxCodes as $c): ?>
                    <div class="stat"><div class="valeur" style="font-size:16px"><?= e($c) ?></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($active): ?>
    <div class="card">
        <div class="card-entete"><h3>🔐 Double authentification activée</h3></div>
        <div class="card-corps">
            <p>Votre compte est protégé par un code à usage unique. Codes de secours restants : <strong><?= (int) $restants ?></strong>.</p>
            <?php if ($obligatoire): ?>
                <div class="alerte alerte-warning">La double authentification est obligatoire pour votre profil : si vous la désactivez, vous devrez la reconfigurer immédiatement.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grille grille-2">
        <div class="card">
            <div class="card-entete"><h3>Régénérer les codes de secours</h3></div>
            <form method="post" action="<?= url('/profil/2fa/codes') ?>">
                <?= csrf_field() ?>
                <div class="card-corps">
                    <div class="form-groupe">
                        <label for="pwd_codes">Mot de passe actuel</label>
                        <input type="password" id="pwd_codes" name="password" required autocomplete="current-password">
                        <?php if (!empty($errors['password'])): ?><div class="msg-erreur"><?= e($errors['password']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="card-pied"><button type="submit" class="btn btn-secondaire">Générer de nouveaux codes</button></div>
            </form>
        </div>
        <div class="card">
            <div class="card-entete"><h3>Désactiver</h3></div>
            <form method="post" action="<?= url('/profil/2fa/desactiver') ?>" data-confirmer="Désactiver la double authentification ?">
                <?= csrf_field() ?>
                <div class="card-corps">
                    <div class="form-groupe">
                        <label for="pwd_off">Mot de passe actuel</label>
                        <input type="password" id="pwd_off" name="password" required autocomplete="current-password">
                    </div>
                </div>
                <div class="card-pied"><button type="submit" class="btn btn-rouge">Désactiver la 2FA</button></div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-entete"><h3>🔐 Activer la double authentification</h3></div>
        <form method="post" action="<?= url('/profil/2fa/activer') ?>">
            <?= csrf_field() ?>
            <div class="card-corps">
                <?php if ($obligatoire): ?>
                    <div class="alerte alerte-warning mb-2">La double authentification est obligatoire pour votre profil. Activez-la pour continuer à utiliser l'application.</div>
                <?php endif; ?>
                <ol>
                    <li>Installez une application d'authentification (Google Authenticator, Microsoft Authenticator, Authy, 1Password…).</li>
                    <li>Ajoutez un compte <strong>en saisissant cette clé</strong> (type « basé sur le temps ») :
                        <div class="mono gras mt-1" style="font-size:18px;letter-spacing:2px;user-select:all"><?= e(trim(chunk_split((string) $secret, 4, ' '))) ?></div>
                        <div class="petit texte-doux">Sur mobile, vous pouvez aussi <a href="<?= e($uri) ?>">ouvrir directement le lien d'enrôlement</a>.</div>
                    </li>
                    <li>Saisissez ci-dessous le code à 6 chiffres affiché par l'application.</li>
                </ol>
                <div class="form-groupe" style="max-width:240px">
                    <label for="code">Code de vérification</label>
                    <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="123 456">
                    <?php if (!empty($errors['code'])): ?><div class="msg-erreur"><?= e($errors['code']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="card-pied"><button type="submit" class="btn btn-vert">Activer</button></div>
        </form>
    </div>
<?php endif; ?>
