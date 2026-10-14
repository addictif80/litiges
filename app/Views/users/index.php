<?php /** @var array $users */ ?>
<div class="flex mb-2">
    <a href="<?= url('/utilisateurs/nouveau') ?>" class="btn">+ Nouvel utilisateur</a>
</div>

<div class="card">
    <table class="tableau">
        <thead>
            <tr><th>Utilisateur</th><th>Identifiant</th><th>Email</th><th>Rôle</th><th>Agence</th><th>Dernière connexion</th><th>État</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div class="flex">
                        <span class="avatar"><?= e(initials($u['full_name'] ?: $u['username'])) ?></span>
                        <strong><?= e($u['full_name'] ?: '—') ?></strong>
                    </div>
                </td>
                <td class="mono"><?= e($u['username']) ?></td>
                <td class="petit"><?= e($u['email'] ?: '—') ?></td>
                <td>
                    <?php
                    $couleurs = ['admin' => 'var(--rouge)', 'gestionnaire' => 'var(--bleu-600)', 'lecteur' => 'var(--gris)'];
                    ?>
                    <span class="badge" style="background:<?= $couleurs[$u['role']] ?? 'var(--gris)' ?>"><?= e(ucfirst($u['role'])) ?></span>
                </td>
                <td class="petit"><?= e($u['agence_nom'] ?: '—') ?></td>
                <td class="petit"><?= $u['last_login_at'] ? datetime_fr($u['last_login_at']) : 'Jamais' ?></td>
                <td>
                    <?php if ((int) $u['actif'] === 1): ?>
                        <span class="petit texte-vert">● Actif</span>
                    <?php else: ?>
                        <span class="petit texte-doux">○ Inactif</span>
                    <?php endif; ?>
                    <?php if (!empty($u['locked_until']) && strtotime((string) $u['locked_until']) > time()): ?>
                        <span class="badge" style="background:var(--rouge)">Verrouillé</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="<?= url('/utilisateurs/' . (int) $u['id'] . '/modifier') ?>" class="btn-lien">Modifier</a>
                    <form method="post" action="<?= url('/utilisateurs/' . (int) $u['id'] . '/mot-de-passe') ?>" class="inline"
                          data-confirmer="Réinitialiser le mot de passe de <?= e($u['username']) ?> ? Un mot de passe temporaire sera généré.">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-lien">Réinit. mot de passe</button>
                    </form>
                    <?php if ((int) $u['id'] !== (int) $authUser['id']): ?>
                        <form method="post" action="<?= url('/utilisateurs/' . (int) $u['id'] . '/supprimer') ?>" class="inline"
                              data-confirmer="Supprimer définitivement l'utilisateur <?= e($u['username']) ?> ?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-lien rouge">Supprimer</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-corps petit texte-doux">
        <strong>Rôles :</strong>
        <em>Administrateur</em> — accès total (utilisateurs, paramètres, audit, suppression).
        <em>Gestionnaire</em> — création/modification des litiges, clients et relances.
        <em>Lecteur</em> — consultation uniquement (aucune modification).
    </div>
</div>
