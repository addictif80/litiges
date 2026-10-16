<?php
/**
 * Liste des litiges imprimable / convertible en PDF (document HTML autonome).
 * @var array $lignes
 * @var string[] $colonnes
 * @var int $nb
 */
$affichees = ['reference', 'raison_sociale', 'objet', 'statut_libelle', 'priorite', 'date_echeance', 'encours', 'responsable_nom'];
$total = 0.0;
foreach ($lignes as $l) {
    $total += (float) ($l['encours'] ?? 0);
}
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title><?= e($titre) ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #111; margin: 20px; }
        h1 { font-size: 15pt; margin: 0 0 4px; }
        .sous { color: #555; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #e9eef5; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; background: #f3f3f3; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1><?= e($titre) ?></h1>
    <div class="sous"><?= (int) $nb ?> litige(s) — édité le <?= e(date('d/m/Y à H:i')) ?></div>
    <table>
        <thead>
            <tr>
                <?php foreach ($affichees as $cle): ?>
                    <th class="<?= $cle === 'encours' ? 'num' : '' ?>"><?= e($colonnes[$cle] ?? $cle) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr>
                <?php foreach ($affichees as $cle): ?>
                    <?php $valeur = $l[$cle] ?? ''; ?>
                    <?php if ($cle === 'encours'): ?>
                        <td class="num"><?= e(money($valeur)) ?></td>
                    <?php elseif ($cle === 'date_echeance'): ?>
                        <td><?= e(date_fr($valeur ?: null)) ?></td>
                    <?php else: ?>
                        <td><?= e($valeur) ?></td>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="<?= count($affichees) - 2 ?>">Total de l'encours</td><td class="num"><?= e(money($total)) ?></td><td></td></tr>
        </tfoot>
    </table>
</body>
</html>
