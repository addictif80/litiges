<?php
/** @var array $user @var string $mode */
$estEdition = $mode === 'edition';
$action = $estEdition ? url('/utilisateurs/' . (int) $user['id'] . '/modifier') : url('/utilisateurs');
$v = static function (string $cle, mixed $defaut = '') use ($user) {
    return old($cle, $user[$cle] ?? $defaut);
};
$err = static fn (string $cle): ?string => $errors[$cle] ?? null;
?>
<div class="fil"><a href="<?= url('/utilisateurs') ?>">Utilisateurs</a> / <?= $estEdition ? 'Modifier' : 'Nouveau' ?></div>

<div class="card" style="max-width:760px">
    <div class="card-entete"><h3><?= $estEdition ? 'Modifier l\'utilisateur' : 'Créer un utilisateur' ?></h3></div>
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="card-corps">
            <div class="ligne-champs">
                <div class="form-groupe">
                    <label for="username">Identifiant <span class="req">*</span></label>
                    <input type="text" id="username" name="username" value="<?= e($v('username')) ?>" required maxlength="60">
                    <?php if ($err('username')): ?><div class="msg-erreur"><?= e($err('username')) ?></div><?php endif; ?>
                </div>
                <div class="form-groupe">
                    <label for="full_name">Nom complet</label>
                    <input type="text" id="full_name" name="full_name" value="<?= e($v('full_name')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($v('email')) ?>">
                    <?php if ($err('email')): ?><div class="msg-erreur"><?= e($err('email')) ?></div><?php endif; ?>
                </div>
                <div class="form-groupe">
                    <label for="role">Rôle <span class="req">*</span></label>
                    <select id="role" name="role" required>
                        <?php foreach (['admin' => 'Administrateur', 'gestionnaire' => 'Gestionnaire', 'consultant' => 'Consultant', 'lecteur' => 'Lecteur (consultation)'] as $k => $lib): ?>
                            <option value="<?= $k ?>" <?= $v('role') === $k ? 'selected' : '' ?>><?= $lib ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($err('role')): ?><div class="msg-erreur"><?= e($err('role')) ?></div><?php endif; ?>
                </div>
                <div class="form-groupe">
                    <label for="agence_id">Agence de rattachement</label>
                    <select id="agence_id" name="agence_id">
                        <option value="">—</option>
                        <?php foreach ($agences as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) $v('agence_id') === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-groupe">
                <label for="password">Mot de passe <?= $estEdition ? '(laisser vide pour conserver)' : '<span class="req">*</span>' ?></label>
                <input type="password" id="password" name="password" <?= $estEdition ? '' : 'required' ?> autocomplete="new-password">
                <?php if ($err('password')): ?><div class="msg-erreur"><?= e($err('password')) ?></div><?php endif; ?>
                <div class="aide">10 caractères minimum. Un mot de passe peut être généré automatiquement via « Réinitialiser ».</div>
            </div>

            <div class="flex">
                <label class="check"><input type="checkbox" name="actif" value="1" <?= (int) $v('actif', 1) === 1 ? 'checked' : '' ?>> Compte actif</label>
                <label class="check"><input type="checkbox" name="must_change_password" value="1" <?= (int) $v('must_change_password', 0) === 1 ? 'checked' : '' ?>> Demander le changement à la 1ʳᵉ connexion</label>
            </div>
        </div>
        <div class="card-pied flex">
            <button type="submit" class="btn btn-vert"><?= $estEdition ? 'Enregistrer' : 'Créer' ?></button>
            <a href="<?= url('/utilisateurs') ?>" class="btn btn-secondaire">Annuler</a>
        </div>
    </form>
</div>
