<?php
/**
 * @var array $conversations
 * @var array $users
 * @var array $agences
 */
?>
<div class="flex mb-2" style="align-items:flex-end">
    <div style="flex:1">
        <h2>Messagerie</h2>
        <p class="aide">Discussions directes, groupes et canaux d'agence.</p>
    </div>
    <details>
        <summary class="btn">+ Nouvelle conversation</summary>
        <div class="card" style="position:absolute;right:0;z-index:10;min-width:320px">
            <form method="post" action="<?= url('/chat') ?>" class="card-corps">
                <?= csrf_field() ?>
                <div class="form-groupe">
                    <label for="type">Type</label>
                    <select id="type" name="type" onchange="this.form.querySelectorAll('[data-groupe],[data-direct],[data-agence]').forEach(e=>e.style.display='none');const v=this.value;this.form.querySelector('[data-'+v+']').style.display='';">
                        <option value="direct">1-à-1</option>
                        <option value="groupe">Groupe</option>
                        <?php if ($estAdmin ?? false): ?><option value="agence">Canal d'agence</option><?php endif; ?>
                    </select>
                </div>
                <div data-direct>
                    <div class="form-groupe">
                        <label for="destinataire_id">Destinataire</label>
                        <select name="destinataire_id" id="destinataire_id" required>
                            <option value="">—</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name'] ?: $u['username']) ?> <?= $u['agence_nom'] ? '(' . e($u['agence_nom']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div data-groupe style="display:none">
                    <div class="form-groupe">
                        <label for="titre">Nom du groupe <span class="req">*</span></label>
                        <input type="text" id="titre" name="titre" maxlength="150">
                    </div>
                    <div class="form-groupe">
                        <label>Membres</label>
                        <div style="max-height:220px;overflow:auto;border:1px solid var(--bord);border-radius:4px;padding:8px">
                            <?php foreach ($users as $u): ?>
                                <label class="check"><input type="checkbox" name="membres[]" value="<?= (int) $u['id'] ?>"> <?= e($u['full_name'] ?: $u['username']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php if ($estAdmin ?? false): ?>
                <div data-agence style="display:none">
                    <div class="form-groupe">
                        <label for="agence_id">Agence</label>
                        <select name="agence_id" id="agence_id">
                            <option value="">—</option>
                            <?php foreach ($agences as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"><?= e($a['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn">Créer</button>
            </form>
        </div>
    </details>
</div>

<div class="card">
    <div class="liste-conversations">
        <?php if (empty($conversations)): ?>
            <div class="texte-doux p-2">Aucune conversation. Créez-en une.</div>
        <?php else: ?>
            <?php foreach ($conversations as $c): ?>
                <a href="<?= url('/chat/' . (int) $c['id']) ?>" class="conv">
                    <div class="conv-titre"><?= e($c['titre_affiche'] ?? ($c['titre'] ?: 'Conversation')) ?></div>
                    <?php if (!empty($c['dernier_message'])): ?>
                        <div class="conv-extrait"><?= e(mb_strimwidth($c['dernier_message'], 0, 60, '…')) ?></div>
                    <?php endif; ?>
                    <div class="conv-meta"><?= $c['dernier_at'] ? datetime_fr($c['dernier_at']) : '' ?>
                        <?php if ((int) ($c['non_lus'] ?? 0) > 0): ?><span class="badge" style="background:var(--bleu-600)"><?= (int) $c['non_lus'] ?></span><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>