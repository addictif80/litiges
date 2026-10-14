<?php
/**
 * Fiche detaillee d'un litige.
 * @var array $litige
 */
$cloture = (int) $litige['statut_cloturant'] === 1;
$peutEcrire = in_array($authUser['role'] ?? '', ['admin', 'gestionnaire'], true);
$estAdmin = ($authUser['role'] ?? '') === 'admin';
$aujourdhui = date('Y-m-d');
$retardRelance = !empty($litige['prochaine_relance_at']) && !$cloture
    && strtotime((string) $litige['prochaine_relance_at']) <= strtotime($aujourdhui);
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
            <span class="badge badge-priorite-<?= e($litige['priorite']) ?>"><?= e(ucfirst($litige['priorite'])) ?></span>
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
        <button type="button" class="btn btn-secondaire" onclick="window.print()">🖨 Imprimer</button>
        <?php if ($estAdmin): ?>
            <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/supprimer') ?>" data-confirmer="Supprimer définitivement ce litige et tout son historique ?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-rouge">🗑</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grille grille-4 mb-2">
    <div class="stat accent"><div class="libelle">Montant principal</div><div class="valeur"><?= money($litige['montant_principal']) ?></div></div>
    <div class="stat accent-orange"><div class="libelle">Pénalités / frais</div><div class="valeur"><?= money($litige['montant_penalites']) ?></div></div>
    <div class="stat accent-vert"><div class="libelle">Récupéré</div><div class="valeur"><?= money($litige['montant_recupere']) ?></div></div>
    <div class="stat accent-rouge"><div class="libelle">Encours</div><div class="valeur"><?= money($litige['encours']) ?></div>
        <div class="detail"><?= $cloture ? 'Litige clôturé' . ($litige['finalite_libelle'] ? ' — ' . e($litige['finalite_libelle']) : '') : ($retardRelance ? '⚠ Relance en retard' : 'En cours') ?></div>
    </div>
</div>

<div class="grille" style="grid-template-columns:2fr 1fr;align-items:start">
    <div>
        <div class="onglets">
            <button data-onglet="suivi" class="is-actif">Suivi &amp; relances (<?= count($relances) ?>)</button>
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
                                        <?php foreach (\App\Models\Relance::TYPES as $k => $lib): ?>
                                            <option value="<?= e($k) ?>"><?= e($lib) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-groupe">
                                    <label for="canal">Canal</label>
                                    <select id="canal" name="canal">
                                        <?php foreach (\App\Models\Relance::CANAUX as $k => $lib): ?>
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
                                        <span class="tl-titre"><?= e(\App\Models\Relance::TYPES[$r['type_action']] ?? $r['type_action']) ?></span>
                                        <span class="badge badge-contour"><?= e(\App\Models\Relance::CANAUX[$r['canal']] ?? $r['canal']) ?></span>
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

        <!-- NOTES -->
        <div class="onglet-panneau" id="notes">
            <?php if ($peutEcrire): ?>
                <div class="card">
                    <form method="post" action="<?= url('/litiges/' . (int) $litige['id'] . '/notes') ?>">
                        <?= csrf_field() ?>
                        <div class="card-corps">
                            <div class="form-groupe mb-0">
                                <label for="contenu_note">Nouvelle note interne</label>
                                <textarea id="contenu_note" name="contenu" rows="3" placeholder="Note visible uniquement par les utilisateurs internes…" required></textarea>
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
                                <div class="note-corps"><?= e($n['contenu']) ?></div>
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
                                    <div class="aide">PDF, images, Word/Excel… — taille max <?= (int) setting('max_upload_mo', 10) ?> Mo.</div>
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
                                    <div class="tl-pied">par <?= e($h['user_nom'] ?: $h['username'] ?: '—') ?></div>
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
                                    <option value="<?= (int) $s['id'] ?>" <?= (int) $litige['statut_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['libelle']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                            <label for="montant_recupere_maj">Montant récupéré (€)</label>
                            <input type="text" inputmode="decimal" id="montant_recupere_maj" name="montant_recupere" value="<?= e($litige['montant_recupere']) ?>">
                        </div>
                        <div class="form-groupe mb-0">
                            <label for="commentaire_maj">Commentaire</label>
                            <textarea id="commentaire_maj" name="commentaire" rows="2"></textarea>
                        </div>
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
