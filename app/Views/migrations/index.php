<?php
/**
 * Suivi des migrations de base de donnees (administrateurs).
 *
 * @var array $migrations
 * @var int   $enAttente
 */
?>
<div class="flex mb-2">
    <div style="flex:1">
        <p class="aide">
            Les migrations sont appliquées <strong>automatiquement</strong> au chargement des pages
            (à chaque <code>git pull</code> ajoutant un fichier dans <code>database/migrations/</code>).
            Cette page permet de vérifier l'état et de relancer manuellement en cas de besoin.
        </p>
    </div>
    <form method="post" action="<?= url('/parametres/migrations') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn">↻ Appliquer maintenant</button>
    </form>
</div>

<?php if ($enAttente > 0): ?>
    <div class="alerte alerte-warning">
        <strong><?= (int) $enAttente ?> migration(s) en attente.</strong>
        Elles devraient s'appliquer automatiquement ; si ce n'est pas le cas, cliquez sur
        « Appliquer maintenant ».
    </div>
<?php else: ?>
    <div class="alerte alerte-success">Base de donnees à jour.</div>
<?php endif; ?>

<div class="card">
    <div class="card-entete"><h3>Migrations</h3></div>
    <?php if (empty($migrations)): ?>
        <div class="vide"><div class="icone">🗄️</div><p>Aucun fichier de migration.</p></div>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th>Fichier</th><th>État</th><th>Appliquée le</th><th class="num">Taille</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($migrations as $m): ?>
                <tr>
                    <td class="mono petit"><?= e($m['migration']) ?></td>
                    <td>
                        <?php if ($m['applied']): ?>
                            <span class="badge" style="background:var(--vert,#198754);color:#fff">Appliquée</span>
                        <?php else: ?>
                            <span class="badge badge-contour">En attente</span>
                        <?php endif; ?>
                    </td>
                    <td class="petit"><?= $m['applied'] && $m['applied_at'] ? datetime_fr($m['applied_at']) : '—' ?></td>
                    <td class="num petit"><?= number_format((int) $m['taille'] / 1024, 1, ',', ' ') ?> Ko</td>
                </tr>
                <?php if (!empty($m['erreur'])): ?>
                    <tr>
                        <td colspan="4" class="petit" style="color:#b02a37;background:#fdf2f2">
                            ⚠ Dernière erreur : <?= e($m['erreur']) ?>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-entete"><h3>Comment ajouter une évolution</h3></div>
    <div class="card-corps">
        <ol class="aide" style="line-height:1.9;margin:0;padding-left:18px">
            <li>Créez un fichier <code>database/migrations/AAAA-MM-JJ-description.sql</code>.</li>
            <li>Écrivez le SQL souhaité (instructions séparées par des <code>;</code>).
                N'utilisez pas <code>DELIMITER</code> ni de procédures stockées.</li>
            <li>Rendez-le relançable sans dommage (idempotent), par exemple avec
                <code>CREATE TABLE IF NOT EXISTS</code> ou un garde <code>information_schema</code>.</li>
            <li>Committez : il sera appliqué automatiquement au prochain chargement de page.</li>
        </ol>
        <p class="aide mt-2">
            Ne modifiez jamais une migration déjà appliquée (elle ne sera pas rejouée) :
            ajoutez-en une nouvelle.
        </p>
    </div>
</div>
