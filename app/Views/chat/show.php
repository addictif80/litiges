<?php
/**
 * @var array $chat
 * @var string $titreChat
 * @var array $messages
 * @var array $membres
 */
?>
<style>
.conv {display:block;padding:8px 10px;border-bottom:1px solid var(--bordure);color:inherit}
.conv:hover {background:#f8fafb}
.conv-titre {font-weight:600}
.conv-extrait {font-size:12px;color:var(--gris);margin-top:1px}
.conv-meta {font-size:11px;color:var(--gris);margin-top:2px;display:flex;justify-content:space-between;align-items:center}
.chat-fil {display:flex;flex-direction:column;gap:8px;max-height:480px;overflow:auto;padding:8px}
.message {display:flex;flex-direction:column;max-width:70%;padding:6px 8px;border-radius:8px;background:#f1f5f9}
.message.mine {align-self:flex-end;background:var(--bleu-50);border:1px solid var(--bleu-100)}
.message-entete {font-size:11px;display:flex;justify-content:space-between;color:var(--gris)}
.message-corps {white-space:pre-wrap}
</style>

<div class="fil"><a href="<?= url('/chat') ?>">Messagerie</a> / <?= e($titreChat) ?></div>
<h2 class="mt-1"><?= e($titreChat) ?></h2>
<?php if (!empty($membres)): ?>
    <div class="petit texte-doux mb-1">Membres : <?= e(implode(', ', array_map(fn($m) => $m['full_name'] ?: $m['username'], $membres))) ?></div>
<?php endif; ?>

<div class="card">
    <div class="chat-fil" id="chat-fil" data-url="<?= url('/chat/' . (int) $chat['id'] . '/flux') ?>">
        <?php if (empty($messages)): ?>
            <div class="texte-doux">Aucun message pour le moment.</div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
                <?php $mine = ((int) ($m['user_id'] ?? 0) === (int) $authUser['id']); ?>
                <div class="message <?= $mine ? 'mine' : '' ?>" data-id="<?= (int) $m['id'] ?>">
                    <div class="message-entete">
                        <strong><?= e($m['user_nom'] ?: $m['username'] ?: 'Inconnu') ?></strong>
                        <span><?= datetime_fr($m['created_at'], true) ?></span>
                    </div>
                    <div class="message-corps"><?= e($m['contenu']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <span id="bas"></span>
    </div>
    <form method="post" action="<?= url('/chat/' . (int) $chat['id'] . '/messages') ?>" class="card-corps bordure-haut" style="display:flex;gap:8px">
        <?= csrf_field() ?>
        <textarea name="contenu" rows="1" placeholder="Votre message…" style="flex:1" required></textarea>
        <button type="submit" class="btn">Envoyer</button>
    </form>
</div>