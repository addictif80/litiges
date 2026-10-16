<?php
/**
 * Liste des modeles de courriers (administration).
 * @var array $modeles
 */
use App\Models\ModeleCourrier;
?>
<div class="flex mb-2">
    <a href="<?= url('/parametres/modeles/nouveau') ?>" class="btn">+ Nouveau modèle</a>
    <div class="esp"></div>
    <a href="<?= url('/parametres#societe') ?>" class="btn btn-secondaire">Coordonnées de la société</a>
</div>

<div class="alerte alerte-info mb-2">
    Les modèles fournis sont des exemples : <strong>faites-les valider par votre conseil juridique</strong> avant tout usage (mise en demeure, déclaration de créance).
</div>

<div class="card">
    <table class="tableau">
        <thead><tr><th>Modèle</th><th>Type</th><th>Code</th><th>Actif</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($modeles as $m): ?>
            <tr>
                <td><strong><?= e($m['nom']) ?></strong><?= (int) $m['systeme'] === 1 ? ' <span class="petit texte-doux">(fourni)</span>' : '' ?>
                    <div class="petit texte-doux"><?= e($m['sujet']) ?></div></td>
                <td class="petit"><?= e(ModeleCourrier::TYPES[$m['type']] ?? $m['type']) ?></td>
                <td class="mono petit"><?= e($m['code']) ?></td>
                <td class="petit"><?= (int) $m['actif'] === 1 ? 'Oui' : 'Non' ?></td>
                <td class="actions">
                    <a href="<?= url('/parametres/modeles/' . (int) $m['id'] . '/modifier') ?>" class="btn-lien">Modifier</a>
                    <?php if ((int) $m['systeme'] !== 1): ?>
                        <form method="post" action="<?= url('/parametres/modeles/' . (int) $m['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer ce modèle ?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-lien rouge">Suppr.</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
