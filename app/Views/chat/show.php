<?php
/**
 * Messagerie : conversation ouverte.
 *
 * @var array      $chat
 * @var array      $entete      Titre / agence / initiales / type de la conversation
 * @var string     $titreChat
 * @var array      $messages
 * @var array      $membres
 * @var array|null $authUser
 */
$authId = (int) ($authUser['id'] ?? 0);
?>
<div class="fil"><a href="<?= url('/chat') ?>">Messagerie</a> / <?= e($titreChat) ?></div>

<div class="chat-entete">
    <span class="conv-avatar <?= e($entete['type']) ?>"><?= e($entete['initiales']) ?></span>
    <div class="chat-entete-texte">
        <div class="chat-entete-titre">
            <?= e($entete['titre']) ?><?php if ($entete['type'] === 'direct' && !empty($entete['agence_nom'])): ?> <span class="conv-agence">(<?= e($entete['agence_nom']) ?>)</span><?php endif; ?>
        </div>
        <div class="chat-entete-sous"><?= e($entete['type_libelle']) ?></div>
        <?php if (!empty($membres)): ?>
            <div class="chat-membres">
                <?php foreach ($membres as $mb): ?>
                    <span class="chat-membre">
                        <?= e(nom_utilisateur($mb)) ?><?= !empty($mb['agence_nom']) ? ' (' . e($mb['agence_nom']) . ')' : '' ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card card-chat">
    <div class="chat-fil" id="chat-fil" data-auth="<?= $authId ?>" data-url="<?= url('/chat/' . (int) $chat['id'] . '/flux') ?>">
        <?php if (empty($messages)): ?>
            <div class="vide">
                <div class="icone">💬</div>
                <p>Aucun message pour le moment.</p>
                <p class="petit">Écrivez le premier message ci-dessous.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
                <?php $mine = ((int) ($m['user_id'] ?? 0) === $authId); ?>
                <div class="message <?= $mine ? 'mine' : '' ?>" data-id="<?= (int) $m['id'] ?>">
                    <div class="message-entete">
                        <span class="message-auteur">
                            <strong><?= e(nom_utilisateur($m)) ?></strong><?php if (!empty($m['agence_nom'])): ?> <span class="message-agence">(<?= e($m['agence_nom']) ?>)</span><?php endif; ?>
                        </span>
                        <span class="message-quand"><?= datetime_fr($m['created_at']) ?></span>
                    </div>
                    <div class="message-corps"><?= e($m['contenu']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <span id="bas"></span>
    </div>
    <form method="post" action="<?= url('/chat/' . (int) $chat['id'] . '/messages') ?>" class="chat-compose">
        <?= csrf_field() ?>
        <textarea name="contenu" rows="1" placeholder="Écrivez votre message…" required></textarea>
        <button type="submit" class="btn">Envoyer</button>
    </form>
</div>
