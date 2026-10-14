/* Interface - Gestion des litiges */
(function () {
    'use strict';

    // --- Menu mobile ---
    document.addEventListener('click', function (e) {
        const burger = e.target.closest('[data-burger]');
        if (burger) {
            document.querySelector('.sidebar')?.classList.toggle('ouvert');
        }
    });

    // --- Confirmation avant action sensible ---
    document.addEventListener('submit', function (e) {
        const form = e.target;
        const message = form.getAttribute('data-confirmer');
        if (message && !window.confirm(message)) {
            e.preventDefault();
            return;
        }
        // Anti double-envoi
        const bouton = form.querySelector('[type=submit]');
        if (bouton && !form.hasAttribute('data-sans-verrou')) {
            setTimeout(function () {
                bouton.disabled = true;
                bouton.dataset.texte = bouton.textContent;
                bouton.textContent = 'Traitement…';
            }, 0);
        }
    });

    // --- Onglets ---
    document.addEventListener('click', function (e) {
        const onglet = e.target.closest('[data-onglet]');
        if (!onglet) return;
        e.preventDefault();
        const groupe = onglet.closest('.onglets');
        const cible = onglet.getAttribute('data-onglet');
        groupe.querySelectorAll('[data-onglet]').forEach(function (b) { b.classList.remove('is-actif'); });
        groupe.querySelectorAll('[data-onglet]').forEach(function (b) {
            document.getElementById(b.getAttribute('data-onglet'))?.classList.remove('is-actif');
        });
        onglet.classList.add('is-actif');
        document.getElementById(cible)?.classList.add('is-actif');
        if (history.replaceState) history.replaceState(null, '', '#' + cible);
    });

    // Activer l'onglet cible a l'arrivee depuis une ancre.
    window.addEventListener('DOMContentLoaded', function () {
        const hash = (location.hash || '').replace('#', '');
        if (hash) {
            const panneau = document.querySelector('.onglet-panneau' + '#' + CSS.escape(hash));
            if (panneau) {
                document.querySelector('.onglets [data-onglet="' + hash + '"]')?.click();
            }
        }

        // Disparition automatique des messages flash.
        document.querySelectorAll('.alerte[data-auto]').forEach(function (a) {
            setTimeout(function () {
                a.style.transition = 'opacity .4s';
                a.style.opacity = '0';
                setTimeout(function () { a.remove(); }, 400);
            }, 5000);
        });
    });

    // --- Copier dans le presse-papiers ---
    document.addEventListener('click', function (e) {
        const bouton = e.target.closest('[data-copier]');
        if (!bouton) return;
        e.preventDefault();
        const texte = bouton.getAttribute('data-copier');
        navigator.clipboard.writeText(texte).then(function () {
            const ancien = bouton.textContent;
            bouton.textContent = 'Copie !';
            setTimeout(function () { bouton.textContent = ancien; }, 1500);
        });
    });

    // --- Recherche d'entreprise (SIREN / nom) ---
    function initRechercheEntreprise() {
        const zone = document.getElementById('zone-api');
        if (!zone) return;

        const champ = zone.querySelector('[data-api-recherche]');
        const resultats = zone.querySelector('[data-api-resultats]');
        const champSiren = document.querySelector('[data-api-siren]');
        const boutonDetail = zone.querySelector('[data-api-detail]');
        let timer = null;

        function remplirChamps(donnees) {
            const map = {
                'siren': donnees.siren, 'siret': donnees.siret, 'tva_intra': donnees.tva_intra,
                'forme_juridique': donnees.forme_juridique, 'ape_naf': donnees.ape_naf,
                'adresse': donnees.adresse, 'complement_adresse': donnees.complement_adresse,
                'code_postal': donnees.code_postal, 'ville': donnees.ville,
                'effectif': donnees.effectif, 'date_creation': donnees.date_creation,
                'capital': donnees.capital, 'chiffre_affaires': donnees.chiffre_affaires,
                'resultat_net': donnees.resultat_net, 'statut_entreprise': donnees.statut_entreprise,
                'procedure_collective': donnees.procedure_collective, 'libelle_ape': donnees.libelle_ape,
                'score_solvabilite': donnees.score_solvabilite
            };
            Object.keys(map).forEach(function (id) {
                const input = document.getElementById(id);
                if (input && map[id] !== null && map[id] !== undefined && map[id] !== '') {
                    input.value = map[id];
                }
            });
            const raison = document.getElementById('raison_sociale');
            if (raison && donnees.raison_sociale && !raison.value) {
                raison.value = donnees.raison_sociale;
            }
            if (resultats) resultats.innerHTML = '';
        }

        function afficherResultats(items) {
            if (!resultats) return;
            if (!items.length) {
                resultats.innerHTML = '<div class="item texte-doux">Aucun resultat.</div>';
                return;
            }
            resultats.innerHTML = items.map(function (r) {
                return '<div class="item" data-siren="' + r.siren + '">'
                    + '<strong>' + escapeHtml(r.nom || '(sans denomination)') + '</strong>'
                    + '<span>' + escapeHtml(r.siren) + ' — ' + escapeHtml(r.adresse || '') + '</span>'
                    + (r.procedure ? '<span class="texte-rouge"> ⚠ ' + escapeHtml(r.procedure) + '</span>' : '')
                    + '</div>';
            }).join('');
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
            });
        }

        if (champ) {
            champ.addEventListener('input', function () {
                clearTimeout(timer);
                const q = champ.value.trim();
                if (q.length < 2) { resultats.innerHTML = ''; return; }
                timer = setTimeout(function () {
                    fetch(zone.getAttribute('data-url-recherche') + '?q=' + encodeURIComponent(q), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { afficherResultats(data.results || []); })
                    .catch(function () { resultats.innerHTML = '<div class="item texte-rouge">Erreur de connexion a l\'API.</div>'; });
                }, 350);
            });
        }

        document.addEventListener('click', function (e) {
            const item = e.target.closest('.resultats-api .item[data-siren]');
            if (!item) return;
            const siren = item.getAttribute('data-siren');
            if (champSiren) champSiren.value = siren;
            chargerDetail(siren);
        });

        if (boutonDetail) {
            boutonDetail.addEventListener('click', function (e) {
                e.preventDefault();
                const siren = (champSiren && champSiren.value.trim()) || (champ && champ.value.trim());
                if (siren) chargerDetail(siren);
            });
        }

        function chargerDetail(identifiant) {
            if (resultats) resultats.innerHTML = '<div class="item texte-doux">Recherche…</div>';
            fetch(zone.getAttribute('data-url-detail') + '/' + encodeURIComponent(identifiant), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    if (resultats) resultats.innerHTML = '<div class="item texte-rouge">' + escapeHtml(data.message || 'Introuvable') + '</div>';
                    return;
                }
                remplirChamps(data._champs_client || data);
            })
            .catch(function () {
                if (resultats) resultats.innerHTML = '<div class="item texte-rouge">Erreur de connexion.</div>';
            });
        }
    }

    window.addEventListener('DOMContentLoaded', initRechercheEntreprise);
})();
