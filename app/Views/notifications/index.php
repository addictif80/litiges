<?php
/**
 * Liste des notifications internes de l'utilisateur connecte.
 * @var array $notifications
 */
$nonLues = 0;
foreach ($notifications as $n) {
    if ((int) $n['lu'] === 0) {
        $nonLues++;
    }
}
$icones = [
    'relance_due'       => '🔔',
    'promesse_depassee' => '⚠',
    'echeance_proche'   => '⏰',
];
?>
<div class="flex mb-2">
    <span class="petit texte-doux">
        <?= count($notifications) ?> notification(s) — <strong><?= (int) $nonLues ?></strong> non lue(s)
    </span>
    <div class="esp"></div>
    <?php if ($nonLues > 0): ?>
        <form method="post" action="<?= url('/notifications/lu') ?>" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondaire btn-petit">Tout marquer comme lu</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (empty($notifications)): ?>
        <div class="vide"><div class="icone">🔔</div><p>Aucune notification pour le moment.</p></div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <?php $nonLue = (int) $n['lu'] === 0; ?>
            <div style="display:flex;gap:13px;align-items:flex-start;padding:14px 18px;border-bottom:1px solid #eef2f7;<?= $nonLue ? 'background:#f7fbff' : '' ?>">
                <div style="font-size:20px;line-height:1.2"><?= $icones[$n['type']] ?? '🔔' ?></div>
                <div style="flex:1;min-width:0">
                    <div class="<?= $nonLue ? 'gras' : '' ?>"><?= e($n['message']) ?></div>
                    <div class="petit texte-doux">
                        <?= datetime_fr($n['created_at']) ?>
                        <?php if (!empty($n['reference'])): ?>
                            · <a href="<?= url('/litiges/' . (int) $n['litige_id']) ?>"><?= e($n['reference']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($nonLue): ?>
                    <form method="post" action="<?= url('/notifications/' . (int) $n['id'] . '/lu') ?>" style="margin:0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-lien">Marquer lu</button>
                    </form>
                <?php else: ?>
                    <span class="petit texte-doux">Lu</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
