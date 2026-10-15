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

    // --- Messagerie : rafraichissement automatique (long polling minimal) ---
    function initChatFlux() {
        const conteneur = document.getElementById('chat-fil');
        if (!conteneur) return;

        const urlBase = conteneur.getAttribute('data-url');
        if (!urlBase) return;

        let dernierId = 0;
        const messagesDom = conteneur.querySelectorAll('.message[data-id]');
        messagesDom.forEach(function (m) {
            const id = parseInt(m.getAttribute('data-id'), 10);
            if (!isNaN(id) && id > dernierId) {
                dernierId = id;
            }
        });

        const authId = parseInt(conteneur.getAttribute('data-auth') || (document.body.getAttribute('data-auth-id') || '0'), 10);

        function scrollBas() {
            const bas = document.getElementById('bas');
            if (bas) {
                bas.scrollIntoView({ behavior: 'auto' });
            } else {
                conteneur.scrollTop = conteneur.scrollHeight;
            }
        }

        scrollBas();

        function charger() {
            const url = urlBase + (urlBase.indexOf('?') >= 0 ? '&' : '?') + 'after=' + encodeURIComponent(dernierId);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok && Array.isArray(data.messages) && data.messages.length) {
                        data.messages.forEach(function (msg) {
                            const div = document.createElement('div');
                            div.className = 'message' + ((msg.user_id === authId) ? ' mine' : '');
                            div.setAttribute('data-id', msg.id);
                            div.innerHTML = '<div class="message-entete"><strong>' + escapeHtml(msg.auteur) + '</strong><span>' + escapeHtml(msg.quand) + '</span></div><div class="message-corps">' + escapeHtml(msg.contenu) + '</div>';
                            conteneur.insertBefore(div, document.getElementById('bas'));
                            if (msg.id > dernierId) {
                                dernierId = msg.id;
                            }
                        });
                        scrollBas();
                    }
                })
                .catch(function () { })
                .finally(function () {
                    setTimeout(charger, 2500);
                });
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
            });
        }

        setTimeout(charger, 2500);
    }

    window.addEventListener('DOMContentLoaded', initChatFlux);

    // --- PWA : installation, détection et guide iOS/Android ---
    function initPWA() {
        const banner = document.getElementById('pwa-install-banner');
        const btnInstall = document.getElementById('pwa-install-btn');
        const btnCloseBanner = document.getElementById('pwa-install-close');
        const modalIOS = document.getElementById('pwa-ios-guide');
        const modalAndroid = document.getElementById('pwa-android-guide');
        const modals = [modalIOS, modalAndroid];

        let deferredPrompt = null;
        let dismissedUntil = 0;
        const DISMISS_MS = 7 * 24 * 60 * 60 * 1000;

        function isStandalone() {
            if (typeof window === 'undefined') return false;
            const mq = window.matchMedia && window.matchMedia('(display-mode: standalone)');
            if (mq && mq.matches) return true;
            if (window.navigator && window.navigator.standalone === true) return true;
            if (document.referrer && document.referrer.startsWith('android-app://')) return true;
            return false;
        }

        function isIOS() {
            if (typeof navigator === 'undefined') return false;
            const ua = navigator.userAgent || '';
            return /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
        }

        function isAndroid() {
            if (typeof navigator === 'undefined') return false;
            const ua = navigator.userAgent || '';
            return /Android/i.test(ua);
        }

        function isSafari() {
            if (typeof navigator === 'undefined') return false;
            const ua = navigator.userAgent || '';
            const isChrome = /Chrome/i.test(ua) || /CriOS/i.test(ua);
            const isEdge = /Edg/i.test(ua);
            return (/Safari/i.test(ua) && !isChrome && !isEdge);
        }

        function canShowBanner() {
            if (isStandalone()) return false;
            const now = Date.now();
            if (dismissedUntil > now) return false;
            const isMobile = window.matchMedia && window.matchMedia('(max-width: 1024px)').matches;
            if (!isMobile && !isIOS() && !isAndroid()) return false;
            return true;
        }

        function showBanner() {
            if (!banner || !canShowBanner()) return;
            banner.hidden = false;
        }

        function hideBanner() {
            if (banner) banner.hidden = true;
        }

        function openModal(modal) {
            if (!modal) return;
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function closeModals() {
            modals.forEach(m => { if (m) m.hidden = true; });
            document.body.style.overflow = '';
        }

        function rememberDismiss(days) {
            const d = days || 7;
            dismissedUntil = Date.now() + d * 24 * 60 * 60 * 1000;
            try { localStorage.setItem('pwa-install-dismissed', String(dismissedUntil)); } catch (e) {}
        }

        function restoreDismiss() {
            try {
                const v = localStorage.getItem('pwa-install-dismissed');
                if (v) dismissedUntil = parseInt(v, 10) || 0;
            } catch (e) { dismissedUntil = 0; }
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js').catch((err) => {
                    console.warn('PWA: échec de l\'enregistrement du Service Worker', err);
                });
            });
        }

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (canShowBanner()) {
                showBanner();
            }
        });

        window.addEventListener('appinstalled', () => {
            deferredPrompt = null;
            hideBanner();
            closeModals();
            try { localStorage.removeItem('pwa-install-dismissed'); } catch (e) {}
        });

        if (btnInstall) {
            btnInstall.addEventListener('click', async () => {
                hideBanner();
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    try { await deferredPrompt.userChoice; } catch (e) {}
                    deferredPrompt = null;
                    return;
                }
                if (isIOS()) {
                    openModal(modalIOS);
                } else if (isAndroid()) {
                    openModal(modalAndroid);
                } else {
                    openModal(modalAndroid);
                }
            });
        }

        if (btnCloseBanner) {
            btnCloseBanner.addEventListener('click', () => {
                hideBanner();
                rememberDismiss(7);
            });
        }

        modals.forEach(m => {
            if (!m) return;
            m.querySelectorAll('[data-close]').forEach(el => {
                el.addEventListener('click', closeModals);
            });
            m.addEventListener('click', (e) => {
                if (e.target === m) closeModals();
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModals();
        });

        restoreDismiss();
        setTimeout(() => {
            if (isStandalone()) return;
            if (isIOS() && isSafari()) {
                if (canShowBanner()) {
                    showBanner();
                }
            } else if (!deferredPrompt) {
                if (canShowBanner()) {
                    showBanner();
                }
            }
        }, 1200);

        if (window.matchMedia) {
            const mqStandalone = window.matchMedia('(display-mode: standalone)');
            const handler = (e) => {
                if (e.matches || isStandalone()) {
                    hideBanner();
                    closeModals();
                }
            };
            if (mqStandalone.addEventListener) mqStandalone.addEventListener('change', handler);
            else if (mqStandalone.addListener) mqStandalone.addListener(handler);
        }
    }

    window.addEventListener('DOMContentLoaded', initPWA);
})();
