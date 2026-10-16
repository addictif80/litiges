<?php
/**
 * Correspondance des colonnes + simulation avant import.
 * @var array $session
 * @var string $type
 * @var string[] $entetes
 * @var array $echantillon
 * @var int $nbLignes
 * @var array $champs
 * @var array $mapping
 * @var array $options
 * @var array $simulation
 */
use App\Services\Import\ImportService;

$estAdmin = \App\Core\Auth::isAdmin();
$niveaux = ['ok' => '✔', 'info' => 'ℹ', 'erreur' => '✘'];
$manquants = [];
foreach ($champs as $champ => [$libelle, $requis]) {
    if ($requis && ($mapping[$champ] ?? null) === null) {
        $manquants[] = $libelle;
    }
}
?>
<div class="fil"><a href="<?= url('/import') ?>">Import</a> / <?= e(ImportService::TYPES[$type] ?? $type) ?> — <?= e($session['nom']) ?></div>

<form method="post" action="<?= url('/import/apercu') ?>" id="form-import">
    <?= csrf_field() ?>
    <div class="grille" style="grid-template-columns:1fr 1fr;align-items:start">
        <div class="card">
            <div class="card-entete"><h3>1. Correspondance des colonnes</h3></div>
            <div class="card-corps">
                <p class="aide">Associez chaque champ à une colonne de votre fichier (<?= (int) $nbLignes ?> ligne(s) de données).</p>
                <?php foreach ($champs as $champ => [$libelle, $requis]): ?>
                    <div class="form-groupe">
                        <label for="map_<?= e($champ) ?>"><?= e($libelle) ?><?= $requis ? ' <span class="req">*</span>' : '' ?></label>
                        <select id="map_<?= e($champ) ?>" name="map[<?= e($champ) ?>]">
                            <option value="">— ignorer —</option>
                            <?php foreach ($entetes as $i => $entete): ?>
                                <option value="<?= (int) $i ?>" <?= ($mapping[$champ] ?? null) === $i ? 'selected' : '' ?>><?= e($entete !== '' ? $entete : 'Colonne ' . ($i + 1)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>

                <h4 class="mt-2">Options</h4>
                <div class="form-groupe">
                    <label for="doublons">Si l'élément existe déjà</label>
                    <select id="doublons" name="doublons">
                        <option value="ignorer" <?= ($options['doublons'] ?? 'ignorer') === 'ignorer' ? 'selected' : '' ?>>L'ignorer</option>
                        <option value="mettre_a_jour" <?= ($options['doublons'] ?? '') === 'mettre_a_jour' ? 'selected' : '' ?>>Le mettre à jour</option>
                    </select>
                </div>
                <?php if ($type === 'litiges'): ?>
                    <label class="check"><input type="checkbox" name="creer_clients" value="1" <?= !empty($options['creer_clients']) ? 'checked' : '' ?>> Créer les clients introuvables (nom seul, sans données légales)</label>
                <?php endif; ?>
            </div>
            <div class="card-pied flex">
                <button type="submit" class="btn btn-secondaire">Actualiser la simulation</button>
                <?php if ($estAdmin && isset(\App\Services\Import\BlgService::PROFILS[$type])): ?>
                    <button type="submit" class="btn btn-secondaire" formaction="<?= url('/import/profil') ?>" title="Utilisé par la synchronisation automatique BLG">Enregistrer comme profil BLG</button>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-entete"><h3>Aperçu du fichier</h3></div>
                <div style="overflow:auto">
                    <table class="tableau tableau-compact">
                        <thead><tr><?php foreach ($entetes as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach ($echantillon as $ligne): ?>
                            <tr><?php foreach ($entetes as $i => $_): ?><td class="petit"><?= e(truncate((string) ($ligne[$i] ?? ''), 40)) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-entete"><h3>2. Simulation (rien n'est encore écrit)</h3></div>
                <div class="card-corps">
                    <div class="grille grille-4 mb-2">
                        <div class="stat accent-vert"><div class="libelle">À créer</div><div class="valeur"><?= (int) $simulation['crees'] ?></div></div>
                        <div class="stat accent"><div class="libelle">À mettre à jour</div><div class="valeur"><?= (int) $simulation['mis_a_jour'] ?></div></div>
                        <div class="stat accent-orange"><div class="libelle">Ignorés</div><div class="valeur"><?= (int) $simulation['ignores'] ?></div></div>
                        <div class="stat accent-rouge"><div class="libelle">Erreurs</div><div class="valeur"><?= (int) $simulation['erreurs'] ?></div></div>
                    </div>
                    <?php if (!empty($simulation['messages'])): ?>
                        <div style="max-height:240px;overflow:auto" class="petit">
                            <?php foreach (array_slice($simulation['messages'], 0, 80) as $m): ?>
                                <div class="<?= $m['niveau'] === 'erreur' ? 'texte-rouge' : '' ?>"><?= $niveaux[$m['niveau']] ?? '' ?> ligne <?= (int) $m['ligne'] ?> : <?= e($m['texte']) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-pied flex">
                    <?php if ($manquants !== []): ?>
                        <span class="texte-rouge petit">Champs obligatoires à associer : <?= e(implode(', ', $manquants)) ?></span>
                    <?php else: ?>
                        <button type="submit" class="btn btn-vert" formaction="<?= url('/import/executer') ?>">3. Lancer l'import</button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-secondaire" formaction="<?= url('/import/annuler') ?>" formnovalidate>Annuler</button>
                </div>
            </div>
        </div>
    </div>
</form>
