<?php
/**
 * Import de donnees.
 * @var array $types
 * @var array $journal
 * @var array|null $rapport
 * @var bool $xlsx
 * @var bool $blgActif
 * @var string|null $blgDossier
 */
$niveaux = ['ok' => '✔', 'info' => 'ℹ', 'erreur' => '✘'];
?>
<?php if ($rapport): ?>
    <div class="card">
        <div class="card-entete"><h3>Résultat de l'import « <?= e($rapport['nom']) ?> »</h3></div>
        <div class="card-corps">
            <div class="grille grille-4 mb-2">
                <div class="stat accent-vert"><div class="libelle">Créés</div><div class="valeur"><?= (int) $rapport['crees'] ?></div></div>
                <div class="stat accent"><div class="libelle">Mis à jour</div><div class="valeur"><?= (int) $rapport['mis_a_jour'] ?></div></div>
                <div class="stat accent-orange"><div class="libelle">Ignorés</div><div class="valeur"><?= (int) $rapport['ignores'] ?></div></div>
                <div class="stat accent-rouge"><div class="libelle">Erreurs</div><div class="valeur"><?= (int) $rapport['erreurs'] ?></div></div>
            </div>
            <?php if (!empty($rapport['messages'])): ?>
                <div style="max-height:260px;overflow:auto" class="petit">
                    <?php foreach ($rapport['messages'] as $m): ?>
                        <div class="<?= $m['niveau'] === 'erreur' ? 'texte-rouge' : '' ?>"><?= $niveaux[$m['niveau']] ?? '' ?> ligne <?= (int) $m['ligne'] ?> : <?= e($m['texte']) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="grille grille-2">
    <div class="card">
        <div class="card-entete"><h3>⬆ Importer un fichier</h3></div>
        <form method="post" action="<?= url('/import/analyser') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="card-corps">
                <div class="form-groupe">
                    <label for="type">Que contient le fichier ?</label>
                    <select id="type" name="type" required>
                        <?php foreach ($types as $k => $lib): ?><option value="<?= e($k) ?>"><?= e($lib) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-groupe">
                    <label for="fichier">Fichier (.csv, .txt<?= $xlsx ? ', .xlsx' : '' ?>)</label>
                    <input type="file" id="fichier" name="fichier" accept=".csv,.txt<?= $xlsx ? ',.xlsx' : '' ?>" required>
                    <div class="aide">La première ligne doit contenir les noms de colonnes. Séparateur détecté automatiquement (; , tabulation). Vous vérifiez la correspondance des colonnes et une simulation avant toute écriture.</div>
                </div>
            </div>
            <div class="card-pied"><button type="submit" class="btn">Analyser le fichier</button></div>
        </form>
    </div>

    <div class="card">
        <div class="card-entete"><h3>🔗 ERP BLG (blgCloud)</h3></div>
        <div class="card-corps">
            <p>Exportez depuis BLG la liste des <strong>factures impayées</strong> et les <strong>règlements</strong> (CSV ou Excel), puis importez-les ici : les litiges sont créés ou mis à jour, et les règlements rapprochés automatiquement par numéro de facture.</p>
            <ul class="petit">
                <li>Import ponctuel : choisissez « Litiges » ou « Règlements » ci-contre.</li>
                <li>Import automatique : déposez les exports dans le dossier surveillé, le cron quotidien les traite.</li>
                <li>Les règlements déjà importés ne sont jamais comptés deux fois.</li>
            </ul>
            <p class="petit">
                Synchronisation automatique : <strong><?= $blgActif ? 'activée' : 'désactivée' ?></strong>
                <?php if ($blgDossier): ?> — dossier <code><?= e($blgDossier) ?></code><?php endif; ?>
                <?php if (\App\Core\Auth::isAdmin()): ?>(<a href="<?= url('/parametres#blg') ?>">paramètres</a>)<?php endif; ?>
            </p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-entete"><h3>Historique des imports</h3></div>
    <?php if (empty($journal)): ?>
        <div class="card-corps texte-doux">Aucun import pour le moment.</div>
    <?php else: ?>
        <table class="tableau tableau-compact">
            <thead><tr><th>Date</th><th>Source</th><th>Type</th><th>Fichier</th><th class="num">Lignes</th><th class="num">Créés</th><th class="num">MàJ</th><th class="num">Ignorés</th><th class="num">Erreurs</th></tr></thead>
            <tbody>
            <?php foreach ($journal as $j): ?>
                <tr>
                    <td class="petit"><?= datetime_fr($j['created_at']) ?><div class="texte-doux"><?= e($j['user_nom'] ?: '') ?></div></td>
                    <td class="petit"><?= e($j['source']) ?></td>
                    <td class="petit"><?= e($types[$j['type']] ?? $j['type']) ?></td>
                    <td class="petit"><?= e($j['fichier']) ?></td>
                    <td class="num"><?= (int) $j['lignes'] ?></td>
                    <td class="num"><?= (int) $j['crees'] ?></td>
                    <td class="num"><?= (int) $j['mis_a_jour'] ?></td>
                    <td class="num"><?= (int) $j['ignores'] ?></td>
                    <td class="num <?= (int) $j['erreurs'] > 0 ? 'texte-rouge gras' : '' ?>"><?= (int) $j['erreurs'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
