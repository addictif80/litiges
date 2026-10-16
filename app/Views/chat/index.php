<?php
/**
 * Messagerie : liste des conversations.
 *
 * @var array $conversations
 * @var array $users
 * @var array $agences
 * @var bool  $estAdmin
 */
?>
<div class="messagerie-entete">
    <div class="titre-zone">
        <h2>Messagerie</h2>
        <p class="aide">Discussions directes, groupes et canaux d'agence.</p>
    </div>
    <details class="nouvelle-conv">
        <summary class="btn">+ Nouvelle conversation</summary>
        <div class="card panneau">
            <form method="post" action="<?= url('/chat') ?>" class="card-corps">
                <?= csrf_field() ?>
                <div class="form-groupe">
                    <label for="type">Type de conversation</label>
                    <select id="type" name="type" data-bloc-switch>
                        <option value="direct">1-à-1</option>
                        <option value="groupe">Groupe</option>
                        <?php if ($estAdmin ?? false): ?><option value="agence">Canal d'agence</option><?php endif; ?>
                    </select>
                </div>

                <div data-bloc="direct">
                    <div class="form-groupe">
                        <label for="destinataire_id">Destinataire</label>
                        <select name="destinataire_id" id="destinataire_id">
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= e(nom_utilisateur($u)) ?><?= !empty($u['agence_nom']) ? ' (' . e($u['agence_nom']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div data-bloc="groupe" hidden>
                    <div class="form-groupe">
                        <label for="titre">Nom du groupe <span class="req">*</span></label>
                        <input type="text" id="titre" name="titre" maxlength="150">
                    </div>
                    <div class="form-groupe">
                        <label>Membres</label>
                        <div class="choix-membres">
                            <?php foreach ($users as $u): ?>
                                <label class="check">
                                    <input type="checkbox" name="membres[]" value="<?= (int) $u['id'] ?>">
                                    <?= e(nom_utilisateur($u)) ?><?= !empty($u['agence_nom']) ? ' <span class="texte-doux">(' . e($u['agence_nom']) . ')</span>' : '' ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($estAdmin ?? false): ?>
                <div data-bloc="agence" hidden>
                    <div class="form-groupe">
                        <label for="agence_id">Agence</label>
                        <select name="agence_id" id="agence_id">
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($agences as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"><?= e($a['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn">Créer la conversation</button>
            </form>
        </div>
    </details>
</div>

<div class="card">
    <?php if (empty($conversations)): ?>
        <div class="vide">
            <div class="icone">💬</div>
            <p>Aucune conversation pour le moment.</p>
            <p class="petit">Créez-en une avec « + Nouvelle conversation ».</p>
        </div>
    <?php else: ?>
        <div class="liste-conversations">
            <?php foreach ($conversations as $c): ?>
                <a href="<?= url('/chat/' . (int) $c['id']) ?>" class="conv">
                    <span class="conv-avatar <?= e($c['type']) ?>"><?= e($c['initiales']) ?></span>
                    <span class="conv-corps">
                        <span class="conv-ligne">
                            <span class="conv-titre">
                                <?= e($c['titre']) ?><?php if ($c['type'] === 'direct' && !empty($c['agence_nom'])): ?> <span class="conv-agence">(<?= e($c['agence_nom']) ?>)</span><?php endif; ?>
                            </span>
                            <span class="conv-quand" title="<?= e($c['dernier_at'] ? datetime_fr($c['dernier_at']) : '') ?>">
                                <?= $c['dernier_at'] ? e(relative_time($c['dernier_at'])) : '' ?>
                            </span>
                        </span>
                        <span class="conv-ligne">
                            <span class="conv-extrait">
                                <?php if (!empty($c['dernier_message'])): ?>
                                    <?= e(mb_strimwidth($c['dernier_message'], 0, 70, '…')) ?>
                                <?php else: ?>
                                    <em><?= e($c['type_libelle']) ?> · aucun message</em>
                                <?php endif; ?>
                            </span>
                            <?php if ((int) ($c['non_lus'] ?? 0) > 0): ?>
                                <span class="conv-badge"><?= (int) $c['non_lus'] ?></span>
                            <?php endif; ?>
                        </span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
