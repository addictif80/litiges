<?php
/**
 * API REST (jetons) et webhooks.
 * @var array $jetons
 * @var array $utilisateurs
 * @var array $webhooks
 * @var array $livraisons
 * @var array<string,string> $evenements
 * @var string|null $jetonClair
 * @var string|null $secretClair
 */
$couleursLivraison = ['livre' => '#198754', 'echec' => '#fd7e14', 'abandonne' => '#dc3545', 'en_attente' => '#6c757d'];
?>
<?php if ($jetonClair): ?>
    <div class="alerte alerte-success mb-2">
        <strong>Votre nouveau jeton d'API (affiché une seule fois) :</strong>
        <div class="mono mt-1" style="user-select:all;word-break:break-all"><?= e($jetonClair) ?></div>
        <div class="petit">Utilisation : <code>Authorization: Bearer &lt;jeton&gt;</code>. Documentation : <code>docs/api.md</code>.</div>
    </div>
<?php endif; ?>
<?php if ($secretClair): ?>
    <div class="alerte alerte-success mb-2">
        <strong>Secret de signature du webhook (affiché une seule fois) :</strong>
        <div class="mono mt-1" style="user-select:all;word-break:break-all"><?= e($secretClair) ?></div>
        <div class="petit">Vérifiez l'en-tête <code>X-Litiges-Signature</code> : <code>t=&lt;horodatage&gt;,v1=&lt;HMAC-SHA256(horodatage + "." + corps, secret)&gt;</code>.</div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-entete"><h3>🔑 Jetons d'API REST</h3></div>
    <?php if (empty($jetons)): ?>
        <div class="card-corps texte-doux">Aucun jeton. L'API permet de brancher d'autres outils (ERP, tableaux de bord, automatisations).</div>
    <?php else: ?>
        <table class="tableau tableau-compact">
            <thead><tr><th>Nom</th><th>Préfixe</th><th>Agit comme</th><th>Droits</th><th>Dernière utilisation</th><th>Expire</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($jetons as $j): ?>
                <tr>
                    <td><?= e($j['nom']) ?></td>
                    <td class="mono petit"><?= e($j['prefixe']) ?>…</td>
                    <td class="petit"><?= e($j['full_name'] ?: $j['username']) ?></td>
                    <td class="petit"><?= (int) $j['ecriture'] === 1 ? 'Lecture + écriture' : 'Lecture seule' ?></td>
                    <td class="petit"><?= $j['last_used_at'] ? datetime_fr($j['last_used_at']) : 'jamais' ?></td>
                    <td class="petit"><?= $j['expires_at'] ? date_fr($j['expires_at']) : '—' ?></td>
                    <td class="actions">
                        <form method="post" action="<?= url('/parametres/integrations/jetons/' . (int) $j['id'] . '/revoquer') ?>" class="inline" data-confirmer="Révoquer ce jeton ? Les applications qui l'utilisent seront refusées.">
                            <?= csrf_field() ?><button type="submit" class="btn-lien rouge">Révoquer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <form method="post" action="<?= url('/parametres/integrations/jetons') ?>" class="card-pied">
        <?= csrf_field() ?>
        <div class="ligne-champs">
            <div class="form-groupe"><label for="jeton_nom">Nom du jeton</label><input type="text" id="jeton_nom" name="nom" maxlength="120" required placeholder="Ex : Tableau de bord Power BI"></div>
            <div class="form-groupe">
                <label for="jeton_user">Agit au nom de</label>
                <select id="jeton_user" name="user_id">
                    <?php foreach ($utilisateurs as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === (int) $authUser['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?> (<?= e($u['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-groupe"><label for="jeton_exp">Expire dans (jours, 0 = jamais)</label><input type="number" id="jeton_exp" name="expire_jours" min="0" max="3650" value="365"></div>
        </div>
        <label class="check"><input type="checkbox" name="ecriture" value="1"> Autoriser l'écriture (créer des litiges, paiements, relances)</label>
        <p class="aide">Les droits du jeton sont limités à ceux de l'utilisateur choisi. Limite : 120 requêtes par minute.</p>
        <button type="submit" class="btn btn-petit mt-1">Créer le jeton</button>
    </form>
</div>

<div class="card">
    <div class="card-entete"><h3>📡 Webhooks</h3></div>
    <?php if (empty($webhooks)): ?>
        <div class="card-corps texte-doux">Aucun webhook. Un webhook notifie un autre système (URL https) à chaque événement choisi.</div>
    <?php else: ?>
        <?php foreach ($webhooks as $w): ?>
            <?php $abonnes = json_decode((string) $w['evenements'], true) ?: []; ?>
            <div class="card-corps" style="border-bottom:1px solid #eef2f7">
                <div class="flex" style="justify-content:space-between;flex-wrap:wrap">
                    <div>
                        <strong><?= e($w['nom']) ?></strong>
                        <?= (int) $w['actif'] === 1 ? '' : '<span class="badge" style="background:#dc3545">désactivé</span>' ?>
                        <div class="mono petit"><?= e($w['url']) ?></div>
                        <div class="petit texte-doux">
                            <?= e(implode(', ', array_map(static fn (string $ev): string => $evenements[$ev] ?? $ev, $abonnes))) ?>
                            · dernier envoi : <?= $w['dernier_envoi_at'] ? datetime_fr($w['dernier_envoi_at']) . ' (HTTP ' . (int) $w['dernier_statut'] . ')' : 'jamais' ?>
                            <?= (int) $w['echecs_consecutifs'] > 0 ? ' · <span class="texte-rouge">' . (int) $w['echecs_consecutifs'] . ' échec(s) consécutif(s)</span>' : '' ?>
                        </div>
                    </div>
                    <div class="flex">
                        <form method="post" action="<?= url('/parametres/integrations/webhooks/' . (int) $w['id'] . '/tester') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-petit btn-secondaire">Tester</button></form>
                        <form method="post" action="<?= url('/parametres/integrations/webhooks/' . (int) $w['id'] . '/basculer') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-petit btn-secondaire"><?= (int) $w['actif'] === 1 ? 'Désactiver' : 'Réactiver' ?></button></form>
                        <form method="post" action="<?= url('/parametres/integrations/webhooks/' . (int) $w['id'] . '/supprimer') ?>" data-confirmer="Supprimer ce webhook ?"><?= csrf_field() ?><button type="submit" class="btn btn-petit btn-rouge">Suppr.</button></form>
                    </div>
                </div>
                <?php if (!empty($livraisons[(int) $w['id']])): ?>
                    <div class="petit mt-1">
                        Dernières livraisons :
                        <?php foreach ($livraisons[(int) $w['id']] as $l): ?>
                            <span class="badge" style="background:<?= e($couleursLivraison[$l['statut']] ?? '#6c757d') ?>" title="<?= e(($l['erreur'] ?? '') . ' ' . datetime_fr($l['created_at'])) ?>"><?= e($l['evenement']) ?> · <?= e($l['statut']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <form method="post" action="<?= url('/parametres/integrations/webhooks') ?>" class="card-pied">
        <?= csrf_field() ?>
        <div class="ligne-champs">
            <div class="form-groupe"><label for="wh_nom">Nom</label><input type="text" id="wh_nom" name="nom" maxlength="120" required></div>
            <div class="form-groupe" style="grid-column:span 2"><label for="wh_url">URL de destination (https)</label><input type="url" id="wh_url" name="url" required placeholder="https://exemple.fr/hooks/litiges"></div>
        </div>
        <div class="flex mb-1" style="flex-wrap:wrap;gap:6px 16px">
            <?php foreach ($evenements as $cle => $lib): ?>
                <label class="check"><input type="checkbox" name="evenements[]" value="<?= e($cle) ?>"> <?= e($lib) ?></label>
            <?php endforeach; ?>
        </div>
        <p class="aide">Les adresses du réseau interne (localhost, 192.168.x.x…) sont refusées. Les échecs sont rejoués automatiquement (cron <code>cron/webhooks.php</code>).</p>
        <button type="submit" class="btn btn-petit mt-1">Ajouter le webhook</button>
    </form>
</div>
