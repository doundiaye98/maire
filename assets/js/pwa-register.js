/**
 * Enregistre le service worker et affiche un bouton « 📲 Installer l'app »
 * persistant sur le site (PWA — Phase X).
 *
 * Comportement :
 *   - Le bouton est TOUJOURS visible (sauf si l'app est déjà ouverte
 *     en mode standalone, c.-à-d. déjà installée et lancée).
 *   - Si le navigateur a déclenché `beforeinstallprompt` (Chrome / Edge /
 *     Android Chrome), un clic ouvre la vraie boîte d'installation native.
 *   - Sinon (Safari iOS, Firefox, etc.), un clic affiche un mode d'emploi
 *     adapté au navigateur courant.
 */
(function () {
    if (typeof window === "undefined") return;

    // -- Enregistrement du Service Worker ---------------------------------
    if ("serviceWorker" in navigator) {
        var basePath = location.pathname.replace(/[^/]+$/, "");
        var swUrl = basePath + "service-worker.js";
        var match = location.pathname.match(/^(.*?\/)(admin|citoyen|super-admin|mairie|presentation)\//);
        if (match) {
            basePath = match[1];
            swUrl = basePath + "service-worker.js";
        }
        window.addEventListener("load", function () {
            navigator.serviceWorker.register(swUrl, { scope: basePath }).catch(function () {
                // Silencieux : pas de SW disponible en local sans HTTPS, c'est OK.
            });
        });
    }

    // -- Détection mode standalone (app déjà installée et ouverte) --------
    function isStandalone() {
        if (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches) return true;
        if (window.navigator.standalone === true) return true; // iOS
        return false;
    }
    if (isStandalone()) return; // inutile d'afficher un bouton « installer »

    // -- État du prompt natif ----------------------------------------------
    var deferredPrompt = null;
    var installBtn = null;
    var modal = null;

    // -- UI : bouton flottant ---------------------------------------------
    function buildButton() {
        if (installBtn) return;
        installBtn = document.createElement("button");
        installBtn.type = "button";
        installBtn.textContent = "📲 Installer l'app";
        installBtn.setAttribute("aria-label", "Installer l'application sur cet appareil");
        installBtn.style.cssText = [
            "position:fixed",
            "bottom:18px",
            "left:18px",
            "z-index:1000",
            "background:#0c4a3e",
            "color:#fff",
            "border:none",
            "padding:0.65rem 1rem",
            "border-radius:999px",
            "box-shadow:0 6px 18px rgba(12,74,62,0.35)",
            "cursor:pointer",
            "font-weight:600",
            "font-size:0.92rem"
        ].join(";");
        installBtn.addEventListener("click", onInstallClick);
        document.body.appendChild(installBtn);
    }

    function onInstallClick() {
        if (deferredPrompt) {
            // Prompt natif disponible (Chrome / Edge / Android).
            try {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.finally(function () {
                    deferredPrompt = null;
                });
            } catch (e) {
                showManualInstructions();
            }
            return;
        }
        // Fallback : instructions selon le navigateur.
        showManualInstructions();
    }

    // -- Détection navigateur ---------------------------------------------
    function detectBrowser() {
        var ua = navigator.userAgent || "";
        var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
        var isSafari = /^((?!chrome|android).)*safari/i.test(ua);
        var isFirefox = /firefox/i.test(ua);
        var isEdge = /edg\//i.test(ua);
        var isChrome = /chrome/i.test(ua) && !isEdge;
        var isAndroid = /android/i.test(ua);
        return {
            ios: isIOS,
            safari: isSafari,
            firefox: isFirefox,
            edge: isEdge,
            chrome: isChrome,
            android: isAndroid,
            desktop: !isAndroid && !isIOS
        };
    }

    function instructionsHtml() {
        var b = detectBrowser();
        if (b.ios) {
            return "<p>Sur iPhone / iPad :</p>" +
                "<ol><li>Appuyez sur le bouton de partage <strong>⬆</strong> en bas de Safari.</li>" +
                "<li>Faites défiler et choisissez <strong>« Sur l'écran d'accueil »</strong>.</li>" +
                "<li>Confirmez avec <strong>« Ajouter »</strong>.</li></ol>";
        }
        if (b.android && b.chrome) {
            return "<p>Sur Android (Chrome) :</p>" +
                "<ol><li>Ouvrez le menu <strong>⋮</strong> en haut à droite.</li>" +
                "<li>Choisissez <strong>« Installer l'application »</strong> ou <strong>« Ajouter à l'écran d'accueil »</strong>.</li></ol>";
        }
        if (b.firefox) {
            return "<p>Sur Firefox :</p>" +
                "<ol><li>Cliquez sur le menu <strong>☰</strong> du navigateur.</li>" +
                "<li>Choisissez <strong>« Installer »</strong> ou ajoutez un raccourci à l'écran d'accueil.</li></ol>" +
                "<p style='color:#64748b;font-size:0.88rem'>Astuce : Chrome ou Edge proposent une installation native plus simple.</p>";
        }
        if (b.desktop && (b.chrome || b.edge)) {
            return "<p>Sur ordinateur (Chrome / Edge) :</p>" +
                "<ol><li>Cliquez sur l'icône <strong>⊕</strong> à droite de la barre d'adresse.</li>" +
                "<li>Choisissez <strong>« Installer »</strong>.</li></ol>" +
                "<p style='color:#64748b;font-size:0.88rem'>Si l'icône n'apparaît pas, rechargez la page après quelques secondes.</p>";
        }
        return "<p>Pour installer l'application :</p>" +
            "<ul><li>Ouvrez le menu de votre navigateur.</li>" +
            "<li>Cherchez l'option <strong>« Installer »</strong> ou <strong>« Ajouter à l'écran d'accueil »</strong>.</li></ul>";
    }

    function showManualInstructions() {
        if (modal) { modal.style.display = "flex"; return; }
        modal = document.createElement("div");
        modal.style.cssText = [
            "position:fixed", "inset:0", "background:rgba(15,23,42,0.55)",
            "z-index:1200", "display:flex", "align-items:center",
            "justify-content:center", "padding:1rem", "font-family:inherit"
        ].join(";");

        var box = document.createElement("div");
        box.style.cssText = [
            "background:#fff", "border-radius:14px",
            "max-width:480px", "width:100%",
            "padding:1.4rem 1.5rem", "box-shadow:0 24px 60px rgba(15,23,42,0.35)",
            "color:#1e293b"
        ].join(";");
        box.innerHTML =
            "<h3 style='margin:0 0 0.6rem;color:#0c4a3e;'>📲 Installer l'application</h3>" +
            "<p style='margin:0 0 0.8rem;color:#475569;'>Ajoutez le site comme une application sur votre appareil pour un accès rapide et hors-ligne.</p>" +
            "<div class='maire-pwa-howto' style='line-height:1.5;font-size:0.93rem;'>" + instructionsHtml() + "</div>" +
            "<div style='display:flex;justify-content:flex-end;margin-top:1rem;'>" +
            "  <button type='button' data-role='close' style='background:#0c4a3e;color:#fff;border:none;padding:0.55rem 1rem;border-radius:8px;font-weight:600;cursor:pointer;'>Fermer</button>" +
            "</div>";
        modal.appendChild(box);
        modal.addEventListener("click", function (e) {
            if (e.target === modal) modal.style.display = "none";
        });
        box.querySelector("[data-role='close']").addEventListener("click", function () {
            modal.style.display = "none";
        });
        document.body.appendChild(modal);
    }

    // -- Évènements PWA natifs --------------------------------------------
    window.addEventListener("beforeinstallprompt", function (e) {
        e.preventDefault();
        deferredPrompt = e;
        // Le bouton est déjà affiché — rien d'autre à faire ici.
    });

    window.addEventListener("appinstalled", function () {
        deferredPrompt = null;
        // L'utilisateur a demandé que le bouton reste visible : on le garde,
        // mais on le marque comme « installé ».
        if (installBtn) {
            installBtn.textContent = "✓ App installée";
            installBtn.style.background = "#16a34a";
            installBtn.style.boxShadow = "0 6px 18px rgba(22,163,74,0.35)";
        }
    });

    // -- Lancement --------------------------------------------------------
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", buildButton);
    } else {
        buildButton();
    }
})();
