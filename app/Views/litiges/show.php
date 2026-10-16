<?php
/**
 * Fiche detaillee d'un litige.
 * @var array $litige
 * @var array $paiements
 * @var array $echeances
 * @var array $courriers
 * @var array $taches
 * @var array $modeles
 * @var array $penalites
 * @var array|null $transitions
 * @var array $prerequis
 * @var array|null $creance
 */
use App\Enums\CanalAction;
use App\Enums\ModePaiement;
use App\Enums\Priorite;
use App\Enums\TypeAction;
use App\Models\Courrier;
use App\Models\Tache;
use App\Services\Calcul\EcheancierCalculator;

$cloture = (int) $litige['statut_cloturant'] === 1;
$peutEcrire = in_array($authUser['role'] ?? '', ['admin', 'gestionnaire'], true);
$estAdmin = ($authUser['role'] ?? '') === 'admin';
$aujourdhui = date('Y-m-d');
$retardRelance = !empty($litige['prochaine_relance_at']) && !$cloture
    && strtotime((string) $litige['prochaine_relance_at']) <= strtotime($aujourdhui);
$nbEcheancesRetard = count(array_filter($echeances, static fn (array $e): bool => $e['statut'] === 'en_retard'));
$modeleCreance = null;
foreach ($modeles as $m) {
    if ($m['code'] === 'declaration_creance') {
        $modeleCreance = $m;
    }
}
$tachesOuvertes = count(array_filter($taches, static fn (array $t): bool => $t['statut'] !== 'faite'));
$couleursAr = ['non_envoye' => '#6c757d', 'en_attente' => '#fd7e14', 'recu' => '#198754', 'refuse' => '#dc3545', 'non_reclame' => '#dc3545'];
$couleursEcheance = ['payee' => '#198754', 'partielle' => '#fd7e14', 'en_retard' => '#dc3545', 'a_venir' => '#6c757d'];
$libellesEcheance = ['payee' => 'Payée', 'partielle' => 'Partielle', 'en_retard' => 'En retard', 'a_venir' => 'À venir'];
?>

<div class="fil">
    <a href="<?= url('/litiges') ?>">Litiges</a> /
    <span class="mono"><?= e($litige['reference']) ?></span>
</div>

<div class="entete-fiche">
    <div class="infos" style="flex:1;min-width:280px">
        <h1>
            <?= e($litige['reference']) ?>
            <span class="badge" style="background:<?= e($litige['statut_couleur']) ?>"><?= e($litige['statut_libelle']) ?></span>
            <span class="badge badge-priorite-<?= e($litige['priorite']) ?>"><?= e(Priorite::tryFrom($litige['priorite'])?->label() ?? $litige['priorite']) ?></span>
        </h1>
        <div class="meta">
            <span>🏢 <a href="<?= url('/clients/' . (int) $litige['client_id']) ?>"><?= e($litige['raison_sociale']) ?></a><?= $litige['client_siren'] ? ' — SIREN ' . e($litige['client_siren']) : '' ?></span>
            <span>📅 Ouvert le <?= date_fr($litige['date_ouverture']) ?></span>
            <?php if ($litige['agence_nom']): ?><span>🏬 <?= e($litige['agence_nom']) ?></span><?php endif; ?>
            <span>👤 <?= e($litige['responsable_nom'] ?: $litige['responsable_username'] ?: 'Non affecté') ?></span>
        </div>
    </div>
    <div class="flex">
        <?php if ($peutEcrire): ?>
            <a href="<?= url('/litiges/' . (int) $litige['id'] . '/modifier') ?>" class="btn btn-secondaire">✎ Modifier</a>
        <?php endif; ?>
        <?php if ($peutEcrire): ?>
            <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/partage') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondaire">🔗 Partager (lien public)</button>
            </form>
        <?php endif; ?>
        <button type="button" class="btn btn-secondaire" data-imprimer>🖨 Imprimer</button>
        <?php if ($estAdmin): ?>
            <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/supprimer') ?>" data-confirmer="Supprimer définitivement ce litige et tout son historique ?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-rouge">🗑</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($creance): ?>
    <div class="alerte alerte-warning mb-2">
        <strong>⚠ Procédure collective : <?= e($creance['procedure']) ?></strong>
        <?php if ($creance['declaree']): ?>
            <div>Créance déclarée le <?= date_fr($creance['declaree']) ?>.</div>
        <?php elseif ($creance['limite']): ?>
            <div>
                Délai de déclaration de créance (<?= (int) $creance['mois'] ?> mois après la publication du <?= date_fr($creance['date_parution']) ?>) :
                <strong class="<?= $creance['jours'] !== null && $creance['jours'] < 0 ? 'texte-rouge' : '' ?>">
                    avant le <?= date_fr($creance['limite']) ?>
                    (<?= $creance['jours'] === null ? '—' : ($creance['jours'] < 0 ? 'dépassé de ' . abs((int) $creance['jours']) . ' j' : 'J-' . (int) $creance['jours']) ?>)
                </strong>
                <div class="petit">Délai usuel à vérifier avec votre conseil (4 mois pour un créancier hors métropole).</div>
            </div>
        <?php else: ?>
            <div>Date de publication au BODACC inconnue : vérifiez-la sur la fiche client pour calculer le délai de déclaration.</div>
        <?php endif; ?>
        <?php if ($peutEcrire): ?>
            <div class="flex mt-1">
                <?php if ($modeleCreance && !$creance['declaree']): ?>
                    <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/courriers') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="modele_id" value="<?= (int) $modeleCreance['id'] ?>">
                        <input type="hidden" name="enregistrer_relance" value="1">
                        <button type="submit" class="btn btn-petit">📄 Générer la déclaration de créance</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/creance') ?>" class="flex">
                    <?= csrf_field() ?>
                    <?php if ($creance['declaree']): ?>
                        <input type="hidden" name="annuler" value="1">
                        <button type="submit" class="btn btn-petit btn-secondaire">Annuler la déclaration</button>
                    <?php else: ?>
                        <input type="date" name="creance_declaree_le" value="<?= e($aujourdhui) ?>" style="width:auto">
                        <button type="submit" class="btn btn-petit btn-secondaire">Marquer comme déclarée</button>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($nbEcheancesRetard > 0 && !$cloture): ?>
    <div class="alerte alerte-error mb-2">⚠ <?= (int) $nbEcheancesRetard ?> échéance(s) du plan de paiement non réglée(s) — voir l'onglet « Paiements &amp; échéancier ».</div>
<?php endif; ?>

<div class="grille grille-4 mb-2">
    <div class="stat accent"><div class="libelle">Montant principal</div><div class="valeur"><?= money($litige['montant_principal']) ?></div></div>
    <div class="stat accent-orange"><div class="libelle">Pénalités / frais</div><div class="valeur"><?= money($litige['montant_penalites']) ?></div>
        <?php if ((int) $litige['penalites_auto'] === 1): ?><div class="detail">calcul automatique</div><?php endif; ?></div>
    <div class="stat accent-vert"><div class="libelle">Récupéré</div><div class="valeur"><?= money($litige['montant_recupere']) ?></div>
        <div class="detail"><?= count($paiements) ?> paiement(s)</div></div>
    <div class="stat accent-rouge"><div class="libelle">Encours</div><div class="valeur"><?= money($litige['encours']) ?></div>
        <div class="detail"><?= $cloture ? 'Litige clôturé' . ($litige['finalite_libelle'] ? ' — ' . e($litige['finalite_libelle']) : '') : ($retardRelance ? '⚠ Relance en retard' : 'En cours') ?></div>
    </div>
</div>

<div class="grille" style="grid-template-columns:2fr 1fr;align-items:start">
    <div>
        <div class="onglets">
            <button data-onglet="suivi" class="is-actif">Suivi &amp; relances (<?= count($relances) ?>)</button>
            <button data-onglet="paiements">Paiements &amp; échéancier (<?= count($paiements) ?>)</button>
            <button data-onglet="courriers">Courriers (<?= count($courriers) ?>)</button>
            <button data-onglet="taches">Tâches (<?= (int) $tachesOuvertes ?>)</button>
            <button data-onglet="notes">Notes (<?= count($notes) ?>)</button>
            <button data-onglet="documents">Documents (<?= count($documents) ?>)</button>
            <button data-onglet="historique">Historique (<?= count($historique) ?>)</button>
            <button data-onglet="infos">Informations</button>
        </div>

        <!-- SUIVI -->
        <div class="onglet-panneau is-actif" id="suivi">
            <?php if ($peutEcrire): ?>
                <div class="card">
                    <div class="card-entete"><h3>Ajouter une relance / un contact</h3></div>
                    <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/relances') ?>">
                        <?= csrf_field() ?>
                        <div class="card-corps">
                            <div class="ligne-champs">
                                <div class="form-groupe">
                                    <label for="type_action">Type d'action</label>
                                    <select id="type_action" name="type_action">
                                        <?php foreach (TypeAction::options() as $k => $lib): ?>
                                            <option value="<?= e($k) ?>" <?= $k === 'appel' ? 'selected' : '' ?>><?= e($lib) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-groupe">
                                    <label for="canal">Canal</label>
                                    <select id="canal" name="canal">
                                        <?php foreach (CanalAction::options() as $k => $lib): ?>
                                            <option value="<?= e($k) ?>"><?= e($lib) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-groupe">
                                    <label for="date_action">Date &amp; heure</label>
                                    <input type="datetime-local" id="date_action" name="date_action" value="<?= date('Y-m-d\TH:i') ?>">
                                </div>
                                <div class="form-groupe">
                                    <label for="contact_id">Contact</label>
                                    <select id="contact_id" name="contact_id">
                                        <option value="">—</option>
                                        <?php foreach ($contacts as $c): ?>
                                            <option value="<?= (int) $c['id'] ?>"><?= e(trim(($c['prenom'] ?? '') . ' ' . $c['nom']) ?: 'Contact') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-groupe">
                                <label for="objet">Objet</label>
                                <input type="text" id="objet" name="objet" placeholder="Ex : Relance téléphonique impayé">
                            </div>
                            <div class="form-groupe">
                                <label for="contenu">Détail de l'échange</label>
                                <textarea id="contenu" name="contenu" rows="3" placeholder="Compte-rendu, engagements pris…"></textarea>
                            </div>
                            <div class="ligne-champs">
                                <div class="form-groupe">
                                    <label for="resultat">Résultat</label>
                                    <input type="text" id="resultat" name="resultat" placeholder="Ex : promesse de paiement">
                                </div>
                                <div class="form-groupe">
                                    <label for="montant_promis">Montant promis (€)</label>
                                    <input type="text" inputmode="decimal" id="montant_promis" name="montant_promis">
                                </div>
                                <div class="form-groupe">
                                    <label for="date_promesse">Date de promesse</label>
                                    <input type="date" id="date_promesse" name="date_promesse">
                                </div>
                                <div class="form-groupe">
                                    <label for="prochaine_action_at">Prochaine action</label>
                                    <input type="date" id="prochaine_action_at" name="prochaine_action_at">
                                </div>
                            </div>
                        </div>
                        <div class="card-pied">
                            <button type="submit" class="btn">Enregistrer la relance</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-entete"><h3>Historique des relances</h3></div>
                <div class="card-corps">
                    <?php if (empty($relances)): ?>
                        <p class="texte-doux">Aucune relance enregistrée pour ce litige.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($relances as $r): ?>
                                <?php
                                $classe = 'orange';
                                if (in_array($r['type_action'], ['mise_en_demeure'], true)) { $classe = 'rouge'; }
                                if (in_array($r['type_action'], ['promesse_paiement', 'echelonnement'], true)) { $classe = 'vert'; }
                                ?>
                                <div class="timeline-item <?= $classe ?>">
                                    <div class="tl-entete">
                                        <span class="tl-titre"><?= e(TypeAction::tryFrom($r['type_action'])?->label() ?? $r['type_action']) ?></span>
                                        <span class="badge badge-contour"><?= e(CanalAction::tryFrom($r['canal'])?->label() ?? $r['canal']) ?></span>
                                        <span class="tl-date"><?= datetime_fr($r['date_action']) ?></span>
                                    </div>
                                    <?php if ($r['objet']): ?><div class="gras petit" style="margin-top:3px"><?= e($r['objet']) ?></div><?php endif; ?>
                                    <?php if ($r['contenu']): ?><div class="tl-corps"><?= e($r['contenu']) ?></div><?php endif; ?>
                                    <div class="tl-pied">
                                        <?php if ($r['contact_nom']): ?>Contact : <?= e(trim($r['contact_nom'])) ?> · <?php endif; ?>
                                        <?php if ($r['resultat']): ?>Résultat : <?= e($r['resultat']) ?> · <?php endif; ?>
                                        <?php if ($r['montant_promis'] !== null): ?>Promis : <?= money($r['montant_promis']) ?><?= $r['date_promesse'] ? ' le ' . date_fr($r['date_promesse']) : '' ?> · <?php endif; ?>
                                        par <?= e($r['user_nom'] ?: $r['username'] ?: '—') ?>
                                        <?php if ($peutEcrire && ($estAdmin || (int) $r['created_by'] === (int) $authUser['id'])): ?>
                                            <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/relances/' . (int) $r['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer cette relance ?">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn-lien rouge">Supprimer</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PAIEMENTS & ECHEANCIER -->
        <div class="onglet-panneau" id="paiements">
            <?php if ($peutEcrire): ?>
                <div class="card">
                    <div class="card-entete"><h3>Enregistrer un paiement reçu</h3></div>
                    <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/paiements') ?>">
                        <?= csrf_field() ?>
                        <div class="card-corps">
                            <div class="ligne-champs">
                                <div class="form-groupe">
                                    <label for="date_paiement">Date du paiement</label>
                                    <input type="date" id="date_paiement" name="date_paiement" value="<?= e($aujourdhui) ?>" max="<?= e($aujourdhui) ?>" required>
                                </div>
                                <div class="form-groupe">
                                    <label for="montant_paiement">Montant (€)</label>
                                    <input type="text" inputmode="decimal" id="montant_paiement" name="montant" required placeholder="<?= e(number_format(max(0, (float) $litige['encours']), 2, ',', '')) ?>">
                                </div>
                                <div class="form-groupe">
                                    <label for="mode_paiement">Mode</label>
                                    <select id="mode_paiement" name="mode">
                                        <?php foreach (ModePaiement::options() as $k => $lib): ?>
                                            <option value="<?= e($k) ?>"><?= e($lib) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-groupe">
                                    <label for="reference_paiement">Référence</label>
                                    <input type="text" id="reference_paiement" name="reference" maxlength="120" placeholder="N° de chèque, de virement…">
                                </div>
                            </div>
                            <div class="form-groupe mb-0">
                                <label for="commentaire_paiement">Commentaire</label>
                                <input type="text" id="commentaire_paiement" name="commentaire" maxlength="255">
                            </div>
                        </div>
                        <div class="card-pied"><button type="submit" class="btn btn-vert">Enregistrer le paiement</button></div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-entete"><h3>Paiements reçus</h3><div class="esp"></div><span class="petit texte-doux">Total : <?= money($litige['montant_recupere']) ?></span></div>
                <?php if (empty($paiements)): ?>
                    <div class="card-corps texte-doux">Aucun paiement enregistré.</div>
                <?php else: ?>
                    <table class="tableau tableau-compact">
                        <thead><tr><th>Date</th><th class="num">Montant</th><th>Mode</th><th>Référence</th><th>Saisi par</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($paiements as $p): ?>
                            <tr>
                                <td><?= date_fr($p['date_paiement']) ?></td>
                                <td class="num gras"><?= money($p['montant']) ?></td>
                                <td class="petit"><?= e(ModePaiement::tryFrom($p['mode'])?->label() ?? $p['mode']) ?></td>
                                <td class="petit"><?= e($p['reference'] ?: '—') ?><?php if ($p['commentaire']): ?><div class="texte-doux"><?= e($p['commentaire']) ?></div><?php endif; ?></td>
                                <td class="petit"><?= $p['source'] === 'manuel' ? e($p['user_nom'] ?: $p['username'] ?: '—') : '<span class="badge badge-contour">' . e($p['source']) . '</span>' ?></td>
                                <td class="actions">
                                    <?php if ($peutEcrire && ($estAdmin || (int) $p['created_by'] === (int) $authUser['id'])): ?>
                                        <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/paiements/' . (int) $p['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer ce paiement ? Le solde sera recalculé.">
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
            </div>

            <div class="card" id="echeancier">
                <div class="card-entete"><h3>Échéancier de paiement</h3></div>
                <?php if (empty($echeances)): ?>
                    <?php if ($peutEcrire && !$cloture): ?>
                        <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/echeancier') ?>">
                            <?= csrf_field() ?>
                            <div class="card-corps">
                                <p class="aide">Créez un plan de règlement convenu avec le client. Chaque paiement enregistré ensuite est imputé automatiquement sur les échéances.</p>
                                <div class="ligne-champs">
                                    <div class="form-groupe">
                                        <label for="ech_montant">Montant à échelonner (€)</label>
                                        <input type="text" inputmode="decimal" id="ech_montant" name="montant_total" value="<?= e(number_format(max(0, (float) $litige['encours']), 2, ',', '')) ?>">
                                    </div>
                                    <div class="form-groupe">
                                        <label for="ech_nombre">Nombre d'échéances</label>
                                        <input type="number" id="ech_nombre" name="nombre" min="1" max="120" value="3" required>
                                    </div>
                                    <div class="form-groupe">
                                        <label for="ech_date">Première échéance</label>
                                        <input type="date" id="ech_date" name="premiere_date" value="<?= e(date('Y-m-d', strtotime('+15 days'))) ?>" required>
                                    </div>
                                    <div class="form-groupe">
                                        <label for="ech_period">Périodicité</label>
                                        <select id="ech_period" name="periodicite">
                                            <?php foreach (EcheancierCalculator::PERIODICITES as $k => $lib): ?>
                                                <option value="<?= e($k) ?>" <?= $k === 'mensuelle' ? 'selected' : '' ?>><?= e($lib) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-groupe">
                                        <label for="ech_premier">Premier versement (€, facultatif)</label>
                                        <input type="text" inputmode="decimal" id="ech_premier" name="premier_montant" placeholder="Acompte à la 1re échéance">
                                    </div>
                                </div>
                            </div>
                            <div class="card-pied"><button type="submit" class="btn">Créer l'échéancier</button></div>
                        </form>
                    <?php else: ?>
                        <div class="card-corps texte-doux">Aucun échéancier.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <table class="tableau tableau-compact">
                        <thead><tr><th>N°</th><th>Date</th><th class="num">Montant</th><th class="num">Réglé</th><th>Statut</th></tr></thead>
                        <tbody>
                        <?php foreach ($echeances as $ech): ?>
                            <tr>
                                <td><?= (int) $ech['numero'] ?></td>
                                <td><?= date_fr($ech['date_echeance']) ?></td>
                                <td class="num"><?= money($ech['montant']) ?></td>
                                <td class="num"><?= money($ech['montant_regle']) ?></td>
                                <td><span class="badge" style="background:<?= e($couleursEcheance[$ech['statut']] ?? '#6c757d') ?>"><?= e($libellesEcheance[$ech['statut']] ?? $ech['statut']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($peutEcrire): ?>
                        <div class="card-pied">
                            <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/echeancier/supprimer') ?>" data-confirmer="Supprimer l'échéancier ? Les paiements enregistrés sont conservés.">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-petit btn-rouge">Supprimer l'échéancier</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="card" id="penalites">
                <div class="card-entete"><h3>Pénalités de retard</h3></div>
                <div class="card-corps">
                    <dl class="definition" style="grid-template-columns:200px 1fr">
                        <dt>Point de départ</dt><dd><?= date_fr($penalites['depuis']) ?> (<?= (int) $penalites['jours'] ?> jours de retard)</dd>
                        <dt>Taux annuel</dt><dd><?= number_format((float) $penalites['taux'], 2, ',', ' ') ?> %</dd>
                        <dt>Intérêts de retard</dt><dd><?= money($penalites['interets']) ?> <span class="petit texte-doux">sur le capital restant dû</span></dd>
                        <dt>Indemnité forfaitaire</dt><dd><?= money($penalites['indemnite']) ?></dd>
                        <dt>Total calculé</dt><dd class="gras"><?= money($penalites['total']) ?></dd>
                    </dl>
                    <?php if ($peutEcrire): ?>
                        <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/penalites') ?>" class="mt-2">
                            <?= csrf_field() ?>
                            <label class="check"><input type="checkbox" name="penalites_auto" value="1" <?= (int) $litige['penalites_auto'] === 1 ? 'checked' : '' ?>> Calcul automatique quotidien (remplace le montant de pénalités saisi à la main)</label>
                            <label class="check"><input type="checkbox" name="indemnite_forfaitaire" value="1" <?= (int) $litige['indemnite_forfaitaire'] === 1 ? 'checked' : '' ?>> Ajouter l'indemnité forfaitaire de recouvrement</label>
                            <div class="ligne-champs mt-1">
                                <div class="form-groupe">
                                    <label for="taux_penalite">Taux annuel spécifique (%)</label>
                                    <input type="text" inputmode="decimal" id="taux_penalite" name="taux_penalite" value="<?= e($litige['taux_penalite']) ?>" placeholder="par défaut : <?= e(number_format((float) \App\Core\Settings::get('penalite_taux_annuel', 12), 2, ',', '')) ?>">
                                </div>
                                <div class="form-groupe">
                                    <label for="penalites_depuis">Courent depuis le</label>
                                    <input type="date" id="penalites_depuis" name="penalites_depuis" value="<?= e($litige['penalites_depuis']) ?>">
                                    <div class="aide">Par défaut : échéance, sinon date de facture, sinon date d'ouverture.</div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-petit">Enregistrer et recalculer</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- COURRIERS -->
        <div class="onglet-panneau" id="courriers">
            <?php if ($peutEcrire): ?>
                <div class="card">
                    <div class="card-entete"><h3>Générer un courrier</h3></div>
                    <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/courriers') ?>">
                        <?= csrf_field() ?>
                        <div class="card-corps">
                            <div class="form-groupe">
                                <label for="modele_id">Modèle</label>
                                <select id="modele_id" name="modele_id" required>
                                    <?php foreach ($modeles as $m): ?>
                                        <option value="<?= (int) $m['id'] ?>"><?= e($m['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="aide">Les variables (client, montants, échéancier…) sont remplies automatiquement ; vous relisez avant d'imprimer. <?php if ($estAdmin): ?><a href="<?= url('/parametres/modeles') ?>">Gérer les modèles</a><?php endif; ?></div>
                            </div>
                            <label class="check"><input type="checkbox" name="enregistrer_relance" value="1" checked> Enregistrer une action « courrier » dans le suivi du litige</label>
                        </div>
                        <div class="card-pied"><button type="submit" class="btn">Générer le courrier</button></div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card">
                <?php if (empty($courriers)): ?>
                    <div class="card-corps texte-doux">Aucun courrier généré.</div>
                <?php else: ?>
                    <table class="tableau tableau-compact">
                        <thead><tr><th>Courrier</th><th>Envoi</th><th>Accusé de réception</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($courriers as $c): ?>
                            <tr>
                                <td><span class="mono gras"><?= e($c['numero']) ?></span>
                                    <div class="petit"><?= e($c['sujet']) ?></div>
                                    <div class="petit texte-doux"><?= datetime_fr($c['created_at']) ?> · <?= e($c['user_nom'] ?: $c['username'] ?: '—') ?></div></td>
                                <td class="petit">
                                    <?= $c['date_envoi'] ? date_fr($c['date_envoi']) : '—' ?>
                                    <?php if ($c['mode_envoi']): ?><div class="texte-doux"><?= e(Courrier::MODES_ENVOI[$c['mode_envoi']] ?? $c['mode_envoi']) ?></div><?php endif; ?>
                                </td>
                                <td><span class="badge" style="background:<?= e($couleursAr[$c['statut_ar']] ?? '#6c757d') ?>"><?= e(Courrier::STATUTS_AR[$c['statut_ar']] ?? $c['statut_ar']) ?></span>
                                    <?php if ($c['date_ar']): ?><div class="petit texte-doux">le <?= date_fr($c['date_ar']) ?></div><?php endif; ?></td>
                                <td class="actions"><a href="<?= url('/courriers/' . (int) $c['id']) ?>" class="btn-lien">Ouvrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- TACHES -->
        <div class="onglet-panneau" id="taches">
            <?php if ($peutEcrire): ?>
                <div class="card">
                    <div class="card-entete"><h3>Nouvelle tâche</h3></div>
                    <form method="post" action="<?= url('/taches') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="litige_id" value="<?= (int) $litige['id'] ?>">
                        <div class="card-corps">
                            <div class="form-groupe">
                                <label for="tache_titre">À faire</label>
                                <input type="text" id="tache_titre" name="titre" maxlength="190" required placeholder="Ex : Rappeler le comptable avant vendredi">
                            </div>
                            <div class="ligne-champs">
                                <div class="form-groupe">
                                    <label for="tache_assigne">Assignée à</label>
                                    <select id="tache_assigne" name="assigne_a">
                                        <?php foreach ($responsables as $u): ?>
                                            <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === (int) $authUser['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-groupe">
                                    <label for="tache_echeance">Échéance</label>
                                    <input type="date" id="tache_echeance" name="echeance">
                                </div>
                                <div class="form-groupe">
                                    <label for="tache_priorite">Priorité</label>
                                    <select id="tache_priorite" name="priorite">
                                        <?php foreach (Priorite::options() as $k => $lib): ?>
                                            <option value="<?= e($k) ?>" <?= $k === 'normale' ? 'selected' : '' ?>><?= e($lib) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-pied"><button type="submit" class="btn">Ajouter la tâche</button></div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card">
                <?php if (empty($taches)): ?>
                    <div class="card-corps texte-doux">Aucune tâche pour ce litige.</div>
                <?php else: ?>
                    <table class="tableau tableau-compact">
                        <tbody>
                        <?php foreach ($taches as $t): ?>
                            <?php $enRetard = $t['statut'] !== 'faite' && $t['echeance'] && $t['echeance'] < $aujourdhui; ?>
                            <tr>
                                <td style="<?= $t['statut'] === 'faite' ? 'text-decoration:line-through;opacity:.6' : '' ?>">
                                    <?= e($t['titre']) ?>
                                    <div class="petit texte-doux">→ <?= e($t['assigne_nom'] ?: $t['assigne_username'] ?: 'non assignée') ?></div>
                                </td>
                                <td class="petit <?= $enRetard ? 'texte-rouge gras' : '' ?>"><?= $t['echeance'] ? date_fr($t['echeance']) : '—' ?></td>
                                <td class="petit"><?= e(Tache::STATUTS[$t['statut']] ?? $t['statut']) ?></td>
                                <td class="actions">
                                    <?php if ($peutEcrire): ?>
                                        <form method="post" action="<?= url('/taches/' . (int) $t['id'] . '/statut') ?>" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="statut" value="<?= $t['statut'] === 'faite' ? 'a_faire' : 'faite' ?>">
                                            <button type="submit" class="btn-lien"><?= $t['statut'] === 'faite' ? 'Rouvrir' : '✔ Terminer' ?></button>
                                        </form>
                                        <form method="post" action="<?= url('/taches/' . (int) $t['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer cette tâche ?">
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
            </div>
        </div>

        <!-- NOTES -->
        <div class="onglet-panneau" id="notes">
            <?php if ($peutEcrire): ?>
                <div class="card">
                    <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/notes') ?>">
                        <?= csrf_field() ?>
                        <div class="card-corps">
                            <div class="form-groupe mb-0">
                                <label for="contenu_note">Nouvelle note interne</label>
                                <textarea id="contenu_note" name="contenu" rows="3" placeholder="Note visible uniquement par les utilisateurs internes… Citez un collègue avec @identifiant pour le notifier." required></textarea>
                            </div>
                        </div>
                        <div class="card-pied"><button type="submit" class="btn">Ajouter la note</button></div>
                    </form>
                </div>
            <?php endif; ?>
            <div class="card">
                <div class="card-corps">
                    <?php if (empty($notes)): ?>
                        <p class="texte-doux">Aucune note.</p>
                    <?php else: ?>
                        <?php foreach ($notes as $n): ?>
                            <div class="note">
                                <div class="note-entete">
                                    <strong><?= e($n['user_nom'] ?: $n['username'] ?: 'Inconnu') ?></strong>
                                    <span><?= datetime_fr($n['created_at']) ?></span>
                                    <?php if ($peutEcrire && ($estAdmin || (int) $n['user_id'] === (int) $authUser['id'])): ?>
                                        <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/notes/' . (int) $n['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer cette note ?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn-lien rouge">Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="note-corps"><?= mentions_html($n['contenu']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DOCUMENTS -->
        <div class="onglet-panneau" id="documents">
            <?php if ($peutEcrire): ?>
                <div class="card">
                    <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/documents') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="card-corps">
                            <div class="ligne-champs">
                                <div class="form-groupe">
                                    <label for="fichier">Fichier</label>
                                    <input type="file" id="fichier" name="fichier" required>
                                    <div class="aide">PDF, images, Word/Excel… — taille max <?= (int) setting('max_upload_mo', 10) ?> Mo. Le contenu est vérifié.</div>
                                </div>
                                <div class="form-groupe">
                                    <label for="categorie">Catégorie</label>
                                    <input type="text" id="categorie" name="categorie" placeholder="Ex : facture, mise en demeure, contrat">
                                </div>
                            </div>
                        </div>
                        <div class="card-pied"><button type="submit" class="btn">Téléverser</button></div>
                    </form>
                </div>
            <?php endif; ?>
            <div class="card">
                <?php if (empty($documents)): ?>
                    <div class="card-corps texte-doux">Aucune pièce jointe.</div>
                <?php else: ?>
                    <table class="tableau">
                        <thead><tr><th>Fichier</th><th>Catégorie</th><th>Taille</th><th>Ajouté par</th><th></th></tr></thead>
                        <tbody>
                        <?php $upload = new \App\Services\UploadService(); ?>
                        <?php foreach ($documents as $d): ?>
                            <tr>
                                <td>📎 <?= e($d['nom_original']) ?><div class="petit texte-doux"><?= datetime_fr($d['created_at']) ?></div></td>
                                <td><?= $d['categorie'] ? '<span class="puce-doc">' . e($d['categorie']) . '</span>' : '—' ?></td>
                                <td class="petit"><?= e($upload->tailleLisible((int) $d['taille'])) ?></td>
                                <td class="petit"><?= e($d['user_nom'] ?: '—') ?></td>
                                <td class="actions">
                                    <a href="<?= url('/documents/' . (int) $d['id'] . '/telecharger') ?>" class="btn-lien">Télécharger</a>
                                    <?php if ($peutEcrire): ?>
                                        <form method="post" action="<?= url('/documents/' . (int) $d['id'] . '/partage-public') ?>" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn-lien" title="Visible uniquement dans les partages publics qui affichent les documents">
                                                <?= (int) $d['partage_public'] === 1 ? '🔗 Public ✓' : '🔗 Rendre public' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($peutEcrire && ($estAdmin || (int) $d['uploaded_by'] === (int) $authUser['id'])): ?>
                                        <form method="post" action="<?= url('/documents/' . (int) $d['id'] . '/supprimer') ?>" class="inline" data-confirmer="Supprimer ce document ?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn-lien rouge">Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- HISTORIQUE -->
        <div class="onglet-panneau" id="historique">
            <div class="card">
                <div class="card-entete"><h3>Journal des modifications</h3></div>
                <div class="card-corps">
                    <?php if (empty($historique)): ?>
                        <p class="texte-doux">Aucun évènement.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($historique as $h): ?>
                                <div class="timeline-item">
                                    <div class="tl-entete">
                                        <span class="tl-titre"><?= e(ucfirst(str_replace('_', ' ', $h['action']))) ?></span>
                                        <span class="tl-date"><?= datetime_fr($h['created_at']) ?></span>
                                    </div>
                                    <div class="tl-corps petit">
                                        <?php if ($h['champ']): ?>
                                            <strong><?= e($h['champ']) ?></strong> :
                                            <?php if ($h['ancienne_valeur'] !== null && $h['ancienne_valeur'] !== ''): ?>
                                                <span class="texte-doux">« <?= e($h['ancienne_valeur']) ?> » →</span>
                                            <?php endif; ?>
                                            « <?= e($h['nouvelle_valeur']) ?> »
                                        <?php endif; ?>
                                        <?php if ($h['commentaire']): ?><div><?= e($h['commentaire']) ?></div><?php endif; ?>
                                    </div>
                                    <div class="tl-pied">par <?= e($h['user_nom'] ?: $h['username'] ?: 'système') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- INFOS -->
        <div class="onglet-panneau" id="infos">
            <div class="card">
                <div class="card-entete"><h3>Détails du dossier</h3></div>
                <div class="card-corps">
                    <dl class="definition">
                        <dt>Objet</dt><dd><?= e($litige['objet']) ?></dd>
                        <dt>Type</dt><dd><?= e($types[$litige['type_litige']] ?? ($litige['type_litige'] ?: '—')) ?></dd>
                        <dt>Description</dt><dd style="white-space:pre-wrap"><?= e($litige['description'] ?: '—') ?></dd>
                        <dt>N° facture</dt><dd><?= e($litige['numero_facture'] ?: '—') ?></dd>
                        <dt>Date facture</dt><dd><?= date_fr($litige['date_facture']) ?></dd>
                        <dt>N° contrat</dt><dd><?= e($litige['numero_contrat'] ?: '—') ?></dd>
                        <dt>Matériel / véhicule</dt><dd><?= e($litige['materiel'] ?: '—') ?></dd>
                        <dt>Référence externe</dt><dd><?= e($litige['reference_externe'] ?: '—') ?></dd>
                        <dt>Date d'échéance</dt><dd><?= date_fr($litige['date_echeance']) ?></dd>
                        <dt>Délai de relance</dt><dd><?= (int) $litige['delai_relance_jours'] ?> jours</dd>
                        <dt>Dernière relance</dt><dd><?= datetime_fr($litige['derniere_relance_at']) ?></dd>
                        <dt>Prochaine relance</dt><dd class="<?= $retardRelance ? 'texte-rouge gras' : '' ?>"><?= date_fr($litige['prochaine_relance_at']) ?></dd>
                        <dt>Créé le</dt><dd><?= datetime_fr($litige['created_at']) ?></dd>
                        <dt>Dernière modification</dt><dd><?= datetime_fr($litige['updated_at']) ?></dd>
                        <?php if ($cloture): ?>
                            <dt>Date de clôture</dt><dd><?= date_fr($litige['date_cloture']) ?></dd>
                            <dt>Finalité</dt><dd><span class="badge" style="background:<?= e($litige['finalite_couleur'] ?: '#6c757d') ?>"><?= e($litige['finalite_libelle'] ?: 'Non précisée') ?></span></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Colonne droite -->
    <div>
        <?php if ($peutEcrire): ?>
            <div class="card">
                <div class="card-entete"><h3>Changer le statut</h3></div>
                <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/statut') ?>">
                    <?= csrf_field() ?>
                    <div class="card-corps">
                        <div class="form-groupe">
                            <label for="statut_id_maj">Nouveau statut</label>
                            <select id="statut_id_maj" name="statut_id" required>
                                <?php foreach ($statuts as $s): ?>
                                    <?php
                                    $courant = (int) $litige['statut_id'] === (int) $s['id'];
                                    $interdit = !$courant && $transitions !== null && !in_array((int) $s['id'], $transitions, true);
                                    ?>
                                    <option value="<?= (int) $s['id'] ?>" <?= $courant ? 'selected' : '' ?> <?= ($interdit && !$estAdmin) ? 'disabled' : '' ?>>
                                        <?= e($s['libelle']) ?><?= $interdit ? ' (non autorisé depuis ce statut)' : '' ?><?= !empty($prerequis[(int) $s['id']]) ? ' ⚑' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($prerequis)): ?>
                                <div class="aide">⚑ Pré-requis :
                                    <?php foreach ($statuts as $s): if (empty($prerequis[(int) $s['id']])) { continue; } ?>
                                        <br><strong><?= e($s['libelle']) ?></strong> → <?= e(implode(', ', $prerequis[(int) $s['id']])) ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-groupe">
                            <label for="finalite_id_maj">Finalité</label>
                            <select id="finalite_id_maj" name="finalite_id">
                                <option value="">—</option>
                                <?php foreach ($finalites as $f): ?>
                                    <option value="<?= (int) $f['id'] ?>" <?= (int) $litige['finalite_id'] === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['libelle']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-groupe">
                            <label for="commentaire_maj">Commentaire</label>
                            <textarea id="commentaire_maj" name="commentaire" rows="2"></textarea>
                        </div>
                        <?php if ($estAdmin): ?>
                            <label class="check"><input type="checkbox" name="forcer_workflow" value="1"> Forcer (ignorer le workflow)</label>
                        <?php endif; ?>
                        <div class="aide">Le montant récupéré se met à jour tout seul : enregistrez les paiements dans l'onglet dédié.</div>
                    </div>
                    <div class="card-pied"><button type="submit" class="btn">Mettre à jour le statut</button></div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-entete"><h3>Contacts du client</h3></div>
            <div class="card-corps">
                <?php if (empty($contacts)): ?>
                    <p class="texte-doux petit">Aucun contact enregistré pour ce client.</p>
                <?php else: ?>
                    <?php foreach ($contacts as $c): ?>
                        <div style="padding:8px 0;border-bottom:1px solid #eef2f7">
                            <div class="flex" style="justify-content:space-between">
                                <strong><?= e(trim(($c['prenom'] ?? '') . ' ' . $c['nom'])) ?></strong>
                                <?php if ((int) $c['principal'] === 1): ?><span class="badge badge-contour">Principal</span><?php endif; ?>
                            </div>
                            <?php if ($c['fonction']): ?><div class="petit texte-doux"><?= e($c['fonction']) ?></div><?php endif; ?>
                            <?php if ($c['telephone']): ?><div class="petit">☎ <a href="tel:<?= e(normalize_phone($c['telephone'])) ?>"><?= e($c['telephone']) ?></a></div><?php endif; ?>
                            <?php if ($c['mobile']): ?><div class="petit">📱 <a href="tel:<?= e(normalize_phone($c['mobile'])) ?>"><?= e($c['mobile']) ?></a></div><?php endif; ?>
                            <?php if ($c['email']): ?><div class="petit">✉ <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="mt-2"><a href="<?= url('/clients/' . (int) $litige['client_id'] . '#contacts') ?>" class="btn-lien">Gérer les contacts</a></div>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Résumé client</h3></div>
            <div class="card-corps">
                <dl class="definition" style="grid-template-columns:110px 1fr">
                    <dt>Raison sociale</dt><dd><?= e($litige['raison_sociale']) ?></dd>
                    <dt>SIREN</dt><dd class="mono"><?= e($litige['client_siren'] ?: '—') ?></dd>
                    <dt>Ville</dt><dd><?= e($litige['client_ville'] ?: '—') ?></dd>
                </dl>
                <a href="<?= url('/clients/' . (int) $litige['client_id']) ?>" class="btn btn-secondaire btn-petit mt-2">Voir la fiche client</a>
            </div>
        </div>
    </div>
</div>
