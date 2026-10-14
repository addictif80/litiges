<?php
/**
 * Pagination reutilisable.
 * @var int $page
 * @var int $pages
 * Options : $pageParam (defaut "page")
 */
if (($pages ?? 1) <= 1) {
    return;
}
$page  = max(1, (int) $page);
$pages = (int) $pages;
$pageParam = $pageParam ?? 'page';

$params = $_GET;
unset($params[$pageParam]);
$base = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$lien = static function (int $p) use ($base, $params, $pageParam): string {
    $params[$pageParam] = $p;
    return $base . '?' . http_build_query($params);
};

$debut = max(1, $page - 2);
$fin   = min($pages, $page + 2);
?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e($lien(1)) ?>">&laquo;</a>
        <a href="<?= e($lien($page - 1)) ?>">&lsaquo;</a>
    <?php else: ?>
        <span class="inactif">&laquo;</span>
        <span class="inactif">&lsaquo;</span>
    <?php endif; ?>

    <?php if ($debut > 1): ?>
        <a href="<?= e($lien(1)) ?>">1</a>
        <?php if ($debut > 2): ?><span class="inactif">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($p = $debut; $p <= $fin; $p++): ?>
        <?php if ($p === $page): ?>
            <span class="actuel"><?= $p ?></span>
        <?php else: ?>
            <a href="<?= e($lien($p)) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($fin < $pages): ?>
        <?php if ($fin < $pages - 1): ?><span class="inactif">…</span><?php endif; ?>
        <a href="<?= e($lien($pages)) ?>"><?= $pages ?></a>
    <?php endif; ?>

    <?php if ($page < $pages): ?>
        <a href="<?= e($lien($page + 1)) ?>">&rsaquo;</a>
        <a href="<?= e($lien($pages)) ?>">&raquo;</a>
    <?php else: ?>
        <span class="inactif">&rsaquo;</span>
        <span class="inactif">&raquo;</span>
    <?php endif; ?>
</div>
