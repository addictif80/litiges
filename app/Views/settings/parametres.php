<?php
/** @var array $settings */
$s = static fn (string $cle, mixed $defaut = '') => $settings[$cle] ?? $defaut;
$pappersActif = (string) $s('pappers_api_token') !== '';
$sources = (new \App\Services\CompanyLookupService())->sourcesDisponibles();
?>
<form method="post" action="<?= url('/parametres') ?>">
    <?= csrf_field() ?>

    <div class="grille grille-2">
        <div class="card">
            <div class="card-entete"><h3>Général</h3></div>
            <div class="card-corps">
                <div class="form-groupe">
                    <label for="app_nom">Nom de l'application</label>
                    <input type="text" id="app_nom" name="app_nom" value="<?= e($s('app_nom')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="societe_nom">Nom de la société</label>
                    <input type="text" id="societe_nom" name="societe_nom" value="<?= e($s('societe_nom')) ?>">
                </div>
                <div class="ligne-champs">
                    <div class="form-groupe">
                        <label for="delai_relance_defaut_jours">Délai de relance par défaut (jours)</label>
                        <input type="number" id="delai_relance_defaut_jours" name="delai_relance_defaut_jours" min="1" value="<?= e($s('delai_relance_defaut_jours', 15)) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="seuil_alerte_retard_jours">Seuil d'alerte échéance (jours)</label>
                        <input type="number" id="seuil_alerte_retard_jours" name="seuil_alerte_retard_jours" min="0" value="<?= e($s('seuil_alerte_retard_jours', 7)) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="max_upload_mo">Taille max. des pièces jointes (Mo)</label>
                        <input type="number" id="max_upload_mo" name="max_upload_mo" min="1" value="<?= e($s('max_upload_mo', 10)) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-entete"><h3>Sécurité</h3></div>
            <div class="card-corps">
                <div class="ligne-champs">
                    <div class="form-groupe">
                        <label for="verrouillage_tentatives">Tentatives avant verrouillage</label>
                        <input type="number" id="verrouillage_tentatives" name="verrouillage_tentatives" min="3" max="20" value="<?= e($s('verrouillage_tentatives', 5)) ?>">
                    </div>
                    <div class="form-groupe">
                        <label for="verrouillage_minutes">Durée de verrouillage (minutes)</label>
                        <input type="number" id="verrouillage_minutes" name="verrouillage_minutes" min="1" value="<?= e($s('verrouillage_minutes', 15)) ?>">
                    </div>
                </div>
                <p class="aide">Les mots de passe sont hashés (bcrypt). Toutes les actions sensibles sont journalisées dans l'audit.</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-entete"><h3>🔎 Source de données entreprises</h3></div>
        <div class="card-corps">
            <div class="ligne-champs">
                <div class="form-groupe">
                    <label for="company_api_provider">Fournisseur</label>
                    <select id="company_api_provider" name="company_api_provider">
                        <option value="auto" <?= $s('company_api_provider') === 'auto' ? 'selected' : '' ?>>Automatique (Pappers si clé, sinon API de l'État + BODACC + score interne)</option>
                        <option value="recherche_entreprises" <?= $s('company_api_provider') === 'recherche_entreprises' ? 'selected' : '' ?>>API Recherche d'entreprises (État, gratuite)</option>
                        <option value="pappers" <?= $s('company_api_provider') === 'pappers' ? 'selected' : '' ?>>Pappers uniquement</option>
                    </select>
                </div>
                <div class="form-groupe">
                    <label for="pappers_api_token">Clé API Pappers <?= $pappersActif ? '<span class="petit texte-vert">(configurée)</span>' : '' ?></label>
                    <input type="password" id="pappers_api_token" name="pappers_api_token" placeholder="<?= $pappersActif ? '•••••••• (laisser vide pour conserver)' : 'Collez votre jeton Pappers' ?>" autocomplete="off">
                    <div class="aide">Clé disponible sur pappers.fr. Sans clé, l'API gratuite de l'État est utilisée (SIREN, adresse, effectif, dirigeants).</div>
                </div>
            </div>
            <label class="check"><input type="checkbox" name="recuperation_auto_active" value="1" <?= (int) $s('recuperation_auto_active', 1) === 1 ? 'checked' : '' ?>> Activer la récupération automatique des données entreprise</label>
            <label class="check mt-2"><input type="checkbox" name="bodacc_actif" value="1" <?= (int) $s('bodacc_actif', 1) === 1 ? 'checked' : '' ?>> Enrichir avec le BODACC (procédures collectives / radiations, gratuit)</label>
            <label class="check"><input type="checkbox" name="score_interne_actif" value="1" <?= (int) $s('score_interne_actif', 1) === 1 ? 'checked' : '' ?>> Calculer un score de solvabilité interne (gratuit, sans service payant)</label>
            <div class="mt-2 petit texte-doux">
                Sources disponibles :
                État <?= $sources['recherche_entreprises'] ? '<span class="texte-vert">✔</span>' : '<span class="texte-rouge">✘</span>' ?> ·
                BODACC <?= $sources['bodacc'] ? '<span class="texte-vert">✔</span>' : '<span class="texte-rouge">✘ (désactivé)</span>' ?> ·
                Pappers <?= $sources['pappers'] ? '<span class="texte-vert">✔</span>' : '<span class="texte-rouge">✘ (clé absente)</span>' ?>
            </div>
            <div class="aide mt-2">Les sources de l'État et le BODACC sont gratuites et sans clé. Pappers reste optionnel.</div>
        </div>
    </div>

    <div class="card" id="ia">
        <div class="card-entete"><h3>🧠 Intelligence artificielle (1min.ai)</h3></div>
        <div class="card-corps">
            <label class="check mb-2"><input type="checkbox" name="oneminai_enabled" value="1" <?= (int) $s('oneminai_enabled', 0) === 1 ? 'checked' : '' ?>> Activer l'analyse de confiance via 1min.ai</label>
            <div class="ligne-champs">
                <div class="form-groupe">
                    <label for="oneminai_api_key">Clé API 1min.ai</label>
                    <input type="password" id="oneminai_api_key" name="oneminai_api_key" placeholder="<?= $s('oneminai_api_key') !== '' ? '•••••••• (laisser vide pour conserver)' : 'Collez votre clé API' ?>" autocomplete="off">
                    <div class="aide">Clé d'API fournie par 1min.ai (compatible OpenAI). Utilisée uniquement pour analyser la confiance d'un client.</div>
                </div>
                <div class="form-groupe">
                    <label for="oneminai_model">Modèle</label>
                    <input type="text" id="oneminai_model" name="oneminai_model" list="oneminai_modeles" value="<?= e($s('oneminai_model', 'gpt-4o-mini')) ?>" autocomplete="off">
                    <div class="aide">Identifiant exact du modèle (ex. <code>gpt-4o-mini</code>). La liste est indicative : voir <code>GET /openai/v1/models</code>. Les modèles de raisonnement (GPT‑5/6, o3, DeepSeek, GLM) n'acceptent pas <code>temperature</code> (géré automatiquement).</div>
                    <datalist id="oneminai_modeles">
                        <optgroup label="OpenAI GPT-4 / GPT-3.5">
                            <option value="gpt-4.1"></option>
                            <option value="gpt-4.1-mini"></option>
                            <option value="gpt-4.1-nano"></option>
                            <option value="gpt-4o"></option>
                            <option value="gpt-4o-mini"></option>
                            <option value="gpt-4-turbo"></option>
                            <option value="gpt-3.5-turbo"></option>
                        </optgroup>
                        <optgroup label="OpenAI GPT-5 / GPT-6 / o3">
                            <option value="gpt-5"></option>
                            <option value="gpt-5-mini"></option>
                            <option value="gpt-5-nano"></option>
                            <option value="gpt-5.1"></option>
                            <option value="gpt-5.2"></option>
                            <option value="gpt-5.4"></option>
                            <option value="gpt-5.4-mini"></option>
                            <option value="gpt-5.4-nano"></option>
                            <option value="gpt-5.5"></option>
                            <option value="gpt-5.6-luna"></option>
                            <option value="gpt-5.6-terra"></option>
                            <option value="gpt-5.6-sol"></option>
                            <option value="gpt-6-astra"></option>
                            <option value="gpt-6-sol"></option>
                            <option value="gpt-6-luna"></option>
                            <option value="o3"></option>
                            <option value="o3-mini"></option>
                        </optgroup>
                        <optgroup label="DeepSeek">
                            <option value="deepseek-flash"></option>
                            <option value="deepseek-v4-pro"></option>
                        </optgroup>
                        <optgroup label="Alibaba / Qwen">
                            <option value="qwen-flash"></option>
                            <option value="qwen-plus"></option>
                            <option value="qwen-max"></option>
                            <option value="qwen-vl-plus"></option>
                            <option value="qwen-vl-max"></option>
                            <option value="qwen3-max"></option>
                            <option value="qwen3-8b"></option>
                            <option value="qwen3-vl-flash"></option>
                            <option value="qwen3-vl-plus"></option>
                            <option value="qwen3.6-flash"></option>
                            <option value="qwen3.6-max-preview"></option>
                            <option value="qwen3.6-plus"></option>
                            <option value="qwen3.7-flash"></option>
                            <option value="qwen3.7-max"></option>
                            <option value="qwen3.7-plus"></option>
                        </optgroup>
                        <optgroup label="Z.AI / GLM">
                            <option value="glm-5"></option>
                            <option value="glm-5.1"></option>
                            <option value="glm-5.2"></option>
                            <option value="glm-5.3"></option>
                        </optgroup>
                        <optgroup label="Mistral">
                            <option value="magistral-medium-latest"></option>
                            <option value="magistral-small-latest"></option>
                            <option value="ministral-14b-latest"></option>
                            <option value="mistral-large-2512"></option>
                            <option value="mistral-large-latest"></option>
                            <option value="mistral-medium-3-5"></option>
                            <option value="mistral-medium-latest"></option>
                            <option value="mistral-small-2603"></option>
                            <option value="mistral-small-latest"></option>
                            <option value="open-mistral-nemo"></option>
                        </optgroup>
                        <optgroup label="OpenRouter — Kimi / Muse Spark">
                            <option value="moonshotai/kimi-k2.6"></option>
                            <option value="moonshotai/kimi-k2.7-code"></option>
                            <option value="meta/muse-spark-1.1"></option>
                            <option value="meta/muse-spark-1.2"></option>
                            <option value="meta/muse-spark-1.3"></option>
                        </optgroup>
                    </datalist>
                </div>
            </div>
            <div class="flex mt-1">
                <button type="submit" class="btn btn-secondaire" formaction="<?= url('/parametres/ia-test') ?>" formmethod="post">Tester la connexion</button>
                <span class="aide" style="margin:0">Valide la clé API et vérifie que le modèle est accessible (sans lancer d'analyse).</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-entete"><h3>🛡️ Droits d'accès par rôle</h3></div>
        <div class="card-corps">
            <p class="aide mb-2">L'administrateur a toujours l'accès à tous les modules. Ces réglages concernent les autres rôles.</p>
            <table class="tableau tableau-compact">
                <thead><tr><th>Module</th><th>Gestionnaire</th><th>Consultant</th><th>Lecteur</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Litiges</td>
                        <td class="centre"><input type="checkbox" name="perm_gestionnaire_litiges" value="1" <?= (int) $s('perm_gestionnaire_litiges', 1) === 1 ? 'checked' : '' ?>></td>
                        <td class="centre"><input type="checkbox" name="perm_consultant_litiges" value="1" <?= (int) $s('perm_consultant_litiges', 1) === 1 ? 'checked' : '' ?>></td>
                        <td class="centre"><input type="checkbox" name="perm_lecteur_litiges" value="1" <?= (int) $s('perm_lecteur_litiges', 1) === 1 ? 'checked' : '' ?>></td>
                    </tr>
                    <tr>
                        <td>Clients</td>
                        <td class="centre"><input type="checkbox" name="perm_gestionnaire_clients" value="1" <?= (int) $s('perm_gestionnaire_clients', 1) === 1 ? 'checked' : '' ?>></td>
                        <td class="centre"><input type="checkbox" name="perm_consultant_clients" value="1" <?= (int) $s('perm_consultant_clients', 1) === 1 ? 'checked' : '' ?>></td>
                        <td class="centre"><input type="checkbox" name="perm_lecteur_clients" value="1" <?= (int) $s('perm_lecteur_clients', 1) === 1 ? 'checked' : '' ?>></td>
                    </tr>
                    <tr>
                        <td>Relances</td>
                        <td class="centre"><input type="checkbox" name="perm_gestionnaire_relances" value="1" <?= (int) $s('perm_gestionnaire_relances', 1) === 1 ? 'checked' : '' ?>></td>
                        <td class="centre"><input type="checkbox" name="perm_consultant_relances" value="1" <?= (int) $s('perm_consultant_relances', 0) === 1 ? 'checked' : '' ?>></td>
                        <td class="centre"><input type="checkbox" name="perm_lecteur_relances" value="1" <?= (int) $s('perm_lecteur_relances', 0) === 1 ? 'checked' : '' ?>></td>
                    </tr>
                    <tr>
                        <td>Messagerie</td>
                        <td class="centre"><input type="checkbox" name="perm_gestionnaire_chat" value="1" <?= (int) $s('perm_gestionnaire_chat', 1) === 1 ? 'checked' : '' ?>></td>
                        <td class="centre"><input type="checkbox" name="perm_consultant_chat" value="1" <?= (int) $s('perm_consultant_chat', 1) === 1 ? 'checked' : '' ?>></td>
                        <td class="centre"><input type="checkbox" name="perm_lecteur_chat" value="1" <?= (int) $s('perm_lecteur_chat', 1) === 1 ? 'checked' : '' ?>></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-entete"><h3>✉️ Courriel (SMTP)</h3></div>
        <div class="card-corps">
            <div class="ligne-champs">
                <div class="form-groupe">
                    <label for="smtp_host">Serveur SMTP</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="<?= e($s('smtp_host')) ?>" placeholder="smtp.mondomaine.fr">
                </div>
                <div class="form-groupe">
                    <label for="smtp_port">Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" value="<?= e($s('smtp_port', 587)) ?>">
                </div>
                <div class="form-groupe">
                    <label for="smtp_secure">Chiffrement</label>
                    <select id="smtp_secure" name="smtp_secure">
                        <option value="tls" <?= $s('smtp_secure') === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                        <option value="ssl" <?= $s('smtp_secure') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="" <?= $s('smtp_secure') === '' ? 'selected' : '' ?>>Aucun</option>
                    </select>
                </div>
                <div class="form-groupe">
                    <label for="smtp_user">Utilisateur SMTP</label>
                    <input type="text" id="smtp_user" name="smtp_user" value="<?= e($s('smtp_user')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="smtp_pass">Mot de passe SMTP</label>
                    <input type="password" id="smtp_pass" name="smtp_pass" placeholder="(laisser vide pour conserver)" autocomplete="off">
                </div>
                <div class="form-groupe">
                    <label for="smtp_from">Adresse d'expédition</label>
                    <input type="email" id="smtp_from" name="smtp_from" value="<?= e($s('smtp_from')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="smtp_from_nom">Nom d'expéditeur</label>
                    <input type="text" id="smtp_from_nom" name="smtp_from_nom" value="<?= e($s('smtp_from_nom')) ?>">
                </div>
            </div>
            <label class="check"><input type="checkbox" name="email_notifications" value="1" <?= (int) $s('email_notifications', 0) === 1 ? 'checked' : '' ?>> Envoyer les notifications de rappel par email</label>
        </div>
    </div>

    <div class="card" id="societe">
        <div class="card-entete"><h3>🏢 Société et courriers</h3></div>
        <div class="card-corps">
            <p class="aide mb-2">Ces informations alimentent les modèles de courriers (relances, mises en demeure, déclarations de créance).</p>
            <div class="ligne-champs">
                <div class="form-groupe">
                    <label for="societe_signataire">Signataire par défaut</label>
                    <input type="text" id="societe_signataire" name="societe_signataire" value="<?= e($s('societe_signataire')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="societe_telephone">Téléphone</label>
                    <input type="text" id="societe_telephone" name="societe_telephone" value="<?= e($s('societe_telephone')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="societe_email">E-mail</label>
                    <input type="email" id="societe_email" name="societe_email" value="<?= e($s('societe_email')) ?>">
                </div>
                <div class="form-groupe">
                    <label for="courrier_delai_jours">Délai accordé dans les mises en demeure (jours)</label>
                    <input type="number" id="courrier_delai_jours" name="courrier_delai_jours" min="1" max="90" value="<?= e($s('courrier_delai_jours', 8)) ?>">
                </div>
            </div>
            <div class="form-groupe">
                <label for="societe_adresse">Adresse (plusieurs lignes possibles)</label>
                <textarea id="societe_adresse" name="societe_adresse" rows="3"><?= e($s('societe_adresse')) ?></textarea>
            </div>
            <div class="form-groupe">
                <label for="societe_iban">IBAN pour les règlements par virement</label>
                <input type="text" id="societe_iban" name="societe_iban" value="<?= e($s('societe_iban')) ?>">
            </div>
            <a href="<?= url('/parametres/modeles') ?>" class="btn btn-secondaire btn-petit">Gérer les modèles de courriers →</a>
        </div>
    </div>

    <div class="card" id="recouvrement">
        <div class="card-entete"><h3>⚖️ Recouvrement : pénalités et procédures collectives</h3></div>
        <div class="card-corps">
            <div class="ligne-champs">
                <div class="form-groupe">
                    <label for="penalite_taux_annuel">Taux annuel des pénalités de retard (%)</label>
                    <input type="text" inputmode="decimal" id="penalite_taux_annuel" name="penalite_taux_annuel" value="<?= e($s('penalite_taux_annuel', '12')) ?>">
                    <div class="aide">À défaut de taux prévu au contrat : au moins 3 fois le taux d'intérêt légal (art. L.441-10 C. com.). Valeur à vérifier auprès de votre conseil.</div>
                </div>
                <div class="form-groupe">
                    <label for="penalite_indemnite_forfaitaire">Indemnité forfaitaire de recouvrement (€)</label>
                    <input type="text" inputmode="decimal" id="penalite_indemnite_forfaitaire" name="penalite_indemnite_forfaitaire" value="<?= e($s('penalite_indemnite_forfaitaire', '40')) ?>">
                    <div class="aide">40 € par facture impayée entre professionnels (art. D.441-5 C. com.).</div>
                </div>
                <div class="form-groupe">
                    <label for="creance_delai_mois">Délai de déclaration de créance (mois)</label>
                    <input type="number" id="creance_delai_mois" name="creance_delai_mois" min="1" max="12" value="<?= e($s('creance_delai_mois', 2)) ?>">
                    <div class="aide">2 mois après la publication au BODACC (4 mois si le créancier est domicilié hors de France métropolitaine) — art. R.622-24 C. com.</div>
                </div>
            </div>
            <label class="check"><input type="checkbox" name="penalite_activer_cron" value="1" <?= (int) $s('penalite_activer_cron', 1) === 1 ? 'checked' : '' ?>> Recalculer chaque jour les pénalités des litiges en mode automatique (cron)</label>
            <label class="check"><input type="checkbox" name="bodacc_surveillance_active" value="1" <?= (int) $s('bodacc_surveillance_active', 1) === 1 ? 'checked' : '' ?>> Surveiller le BODACC : alerter si un client en litige ouvre une procédure collective (cron)</label>
            <div class="form-groupe mt-2" style="max-width:280px">
                <label for="bodacc_surveillance_lot">Clients contrôlés par exécution du cron</label>
                <input type="number" id="bodacc_surveillance_lot" name="bodacc_surveillance_lot" min="1" max="500" value="<?= e($s('bodacc_surveillance_lot', 40)) ?>">
            </div>
        </div>
    </div>

    <div class="card" id="securite-avancee">
        <div class="card-entete"><h3>🔐 Sécurité avancée</h3></div>
        <div class="card-corps">
            <div class="form-groupe" style="max-width:420px">
                <label for="2fa_obligatoire">Double authentification (2FA)</label>
                <select id="2fa_obligatoire" name="2fa_obligatoire">
                    <option value="0" <?= (string) $s('2fa_obligatoire', '0') === '0' ? 'selected' : '' ?>>Facultative (chaque utilisateur choisit)</option>
                    <option value="admin" <?= $s('2fa_obligatoire') === 'admin' ? 'selected' : '' ?>>Obligatoire pour les administrateurs</option>
                    <option value="tous" <?= $s('2fa_obligatoire') === 'tous' ? 'selected' : '' ?>>Obligatoire pour tous les utilisateurs</option>
                </select>
                <div class="aide">Les utilisateurs concernés sont invités à l'activer dès leur prochaine connexion. Un administrateur peut réinitialiser la 2FA d'un utilisateur qui a perdu son téléphone.</div>
            </div>
            <div class="form-groupe" style="max-width:420px">
                <label for="partage_expiration_defaut_jours">Expiration par défaut des accès en lecture seule (jours)</label>
                <input type="number" id="partage_expiration_defaut_jours" name="partage_expiration_defaut_jours" min="0" max="3650" value="<?= e($s('partage_expiration_defaut_jours', 30)) ?>">
                <div class="aide">Appliquée aux nouveaux codes et liens de partage sans date saisie. 0 = pas d'expiration automatique.</div>
            </div>
            <label class="check"><input type="checkbox" name="clamav_actif" value="1" <?= (int) $s('clamav_actif', 0) === 1 ? 'checked' : '' ?>> Analyser les pièces jointes avec l'antivirus ClamAV</label>
            <div class="ligne-champs mt-1">
                <div class="form-groupe">
                    <label for="clamav_adresse">Adresse du démon clamd</label>
                    <input type="text" id="clamav_adresse" name="clamav_adresse" value="<?= e($s('clamav_adresse')) ?>" placeholder="unix:///var/run/clamav/clamd.ctl ou tcp://127.0.0.1:3310">
                </div>
            </div>
            <label class="check"><input type="checkbox" name="clamav_bloquer_si_indisponible" value="1" <?= (int) $s('clamav_bloquer_si_indisponible', 1) === 1 ? 'checked' : '' ?>> Refuser les fichiers si l'antivirus est injoignable</label>
        </div>
    </div>

    <div class="card" id="blg">
        <div class="card-entete"><h3>🔗 ERP BLG (blgCloud)</h3></div>
        <div class="card-corps">
            <p class="aide mb-2">La synchronisation repose sur les exports CSV / Excel de l'ERP déposés dans un dossier surveillé (factures impayées, règlements, clients). Les noms de fichiers doivent contenir <em>facture</em>/<em>impaye</em>, <em>reglement</em>/<em>paiement</em> ou <em>client</em>.</p>
            <label class="check"><input type="checkbox" name="blg_actif" value="1" <?= (int) $s('blg_actif', 0) === 1 ? 'checked' : '' ?>> Activer la synchronisation automatique (cron quotidien)</label>
            <div class="form-groupe mt-2">
                <label for="blg_dossier">Dossier surveillé</label>
                <input type="text" id="blg_dossier" name="blg_dossier" value="<?= e($s('blg_dossier')) ?>" placeholder="blg  (relatif à storage/)  ou  /chemin/absolu/vers/exports">
                <div class="aide">Les fichiers traités sont déplacés dans le sous-dossier <code>archive/</code>. La correspondance des colonnes se règle depuis <a href="<?= url('/import') ?>">Import de données</a> (bouton « Enregistrer comme profil BLG »).</div>
            </div>
        </div>
    </div>

    <div class="card" id="rapport">
        <div class="card-entete"><h3>📧 Rapport hebdomadaire</h3></div>
        <div class="card-corps">
            <label class="check"><input type="checkbox" name="rapport_hebdo_actif" value="1" <?= (int) $s('rapport_hebdo_actif', 0) === 1 ? 'checked' : '' ?>> Envoyer chaque semaine une synthèse (avec classeur Excel) — nécessite le SMTP</label>
            <div class="ligne-champs mt-1">
                <div class="form-groupe">
                    <label for="rapport_hebdo_jour">Jour d'envoi</label>
                    <select id="rapport_hebdo_jour" name="rapport_hebdo_jour">
                        <?php foreach ([1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'] as $n => $jour): ?>
                            <option value="<?= $n ?>" <?= (int) $s('rapport_hebdo_jour', 1) === $n ? 'selected' : '' ?>><?= $jour ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-groupe">
                    <label for="rapport_hebdo_destinataires">Destinataires</label>
                    <input type="text" id="rapport_hebdo_destinataires" name="rapport_hebdo_destinataires" value="<?= e($s('rapport_hebdo_destinataires', 'admin')) ?>">
                    <div class="aide"><code>admin</code> (administrateurs), <code>tous</code> (admins + gestionnaires) ou une liste d'adresses séparées par des virgules.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-corps flex">
            <button type="submit" class="btn btn-vert">Enregistrer les paramètres</button>
        </div>
    </div>
</form>
