<?php
/**
 * Profil de l'utilisateur connecte.
 * @var array $user
 */
$roles = ['admin' => 'Administrateur', 'gestionnaire' => 'Gestionnaire', 'lecteur' => 'Lecteur'];

$agenceNom = '';
if (!empty($user['agence_id'])) {
    $agence = (new \App\Models\Agence())->find((int) $user['agence_id']);
    $agenceNom = (string) ($agence['nom'] ?? '');
}
?>
<div class="grille" style="grid-template-columns:1fr 2fr;align-items:start">

    <div class="card">
        <div class="card-corps" style="text-align:center">
            <div class="avatar grand" style="margin:0 auto 12px"><?= e(initials($user['full_name'] ?: $user['username'])) ?></div>
            <h2 style="margin-bottom:2px"><?= e($user['full_name'] ?: $user['username']) ?></h2>
            <div class="petit texte-doux mono">@<?= e($user['username']) ?></div>
            <div class="mt-2">
                <span class="badge" style="background:<?= $user['role'] === 'admin' ? 'var(--rouge)' : ($user['role'] === 'gestionnaire' ? 'var(--bleu-600)' : 'var(--gris)') ?>">
                    <?= e($roles[$user['role']] ?? $user['role']) ?>
                </span>
            </div>
            <dl class="definition mt-3" style="grid-template-columns:auto;text-align:left">
                <dt>Agence</dt><dd><?= e($agenceNom ?: '—') ?></dd>
                <dt>Dernière connexion</dt><dd><?= $user['last_login_at'] ? datetime_fr($user['last_login_at']) : 'Jamais' ?></dd>
                <dt>Compte créé le</dt><dd><?= datetime_fr($user['created_at']) ?></dd>
            </dl>
            <a href="<?= url('/profil/mot-de-passe') ?>" class="btn btn-secondaire mt-2">🔑 Changer de mot de passe</a>
        </div>
    </div>

    <div class="card">
        <div class="card-entete"><h3>Mes informations</h3></div>
        <form method="post" action="<?= url('/profil') ?>">
            <?= csrf_field() ?>
            <div class="card-corps">
                <?php if (!empty($user['must_change_password'])): ?>
                    <div class="alerte alerte-warning mb-2">
                        Vous devez définir un nouveau mot de passe.
                        <a href="<?= url('/profil/mot-de-passe') ?>">Changer maintenant</a>.
                    </div>
                <?php endif; ?>

                <div class="form-groupe">
                    <label for="username">Identifiant</label>
                    <input type="text" id="username" value="<?= e($user['username']) ?>" disabled>
                    <div class="aide">L'identifiant ne peut être modifié que par un administrateur.</div>
                </div>
                <div class="form-groupe">
                    <label for="full_name">Nom complet <span class="req">*</span></label>
                    <input type="text" id="full_name" name="full_name" value="<?= e(old('full_name', $user['full_name'] ?? '')) ?>" required>
                    <?php if (!empty($errors['full_name'])): ?><div class="msg-erreur"><?= e($errors['full_name']) ?></div><?php endif; ?>
                </div>
                <div class="form-groupe">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>">
                    <div class="aide">Utilisée pour la réinitialisation du mot de passe et les notifications.</div>
                    <?php if (!empty($errors['email'])): ?><div class="msg-erreur"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="card-pied">
                <button type="submit" class="btn">Enregistrer mes informations</button>
            </div>
        </form>
    </div>

</div>

<div class="grille grille-2">
    <div class="card">
        <div class="card-entete"><h3>🔐 Double authentification</h3></div>
        <div class="card-corps">
            <?php if ((int) ($user['totp_enabled'] ?? 0) === 1): ?>
                <p><span class="badge" style="background:#198754">Activée</span> Un code à usage unique est demandé à chaque connexion.</p>
            <?php else: ?>
                <p><span class="badge" style="background:#6c757d">Désactivée</span> Protégez votre compte avec une application d'authentification (Google Authenticator, Microsoft Authenticator…).</p>
            <?php endif; ?>
            <a href="<?= url('/profil/2fa') ?>" class="btn btn-secondaire"><?= (int) ($user['totp_enabled'] ?? 0) === 1 ? 'Gérer' : 'Activer' ?></a>
        </div>
    </div>

    <div class="card">
        <div class="card-entete"><h3>📅 Agenda (Outlook, Google, Apple…)</h3></div>
        <div class="card-corps">
            <?php if (!empty($user['ical_token'])): ?>
                <p class="petit">Abonnez votre agenda à cette adresse privée : vos relances, échéances, paiements attendus et tâches s'y affichent et se mettent à jour automatiquement.</p>
                <input type="text" readonly value="<?= e(url('/agenda.ics?token=' . $user['ical_token'])) ?>" data-selectionner style="font-family:monospace;font-size:12px">
                <p class="aide">Ne partagez pas cette adresse : elle donne accès à votre agenda sans mot de passe.</p>
                <div class="flex">
                    <form method="post" action="<?= url('/profil/agenda') ?>" data-confirmer="Générer une nouvelle adresse ? L'ancienne cessera de fonctionner.">
                        <?= csrf_field() ?><button type="submit" class="btn btn-petit btn-secondaire">Régénérer l'adresse</button>
                    </form>
                    <form method="post" action="<?= url('/profil/agenda/supprimer') ?>">
                        <?= csrf_field() ?><button type="submit" class="btn btn-petit btn-rouge">Désactiver</button>
                    </form>
                </div>
            <?php else: ?>
                <p class="petit">Obtenez une adresse d'abonnement (iCal) pour retrouver vos échéances de recouvrement dans votre agenda habituel.</p>
                <form method="post" action="<?= url('/profil/agenda') ?>">
                    <?= csrf_field() ?><button type="submit" class="btn btn-secondaire">Activer mon agenda</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
