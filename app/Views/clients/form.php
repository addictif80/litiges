<?php
/**
 * Formulaire client avec recuperation automatique des donnees (SIREN).
 * @var array $client
 * @var string $mode
 */
$estEdition = $mode === 'edition';
$action = $estEdition ? url('/clients/' . (int) $client['id'] . '/modifier') : url('/clients');
$v = static function (string $cle, mixed $defaut = '') use ($client) {
    return old($cle, $client[$cle] ?? $defaut);
};
$err = static fn (string $cle): ?string => $errors[$cle] ?? null;
$sourcesOk = (new \App\Services\CompanyLookupService())->sourcesDisponibles();
?>
<div class="fil"><a href="<?= url('/clients') ?>">Clients</a> / <?= $estEdition ? 'Modifier' : 'Nouveau' ?></div>

<div class="card" id="zone-api"
     data-url-recherche="<?= url('/api/entreprises') ?>"
     data-url-detail="<?= url('/api/entreprises') ?>">
    <div class="card-entete">
        <h3>🔎 Récupération automatique des données entreprise</h3>
        <div class="esp"></div>
        <span class="petit texte-doux">
            Source :
            <?= $sourcesOk['pappers'] ? 'Pappers' : 'API Recherche d\'entreprises (État, gratuite)' ?>
        </span>
    </div>
    <div class="card-corps">
        <div class="flex">
            <input type="search" data-api-recherche placeholder="Nom de l'entreprise, SIREN ou SIRET…" style="max-width:460px" autocomplete="off">
            <button type="button" class="btn btn-secondaire" data-api-detail>SIREN → Charger</button>
        </div>
        <div class="aide">Saisissez au moins 2 caractères puis cliquez sur un résultat pour pré-remplir la fiche.</div>
        <div class="resultats-api" data-api-resultats style="display:none"></div>
        <script>document.querySelector('[data-api-resultats]').style.display='block';</script>
    </div>
</div>

<form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>

    <div class="grille grille-2">
        <div class="card">
            <div class="card-entete"><h3>Identité</h3></div>
            <div class="card-corps">
                <div class="form-groupe">
                    <label for="raison_sociale">Raison sociale <span class="req">*</span></label>
                    <input type="text" id="raison_sociale" name="raison_sociale" value="<?= e($v('raison_sociale')) ?>" required maxlength="190">
                    <?php if ($err('raison_sociale')): ?><div class="msg-erreur"><?= e($err('raison_sociale')) ?></div><?php endif; ?>
                </div>
                <div class="ligne-champs">
                    <div class="form-groupe">
                        <label for="siren">SIREN</label>
                        <input type="text" id="siren" name="siren" value="<?= e($v('siren')) ?>" data-api-siren maxlength="9" placeholder="9 chiffres">
                        <?php if ($err('siren')): ?><div class="msg-erreur"><?= e($err('siren')) ?></div><?php endif; ?>
                    </div>
                    <div class="form-groupe">
                        <label for="siret">SIRET (siège)</label>
                        <input type="text" id="siret" name="siret" value="<?= e($v('siret')) ?>" maxlength="14" placeholder="14 chiffres">
                        <?php if ($err('siret')): ?><div class="msg-erreur"><?= e($err('siret')) ?></div><?php endif; ?>
                    </div>
                    <div class="form-groupe">
                        <label for="tva_intra">N° TVA intracommunautaire</label>
                        <input type="text" id="tva_intra" name="tva_intra" value="<?= e($v('tva_intra')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="forme_juridique">Forme juridique</label>
                        <input type="text" id="forme_juridique" name="forme_juridique" value="<?= e($v('forme_juridique')) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Adresse</h3></div>
            <div class="card-corps">
                <div class="form-groupe">
                    <label for="adresse">Adresse</label>
                    <input type="text" id="adresse" name="adresse" value="<?= e($v('adresse')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="complement_adresse">Complément d'adresse</label>
                    <input type="text" id="complement_adresse" name="complement_adresse" value="<?= e($v('complement_adresse')) ?>">
                </div>
                <div class="ligne-champs">
                    <div class="form-groupe">
                        <label for="code_postal">Code postal</label>
                        <input type="text" id="code_postal" name="code_postal" value="<?= e($v('code_postal')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="ville">Ville</label>
                        <input type="text" id="ville" name="ville" value="<?= e($v('ville')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="pays">Pays</label>
                        <input type="text" id="pays" name="pays" value="<?= e($v('pays', 'France')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grille grille-2">
        <div class="card">
            <div class="card-entete"><h3>Données économiques</h3></div>
            <div class="card-corps">
                <div class="ligne-champs">
                    <div class="form-groupe">
                        <label for="ape_naf">Code APE/NAF</label>
                        <input type="text" id="ape_naf" name="ape_naf" value="<?= e($v('ape_naf')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="libelle_ape">Activité</label>
                        <input type="text" id="libelle_ape" name="libelle_ape" value="<?= e($v('libelle_ape')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="effectif">Effectif</label>
                        <input type="text" id="effectif" name="effectif" value="<?= e($v('effectif')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="date_creation">Date de création</label>
                        <input type="date" id="date_creation" name="date_creation" value="<?= e(substr((string) $v('date_creation'), 0, 10)) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="capital">Capital social (€)</label>
                        <input type="text" inputmode="decimal" id="capital" name="capital" value="<?= e($v('capital')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="chiffre_affaires">Chiffre d'affaires (€)</label>
                        <input type="text" inputmode="decimal" id="chiffre_affaires" name="chiffre_affaires" value="<?= e($v('chiffre_affaires')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="resultat_net">Résultat net (€)</label>
                        <input type="text" inputmode="decimal" id="resultat_net" name="resultat_net" value="<?= e($v('resultat_net')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="score_solvabilite">Score / solvabilité</label>
                        <input type="text" id="score_solvabilite" name="score_solvabilite" value="<?= e($v('score_solvabilite')) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Situation &amp; notes</h3></div>
            <div class="card-corps">
                <div class="form-groupe">
                    <label for="statut_entreprise">Situation de l'entreprise</label>
                    <input type="text" id="statut_entreprise" name="statut_entreprise" value="<?= e($v('statut_entreprise')) ?>" placeholder="Ex : Active, Cessée, Radiée…">
                </div>
                <div class="form-groupe">
                    <label for="procedure_collective">Procédure collective</label>
                    <input type="text" id="procedure_collective" name="procedure_collective" value="<?= e($v('procedure_collective')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="notes">Notes internes</label>
                    <textarea id="notes" name="notes" rows="4"><?= e($v('notes')) ?></textarea>
                </div>
                <div class="form-groupe">
                    <label class="check">
                        <input type="checkbox" name="actif" value="1" <?= (int) $v('actif', 1) === 1 ? 'checked' : '' ?>>
                        Client actif
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-corps flex">
            <button type="submit" class="btn btn-vert"><?= $estEdition ? 'Enregistrer' : 'Créer le client' ?></button>
            <a href="<?= $estEdition ? url('/clients/' . (int) $client['id']) : url('/clients') ?>" class="btn btn-secondaire">Annuler</a>
        </div>
    </div>
</form>
