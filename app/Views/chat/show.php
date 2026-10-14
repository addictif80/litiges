<?php
/**
 * @var array $chat
 * @var string $titreChat
 * @var array $messages
 * @var array $membres
 * @var array|null $authUser
 */
$authId = (int) ($authUser['id'] ?? 0);
?>

<div class="fil"><a href="<?= url('/chat') ?>">Messagerie</a> / <?= e($titreChat) ?></div>
<h2 class="mt-1"><?= e($titreChat) ?></h2>
<?php if (!empty($membres)): ?>
    <div class="petit texte-doux mb-1">Membres : <?= e(implode(', ', array_map(fn($m) => $m['full_name'] ?: $m['username'], $membres))) ?></div>
<?php endif; ?>

<div class="card">
    <div class="chat-fil" id="chat-fil" data-auth="<?= $authId ?>" data-url="<?= url('/chat/' . (int) $chat['id'] . '/flux') ?>">
        <?php if (empty($messages)): ?>
            <div class="texte-doux">Aucun message pour le moment.</div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
                <?php $mine = ((int) ($m['user_id'] ?? 0) === $authId); ?>
                <div class="message <?= $mine ? 'mine' : '' ?>" data-id="<?= (int) $m['id'] ?>">
                    <div class="message-entete">
                        <strong><?= e($m['user_nom'] ?: $m['username'] ?: 'Inconnu') ?></strong>
                        <span><?= datetime_fr($m['created_at']) ?></span>
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