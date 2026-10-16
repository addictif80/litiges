<?php
/**
 * Formulaire de creation / modification d'un litige.
 * @var array $litige
 * @var string $mode
 */
$estEdition = $mode === 'edition';
$action = $estEdition ? url('/litiges/' . (int) $litige['id'] . '/modifier') : url('/litiges');
$v = static function (string $cle, mixed $defaut = '') use ($litige) {
    return old($cle, $litige[$cle] ?? $defaut);
};
$err = static fn (string $cle): ?string => $errors[$cle] ?? null;
?>
<div class="fil"><a href="<?= url('/litiges') ?>">Litiges</a> / <?= $estEdition ? 'Modifier' : 'Nouveau' ?></div>

<?php if (!empty($doublons)): ?>
    <div class="alerte alerte-warning mb-2">
        <strong>⚠ Un ou plusieurs litiges semblent déjà exister pour ce client :</strong>
        <ul style="margin:6px 0 0 18px">
            <?php foreach ($doublons as $d): ?>
                <li><a href="<?= url('/litiges/' . (int) $d['id']) ?>" target="_blank" rel="noopener"><?= e($d['reference']) ?></a>
                    — <?= e($d['objet']) ?> (<?= e($d['statut_libelle']) ?>, <?= money($d['montant_principal']) ?><?= $d['numero_facture'] ? ', facture ' . e($d['numero_facture']) : '' ?>)</li>
            <?php endforeach; ?>
        </ul>
        <label class="check mt-1"><input type="checkbox" name="confirmer_doublon" value="1" form="form-litige"> Créer quand même ce litige</label>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" id="form-litige" class="grille" style="grid-template-columns:2fr 1fr;align-items:start">
    <?= csrf_field() ?>

    <div>
        <div class="card">
            <div class="card-entete"><h3>Informations générales</h3></div>
            <div class="card-corps">
                <div class="ligne-champs">
                    <div class="form-groupe" style="grid-column:span 2">
                        <label for="client_id">Client <span class="req">*</span></label>
                        <div data-client-picker data-requis data-nom="client_id" data-url="<?= url('/api/clients/suggestions') ?>"
                             data-valeur="<?= (int) $v('client_id') ?: '' ?>"
                             data-libelle="<?= e($clientPre ? $clientPre['raison_sociale'] . ($clientPre['siren'] ? ' (' . $clientPre['siren'] . ')' : '') : '') ?>"></div>
                        <?php if ($err('client_id')): ?><div class="msg-erreur"><?= e($err('client_id')) ?></div><?php endif; ?>
                        <div class="aide">Client absent ? <a href="<?= url('/clients/nouveau') ?>" target="_blank">Créer une fiche client</a> (les données seront récupérées automatiquement via SIREN).</div>
                    </div>

                    <div class="form-groupe" style="grid-column:span 2">
                        <label for="objet">Objet du litige <span class="req">*</span></label>
                        <input type="text" id="objet" name="objet" value="<?= e($v('objet')) ?>" maxlength="190" required
                               placeholder="Ex : Impayé facture location pelle 2,5 t — chantier Lyon">
                        <?php if ($err('objet')): ?><div class="msg-erreur"><?= e($err('objet')) ?></div><?php endif; ?>
                    </div>

                    <div class="form-groupe">
                        <label for="type_litige">Type de litige</label>
                        <select id="type_litige" name="type_litige">
                            <option value="">— Non précisé —</option>
                            <?php foreach ($types as $k => $lib): ?>
                                <option value="<?= e($k) ?>" <?= $v('type_litige') === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-groupe">
                        <label for="priorite">Priorité</label>
                        <select id="priorite" name="priorite">
                            <?php foreach ($priorites as $k => $lib): ?>
                                <option value="<?= e($k) ?>" <?= $v('priorite', 'normale') === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-groupe">
                    <label for="description">Description / contexte</label>
                    <textarea id="description" name="description" rows="4"><?= e($v('description')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Montants</h3></div>
            <div class="card-corps">
                <div class="ligne-champs">
                    <div class="form-groupe">
                        <label for="montant_principal">Montant principal (€) <span class="req">*</span></label>
                        <input type="text" inputmode="decimal" id="montant_principal" name="montant_principal" value="<?= e($v('montant_principal', '0')) ?>">
                        <?php if ($err('montant_principal')): ?><div class="msg-erreur"><?= e($err('montant_principal')) ?></div><?php endif; ?>
                    </div>
                    <div class="form-groupe">
                        <label for="montant_penalites">Pénalités / frais (€)</label>
                        <input type="text" inputmode="decimal" id="montant_penalites" name="montant_penalites" value="<?= e($v('montant_penalites', '0')) ?>"
                               <?= (int) $v('penalites_auto', 0) === 1 ? 'readonly' : '' ?>>
                        <?php if ((int) $v('penalites_auto', 0) === 1): ?><div class="aide">Calculé automatiquement chaque jour.</div><?php endif; ?>
                    </div>
                    <div class="form-groupe">
                        <label>Montant récupéré (€)</label>
                        <div class="gras" style="padding:9px 0"><?= money($litige['montant_recupere'] ?? 0) ?></div>
                        <div class="aide">Somme des paiements enregistrés sur la fiche du litige.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Pénalités de retard</h3></div>
            <div class="card-corps">
                <label class="check"><input type="checkbox" name="penalites_auto" value="1" <?= (int) $v('penalites_auto', 0) === 1 ? 'checked' : '' ?>> Calculer automatiquement les pénalités (intérêts de retard + indemnité)</label>
                <label class="check"><input type="checkbox" name="indemnite_forfaitaire" value="1" <?= (int) $v('indemnite_forfaitaire', 0) === 1 ? 'checked' : '' ?>> Ajouter l'indemnité forfaitaire de recouvrement</label>
                <div class="ligne-champs mt-1">
                    <div class="form-groupe">
                        <label for="taux_penalite">Taux annuel spécifique (%)</label>
                        <input type="text" inputmode="decimal" id="taux_penalite" name="taux_penalite" value="<?= e($v('taux_penalite', '')) ?>" placeholder="Taux par défaut des paramètres">
                    </div>
                    <div class="form-groupe">
                        <label for="penalites_depuis">Pénalités dues depuis le</label>
                        <input type="date" id="penalites_depuis" name="penalites_depuis" value="<?= e($v('penalites_depuis', '')) ?>">
                        <div class="aide">Par défaut : date d'échéance, sinon de facture.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Références dossier</h3></div>
            <div class="card-corps">
                <div class="ligne-champs">
                    <div class="form-groupe">
                        <label for="numero_facture">N° de facture</label>
                        <input type="text" id="numero_facture" name="numero_facture" value="<?= e($v('numero_facture')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="date_facture">Date de facture</label>
                        <input type="date" id="date_facture" name="date_facture" value="<?= e($v('date_facture')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="numero_contrat">N° de contrat / location</label>
                        <input type="text" id="numero_contrat" name="numero_contrat" value="<?= e($v('numero_contrat')) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="materiel">Matériel / véhicule</label>
                        <input type="text" id="materiel" name="materiel" value="<?= e($v('materiel')) ?>" placeholder="Ex : mini-pelle 2,5 t — AB-123-CD">
                    </div>
                    <div class="form-groupe">
                        <label for="reference_externe">Référence externe</label>
                        <input type="text" id="reference_externe" name="reference_externe" value="<?= e($v('reference_externe')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-entete"><h3>Suivi</h3></div>
            <div class="card-corps">
                <div class="form-groupe">
                    <label for="statut_id">Statut <span class="req">*</span></label>
                    <select id="statut_id" name="statut_id" required>
                        <?php foreach ($statuts as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) $v('statut_id') === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($err('statut_id')): ?><div class="msg-erreur"><?= e($err('statut_id')) ?></div><?php endif; ?>
                <?php if (\App\Core\Auth::isAdmin() && $estEdition): ?>
                    <label class="check mb-2"><input type="checkbox" name="forcer_workflow" value="1"> Forcer le statut (ignorer le workflow)</label>
                <?php endif; ?>
                <div class="form-groupe">
                    <label for="finalite_id">Finalité (si clôturé)</label>
                    <select id="finalite_id" name="finalite_id">
                        <option value="">—</option>
                        <?php foreach ($finalites as $f): ?>
                            <option value="<?= (int) $f['id'] ?>" <?= (int) $v('finalite_id') === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-groupe">
                    <label for="responsable_id">Gestionnaire en charge</label>
                    <select id="responsable_id" name="responsable_id">
                        <option value="">— Non affecté —</option>
                        <?php foreach ($responsables as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (int) $v('responsable_id') === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-groupe">
                    <label for="agence_id">Agence</label>
                    <select id="agence_id" name="agence_id">
                        <option value="">—</option>
                        <?php foreach ($agences as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) $v('agence_id') === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Dates &amp; relances</h3></div>
            <div class="card-corps">
                <div class="form-groupe">
                    <label for="date_ouverture">Date d'ouverture <span class="req">*</span></label>
                    <input type="date" id="date_ouverture" name="date_ouverture" value="<?= e($v('date_ouverture', date('Y-m-d'))) ?>" required>
                    <?php if ($err('date_ouverture')): ?><div class="msg-erreur"><?= e($err('date_ouverture')) ?></div><?php endif; ?>
                </div>
                <div class="form-groupe">
                    <label for="date_echeance">Échéance / deadline</label>
                    <input type="date" id="date_echeance" name="date_echeance" value="<?= e($v('date_echeance')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="delai_relance_jours">Délai de relance (jours)</label>
                    <input type="number" id="delai_relance_jours" name="delai_relance_jours" min="1" max="365" value="<?= e($v('delai_relance_jours', 15)) ?>">
                    <div class="aide">La prochaine relance est calculée automatiquement à partir de la dernière action.</div>
                </div>
                <div class="form-groupe">
                    <label for="prochaine_relance_at">Forcer la prochaine relance</label>
                    <input type="date" id="prochaine_relance_at" name="prochaine_relance_at"
                           value="<?= e(substr((string) $v('prochaine_relance_at'), 0, 10)) ?>">
                    <div class="aide">Laissez vide pour un calcul automatique.</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-corps flex">
                <button type="submit" class="btn btn-vert"><?= $estEdition ? 'Enregistrer' : 'Créer le litige' ?></button>
                <a href="<?= $estEdition ? url('/litiges/' . (int) $litige['id']) : url('/litiges') ?>" class="btn btn-secondaire">Annuler</a>
            </div>
        </div>
    </div>
</form>
