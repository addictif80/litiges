<?php
/**
 * Fiche client : identite, contacts, litiges, documents.
 * @var array $client
 */
$peutEcrire = \App\Core\Auth::canWrite();
$estAdmin = \App\Core\Auth::isAdmin();
$upload = new \App\Services\UploadService();
$encours = 0.0;
foreach ($litiges as $l) {
    if ((int) ($l['statut_cloturant'] ?? 0) === 0) {
        $encours += (float) $l['montant_principal'] + (float) $l['montant_penalites'] - (float) $l['montant_recupere'];
    }
}
$solvabilite = (new \App\Services\SolvabiliteService())->calculer($client, $encours);

// Bloc légal réutilisable : forme juridique et APE avec libellés, dirigeants.
$entreprise = [
    'forme_juridique' => $client['forme_juridique'] ?? null,
    'ape_naf'         => $client['ape_naf'] ?? null,
    'libelle_ape'     => $client['libelle_ape'] ?? null,
    'dirigeants'      => \App\Services\CompanyLookupService::dirigeantsFromRaw($client['raw_data'] ?? null),
];
?>
<div class="fil"><a href="<?= url('/clients') ?>">Clients</a> / <?= e($client['raison_sociale']) ?></div>

<div class="entete-fiche">
    <div class="infos" style="flex:1;min-width:280px">
        <h1><?= e($client['raison_sociale']) ?>
            <?php if (!empty($client['procedure_collective'])): ?><span class="badge" style="background:var(--rouge)">Procédure collective</span><?php endif; ?>
            <?php if ((int) $client['actif'] !== 1): ?><span class="badge" style="background:var(--gris)">Inactif</span><?php endif; ?>
        </h1>
        <div class="meta">
            <?php if ($client['siren']): ?><span class="mono">SIREN <?= e($client['siren']) ?></span><?php endif; ?>
            <?php if ($client['ville']): ?><span>📍 <?= e($client['code_postal'] ? $client['code_postal'] . ' ' : '') ?><?= e($client['ville']) ?></span><?php endif; ?>
            <?php if ($client['statut_entreprise']): ?><span>Situation : <?= e($client['statut_entreprise']) ?></span><?php endif; ?>
            <?php if ($client['data_synced_at']): ?><span class="petit">Données API : <?= datetime_fr($client['data_synced_at']) ?> (<?= e($client['source_api'] ?: '—') ?>)</span><?php endif; ?>
        </div>
    </div>
    <div class="flex">
        <a href="<?= url('/litiges/nouveau?client_id=' . (int) $client['id']) ?>" class="btn">+ Nouveau litige</a>
                <?php if ($peutEcrire): ?>
                    <form method="post" action="<?= url('/clients/' . (int) $client['id'] . '/rafraichir') ?>" data-confirmer="Recharger les données légales depuis l'API ?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondaire" <?= empty($client['siren']) ? 'disabled title="Renseignez un SIREN"' : '' ?>>⟳ Actualiser via API</button>
                    </form>
                    <a href="<?= url('/clients/' . (int) $client['id'] . '/modifier') ?>" class="btn btn-secondaire">✎ Modifier</a>
                <?php endif; ?>
        <?php if ($estAdmin): ?>
            <form method="post" action="<?= url('/clients/' . (int) $client['id'] . '/supprimer') ?>" data-confirmer="Supprimer ce client ?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-rouge">🗑</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grille grille-4 mb-2">
    <div class="stat accent"><div class="libelle">Litiges</div><div class="valeur"><?= count($litiges) ?></div></div>
    <div class="stat accent-rouge"><div class="libelle">Encours client</div><div class="valeur"><?= money($encours) ?></div></div>
    <div class="stat accent"><div class="libelle">Effectif</div><div class="valeur" style="font-size:18px"><?= e($client['effectif'] ?: '—') ?></div></div>
    <div class="stat accent-vert"><div class="libelle">Chiffre d'affaires</div><div class="valeur" style="font-size:18px"><?= $client['chiffre_affaires'] !== null ? money($client['chiffre_affaires']) : '—' ?></div>
        <?php if ($client['annee_finances']): ?><div class="detail">Exercice <?= (int) $client['annee_finances'] ?></div><?php endif; ?>
    </div>
</div>

<div class="grille" style="grid-template-columns:2fr 1fr;align-items:start">
    <div>
        <div class="card">
            <div class="card-entete"><h3>Litiges du client</h3></div>
            <?php if (empty($litiges)): ?>
                <div class="card-corps texte-doux">Aucun litige enregistré. <a href="<?= url('/litiges/nouveau?client_id=' . (int) $client['id']) ?>">Créer un litige</a>.</div>
            <?php else: ?>
                <table class="tableau tableau-compact">
                    <thead><tr><th>Référence</th><th>Objet</th><th>Statut</th><th>Ouverture</th><th class="num">Encours</th></tr></thead>
                    <tbody>
                    <?php foreach ($litiges as $l): ?>
                        <tr>
                            <td><a href="<?= url('/litiges/' . (int) $l['id']) ?>" class="mono gras"><?= e($l['reference']) ?></a></td>
                            <td><?= e(truncate($l['objet'], 46)) ?></td>
                            <td><span class="badge" style="background:<?= e($l['statut_couleur']) ?>"><?= e($l['statut_libelle']) ?></span></td>
                            <td class="petit"><?= date_fr($l['date_ouverture']) ?></td>
                            <td class="num"><?= money((float) $l['montant_principal'] + (float) $l['montant_penalites'] - (float) $l['montant_recupere']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card" id="contacts">
            <div class="card-entete"><h3>Contacts</h3></div>
            <?php if (!empty($contacts)): ?>
                <table class="tableau tableau-compact">
                    <thead><tr><th>Nom</th><th>Fonction</th><th>Coordonnées</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($contacts as $c): ?>
                        <tr>
                            <td><strong><?= e(trim(($c['prenom'] ?? '') . ' ' . $c['nom'])) ?></strong>
                                <?php if ((int) $c['principal'] === 1): ?> <span class="badge badge-contour">Principal</span><?php endif; ?>
                            </td>
                            <td class="petit"><?= e($c['fonction'] ?: '—') ?></td>
                            <td class="petit">
                                <?php if ($c['telephone']): ?>☎ <?= e($c['telephone']) ?><br><?php endif; ?>
                                <?php if ($c['email']): ?>✉ <?= e($c['email']) ?><?php endif; ?>
                            </td>
                            <td class="actions">
                    <?php if ($peutEcrire): ?>
                        <form method="post" action="<?= url('/clients/' . (int) $client['id'] . '/contacts/' . (int) $c['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer ce contact ?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-lien rouge">Suppr.</button>
                        </form>
                    <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="card-corps texte-doux">Aucun contact.</div>
            <?php endif; ?>

            <?php if ($peutEcrire): ?>
                <div class="card-pied">
                    <details>
                        <summary style="cursor:pointer;font-weight:600;color:var(--bleu-600)">+ Ajouter un contact</summary>
                        <form method="post" action="<?= url('/clients/' . (int) $client['id'] . '/contacts') ?>" class="mt-2">
                            <?= csrf_field() ?>
                            <div class="ligne-champs">
                                <div class="form-groupe"><label>Civilité</label>
                                    <select name="civilite"><option value="">—</option><option>M.</option><option>Mme</option></select></div>
                                <div class="form-groupe"><label>Nom <span class="req">*</span></label><input type="text" name="nom" required></div>
                                <div class="form-groupe"><label>Prénom</label><input type="text" name="prenom"></div>
                                <div class="form-groupe"><label>Fonction</label><input type="text" name="fonction"></div>
                                <div class="form-groupe"><label>Téléphone</label><input type="tel" name="telephone"></div>
                                <div class="form-groupe"><label>Mobile</label><input type="tel" name="mobile"></div>
                                <div class="form-groupe"><label>Email</label><input type="email" name="email"></div>
                                <div class="form-groupe"><label>Type</label><input type="text" name="type" placeholder="Ex : comptabilité"></div>
                            </div>
                            <label class="check mb-2"><input type="checkbox" name="principal" value="1"> Contact principal</label>
                            <button type="submit" class="btn btn-petit">Ajouter</button>
                        </form>
                    </details>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" id="documents">
            <div class="card-entete"><h3>Documents</h3></div>
            <?php if (!empty($documents)): ?>
                <table class="tableau tableau-compact">
                    <tbody>
                    <?php foreach ($documents as $d): ?>
                        <tr>
                            <td>📎 <?= e($d['nom_original']) ?><div class="petit texte-doux"><?= datetime_fr($d['created_at']) ?></div></td>
                            <td class="petit"><?= e($upload->tailleLisible((int) $d['taille'])) ?></td>
                            <td class="actions">
                                <a href="<?= url('/documents/' . (int) $d['id'] . '/telecharger') ?>" class="btn-lien">Télécharger</a>
                                <?php if ($peutEcrire && ($estAdmin || (int) $d['uploaded_by'] === (int) $authUser['id'])): ?>
                                    <form method="post" action="<?= url('/documents/' . (int) $d['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer ce document ?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-lien rouge">Suppr.</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php if ($peutEcrire): ?>
                <div class="card-pied">
                    <form method="post" action="<?= url('/clients/' . (int) $client['id'] . '/documents') ?>" enctype="multipart/form-data" class="flex">
                        <?= csrf_field() ?>
                        <input type="file" name="fichier" required style="max-width:320px">
                        <input type="text" name="categorie" placeholder="Catégorie" style="max-width:180px">
                        <button type="submit" class="btn btn-petit">Téléverser</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-entete">
                <h3>Informations légales</h3>
                <?php if (!empty($client['siren'])): ?>
                    <div class="esp"></div>
                    <a href="<?= url('/entreprise/' . rawurlencode((string) $client['siren'])) ?>" class="btn-lien">Fiche complète ↗</a>
                <?php endif; ?>
            </div>
            <div class="card-corps">
                <?php $e = $entreprise; include __DIR__ . '/../partials/infos_legales.php'; ?>

                <dl class="definition mt-2" style="grid-template-columns:140px 1fr">
                    <dt>SIREN</dt><dd class="mono"><?= e($client['siren'] ?: '—') ?></dd>
                    <dt>SIRET</dt><dd class="mono petit"><?= e($client['siret'] ?: '—') ?></dd>
                    <dt>TVA intra.</dt><dd><?= e($client['tva_intra'] ?: '—') ?></dd>
                    <dt>Création</dt><dd><?= date_fr($client['date_creation']) ?></dd>
                    <dt>Capital</dt><dd><?= $client['capital'] !== null ? money($client['capital']) : '—' ?></dd>
                    <dt>Résultat net</dt><dd class="<?= ((float) $client['resultat_net']) < 0 ? 'texte-rouge' : '' ?>"><?= $client['resultat_net'] !== null ? money($client['resultat_net']) : '—' ?></dd>
                    <dt>Procédure coll.</dt><dd class="<?= $client['procedure_collective'] ? 'texte-rouge gras' : '' ?>"><?= e($client['procedure_collective'] ?: 'Aucune') ?></dd>
                    <dt>Score interne</dt><dd><span class="badge" style="background:<?= e($solvabilite['couleur']) ?>"><?= (int) $solvabilite['score'] ?>/100 · <?= e($solvabilite['label']) ?></span></dd>
                </dl>
                <div class="mt-2">
                    <label>Adresse complète</label>
                    <div class="petit texte-doux">
                        <?= e($client['adresse'] ?: '') ?><br>
                        <?php if ($client['complement_adresse']): ?><?= e($client['complement_adresse']) ?><br><?php endif; ?>
                        <?= e(trim(($client['code_postal'] ?? '') . ' ' . ($client['ville'] ?? ''))) ?><br>
                        <?= e($client['pays'] ?: '') ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($client['notes']): ?>
            <div class="card">
                <div class="card-entete"><h3>Notes internes (champ libre)</h3></div>
                <div class="card-corps" style="white-space:pre-wrap"><?= e($client['notes']) ?></div>
            </div>
        <?php endif; ?>

        <div class="card" id="notes-client">
            <div class="card-entete"><h3>Notes sur la fiche client</h3></div>
            <?php if (\App\Core\Auth::peut('clients') && \App\Core\Auth::canWrite()): ?>
                <form method="post" action="<?= url('/clients/' . (int) $client['id'] . '/notes') ?>" class="card-corps">
                    <?= csrf_field() ?>
                    <div class="form-groupe mb-1">
                        <textarea name="contenu" rows="3" placeholder="Ajouter une note… (visible aux utilisateurs ayant accès à Clients et aux agences via le partage)" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-petit">Ajouter</button>
                </form>
            <?php endif; ?>
            <div class="card-corps">
                <?php if (empty($clientNotes)): ?>
                    <div class="texte-doux">Aucune note.</div>
                <?php else: ?>
                    <?php foreach ($clientNotes as $n): ?>
                        <div class="note">
                            <div class="note-entete">
                                <strong><?= e($n['user_nom'] ?: $n['username'] ?: 'Inconnu') ?></strong>
                                <span><?= datetime_fr($n['created_at']) ?></span>
                                <?php if (\App\Core\Auth::canWrite() && ((int) $n['user_id'] === (int) $authUser['id'] || $estAdmin)): ?>
                                    <form method="post" action="<?= url('/clients/' . (int) $client['id'] . '/notes/' . (int) $n['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer cette note ?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-lien rouge">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="note-corps"><?= e($n['contenu']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" id="ia">
            <div class="card-entete">
                <h3>Analyse de confiance IA (1min.ai)</h3>
                <?php if (\App\Core\Auth::peut('clients') && \App\Core\Auth::canWrite()): ?>
                    <div class="esp"></div>
                    <form method="post" action="<?= url('/clients/' . (int) $client['id'] . '/analyser-ia') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-petit">Analyser</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-corps">
                <?php if ($client['ia_synced_at']): ?>
                    <div class="petit texte-doux mb-1">Dernière analyse : <?= datetime_fr($client['ia_synced_at']) ?> — Score IA : <?= (int) $client['ia_score'] ?>/100 — Décision : <?= e($client['ia_decision']) ?></div>
                <?php endif; ?>
                <?php if (!$iaAnalyse): ?>
                    <div class="texte-doux">Aucune analyse enregistrée.</div>
                <?php else: ?>
                    <dl class="definition" style="grid-template-columns:130px 1fr">
                        <dt>Confiance</dt><dd><?= $iaAnalyse['confiance'] ? 'Oui' : 'Non' ?> (<?= (int) $iaAnalyse['score'] ?>/100)</dd>
                        <dt>Décision</dt><dd><?= e($iaAnalyse['decision']) ?></dd>
                        <dt>Raison</dt><dd><?= e($iaAnalyse['raison']) ?></dd>
                        <dt>Risques</dt><dd><?= !empty($iaAnalyse['risques']) ? '<ul class="petit" style="margin:0;padding-left:18px">' . implode('', array_map(fn ($r) => '<li>' . e($r) . '</li>', (array) $iaAnalyse['risques'])) . '</ul>' : '—' ?></dd>
                        <dt>Recommandations</dt><dd><?= !empty($iaAnalyse['recommandations']) ? '<ul class="petit" style="margin:0;padding-left:18px">' . implode('', array_map(fn ($r) => '<li>' . e($r) . '</li>', (array) $iaAnalyse['recommandations'])) . '</ul>' : '—' ?></dd>
                        <dt>Analyse</dt><dd style="white-space:pre-wrap"><?= e($iaAnalyse['analyse']) ?></dd>
                    </dl>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Solvabilité (score interne)</h3></div>
            <div class="card-corps">
                <div class="flex" style="align-items:center;gap:10px">
                    <span class="badge" style="background:<?= e($solvabilite['couleur']) ?>;font-size:15px"><?= (int) $solvabilite['score'] ?>/100</span>
                    <strong><?= e($solvabilite['label']) ?></strong>
                </div>
                <?php if (!empty($solvabilite['facteurs'])): ?>
                    <ul class="petit texte-doux mt-2" style="margin:0;padding-left:18px">
                        <?php foreach ($solvabilite['facteurs'] as $facteur): ?>
                            <li><?= e($facteur) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="petit texte-doux mt-2">Pas assez de données financières pour affiner le score.</div>
                <?php endif; ?>
                <div class="petit texte-doux mt-2">Calcul local et gratuit, à partir des données publiques et de l'encours interne.</div>
            </div>
        </div>
    </div>
</div>
