<?php
/**
 * Acces en lecture seule (codes pour les agences).
 * @var array $partages
 * @var array $scopes
 * @var array $agences
 * @var array $clients
 */
$pm       = new \App\Models\Partage();
$urlPub   = url('/partage');
$peutEcrire = in_array($authUser['role'] ?? '', ['admin', 'gestionnaire'], true);
?>
<div class="card">
    <div class="card-entete">
        <h3>🔗 Accès en lecture seule</h3>
        <div class="esp"></div>
        <span class="petit texte-doux">Adresse à communiquer : <a href="<?= e($urlPub) ?>" target="_blank" class="mono"><?= e($urlPub) ?></a></span>
    </div>
    <div class="card-corps">
        <p class="aide mb-2">
            Créez un code d'accès pour permettre à une agence de consulter les litiges <strong>sans compte utilisateur</strong>,
            en lecture seule. Le périmètre (tous les litiges, une agence, un client ou un litige précis) limite ce qui est visible.
        </p>

        <?php if ($peutEcrire): ?>
            <details>
                <summary class="btn btn-petit" style="cursor:pointer;list-style:none;display:inline-flex">+ Nouveau code d'accès</summary>
                <form method="post" action="<?= url('/partages') ?>" id="form-partage" class="mt-2">
                    <?= csrf_field() ?>
                    <div class="ligne-champs">
                        <div class="form-groupe">
                            <label for="libelle">Libellé <span class="req">*</span></label>
                            <input type="text" id="libelle" name="libelle" placeholder="Ex : Agence Lyon — consultation" required>
                        </div>
                        <div class="form-groupe">
                            <label for="scope">Périmètre</label>
                            <select id="scope" name="scope">
                                <?php foreach ($scopes as $k => $lib): ?>
                                    <option value="<?= e($k) ?>" <?= $k === 'agence' ? 'selected' : '' ?>><?= e($lib) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-groupe">
                            <label for="sel_agence">Agence concernée</label>
                            <select id="sel_agence" name="scope_id">
                                <option value="">—</option>
                                <?php foreach ($agences as $a): ?>
                                    <option value="<?= (int) $a['id'] ?>"><?= e($a['nom']) ?><?= $a['ville'] ? ' (' . e($a['ville']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-groupe">
                            <label for="sel_client">Client concerné</label>
                            <select id="sel_client">
                                <option value="">—</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= e($c['raison_sociale']) ?><?= $c['ville'] ? ' (' . e($c['ville']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-groupe">
                            <label for="inp_litige">ID du litige</label>
                            <input type="number" id="inp_litige" min="1" placeholder="Ex : 42">
                            <div class="aide">Identifiant interne (visible dans l'URL du litige).</div>
                        </div>
                        <div class="form-groupe">
                            <label for="expires_at">Expiration</label>
                            <input type="date" id="expires_at" name="expires_at">
                            <div class="aide">
                                <?php if (($expirationDefautJours ?? 0) > 0): ?>
                                    Laissé vide : l'accès expire automatiquement après <?= (int) $expirationDefautJours ?> jours.
                                <?php else: ?>
                                    Laissé vide : l'accès n'expire pas.
                                <?php endif; ?>
                            </div>
                            <label class="check"><input type="checkbox" name="sans_expiration" value="1"> Sans expiration</label>
                        </div>
                        <div class="form-groupe">
                            <label for="mot_de_passe">Mot de passe (optionnel)</label>
                            <input type="text" id="mot_de_passe" name="mot_de_passe" autocomplete="off" minlength="8" placeholder="8 caractères minimum">
                            <div class="aide">Demandé après le code ou le lien. Communiquez-le par un autre canal.</div>
                        </div>
                    </div>
                    <label class="check mb-2"><input type="checkbox" name="afficher_documents" value="1"> Afficher les documents que vous aurez marqués « publics » sur les litiges</label>
                    <button type="submit" class="btn">Créer le code d'accès</button>
                </form>
            </details>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if (empty($partages)): ?>
        <div class="vide"><div class="icone">🔗</div><p>Aucun accès en lecture seule configuré.</p></div>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Libellé</th><th>Périmètre</th><th>Code</th>
                    <th class="num">Vues</th><th>Dernière utilisation</th><th>Expire le</th><th>État</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($partages as $p): ?>
                <?php
                $expire   = !empty($p['expires_at']) && strtotime((string) $p['expires_at']) < time();
                $actif    = (int) $p['actif'] === 1 && !$expire;
                ?>
                <tr>
                    <td>
                        <strong><?= e($p['libelle']) ?></strong>
                        <div class="petit texte-doux">par <?= e($p['user_nom'] ?: '—') ?></div>
                        <div class="petit">
                            <?= !empty($p['mot_de_passe_hash']) ? '<span class="badge badge-contour">🔒 mot de passe</span>' : '' ?>
                            <?= (int) ($p['afficher_documents'] ?? 0) === 1 ? '<span class="badge badge-contour">📎 documents</span>' : '' ?>
                        </div>
                        <?php if ($peutEcrire): ?>
                            <details class="petit mt-1">
                                <summary style="cursor:pointer;color:var(--bleu-600)">Sécurité / journal</summary>
                                <form method="post" action="<?= url('/partages/' . (int) $p['id'] . '/securiser') ?>" class="mt-1">
                                    <?= csrf_field() ?>
                                    <div class="form-groupe">
                                        <label>Nouveau mot de passe</label>
                                        <input type="text" name="mot_de_passe" autocomplete="off" minlength="8" placeholder="laisser vide pour ne pas changer">
                                    </div>
                                    <?php if (!empty($p['mot_de_passe_hash'])): ?>
                                        <label class="check"><input type="checkbox" name="retirer_mot_de_passe" value="1"> Retirer le mot de passe</label>
                                    <?php endif; ?>
                                    <label class="check"><input type="checkbox" name="afficher_documents" value="1" <?= (int) ($p['afficher_documents'] ?? 0) === 1 ? 'checked' : '' ?>> Afficher les documents « publics »</label>
                                    <div class="form-groupe" style="max-width:220px">
                                        <label>Prolonger de (jours)</label>
                                        <input type="number" name="prolonger_jours" min="0" max="365" value="0">
                                    </div>
                                    <button type="submit" class="btn btn-petit">Enregistrer</button>
                                </form>
                                <?php if (!empty($journal[(int) $p['id']])): ?>
                                    <div class="mt-1"><strong>Dernières consultations</strong>
                                        <?php foreach ($journal[(int) $p['id']] as $c): ?>
                                            <div class="texte-doux"><?= datetime_fr($c['created_at']) ?> · <?= e($c['action']) ?><?= $c['reference'] ? ' · ' . e($c['reference']) : '' ?> · <?= e($c['ip']) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </details>
                        <?php endif; ?>
                    </td>
                    <td class="petit"><?= e($pm->scopeLabel($p)) ?></td>
                    <td>
                        <span class="mono gras"><?= e($p['code']) ?></span>
                        <button type="button" class="btn-lien" data-copier="<?= e($p['code']) ?>" title="Copier le code">Copier</button>
                    </td>
                    <td class="num"><?= (int) $p['vues'] ?></td>
                    <td class="petit"><?= $p['last_used_at'] ? datetime_fr($p['last_used_at']) : 'Jamais' ?></td>
                    <td class="petit"><?= $p['expires_at'] ? date_fr($p['expires_at']) : '—' ?></td>
                    <td>
                        <?php if ($expire): ?>
                            <span class="badge" style="background:var(--gris)">Expiré</span>
                        <?php elseif ($actif): ?>
                            <span class="petit texte-vert">● Actif</span>
                        <?php else: ?>
                            <span class="petit texte-doux">○ Désactivé</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?php if ($peutEcrire): ?>
                            <form method="post" action="<?= url('/partages/' . (int) $p['id'] . '/basculer') ?>" class="inline"
                                  data-confirmer="<?= (int) $p['actif'] === 1 ? 'Désactiver' : 'Réactiver' ?> cet accès ?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-lien"><?= (int) $p['actif'] === 1 ? 'Désactiver' : 'Réactiver' ?></button>
                            </form>
                            <form method="post" action="<?= url('/partages/' . (int) $p['id'] . '/supprimer') ?>" class="inline"
                                  data-confirmer="Supprimer définitivement cet accès ? Le code ne fonctionnera plus.">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-lien rouge">Supprimer</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    'use strict';
    var sel = document.getElementById('scope');
    if (!sel) return;

    var controles = {
        agence: document.getElementById('sel_agence'),
        client: document.getElementById('sel_client'),
        litige: document.getElementById('inp_litige')
    };

    function sync() {
        var scope = sel.value;
        Object.keys(controles).forEach(function (cle) {
            var el = controles[cle];
            if (!el) return;
            var actif = (cle === scope);
            var groupe = el.closest('.form-groupe');
            if (groupe) groupe.style.display = actif ? '' : 'none';
            el.disabled = !actif;
            if (actif) {
                el.setAttribute('name', 'scope_id');
            } else {
                el.removeAttribute('name');
            }
        });
    }

    sel.addEventListener('change', sync);
    Object.keys(controles).forEach(function (cle) {
        var el = controles[cle];
        if (!el) return;
        el.addEventListener('change', sync);
        el.addEventListener('input', sync);
    });

    var form = document.getElementById('form-partage');
    if (form) form.addEventListener('submit', sync);

    sync();
})();
</script>
