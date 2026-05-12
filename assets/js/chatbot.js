/**
 * Widget chatbot citoyen — Phase X
 * Floating bottom-right, indépendant de toute librairie externe.
 */
(function () {
    if (typeof window === "undefined") return;
    if (window.__maireChatbotLoaded) return;
    window.__maireChatbotLoaded = true;

    var endpoint = (document.currentScript && document.currentScript.dataset.endpoint) ||
        document.documentElement.dataset.chatbotEndpoint || null;
    if (!endpoint) {
        // Pas d'endpoint configuré => widget désactivé (commune non éligible).
        return;
    }

    var openLabel = "💬 Assistant citoyen";
    var welcome = "Bonjour ! Posez votre question sur les démarches, paiements, signalements ou consultations.";

    var btn, panel;

    function build() {
        btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = openLabel;
        btn.setAttribute("aria-label", "Ouvrir l'assistant citoyen");
        btn.style.cssText = [
            "position:fixed", "right:20px", "bottom:20px", "z-index:1100",
            "background:#0c4a3e", "color:#fff", "border:none",
            "padding:0.75rem 1.1rem", "border-radius:999px",
            "box-shadow:0 8px 22px rgba(12,74,62,0.42)",
            "cursor:pointer", "font-weight:600", "font-size:0.95rem"
        ].join(";");
        btn.addEventListener("click", toggle);
        document.body.appendChild(btn);

        panel = document.createElement("section");
        panel.setAttribute("role", "dialog");
        panel.setAttribute("aria-label", "Assistant citoyen");
        panel.style.cssText = [
            "position:fixed", "right:20px", "bottom:78px", "z-index:1100",
            "width:min(360px,calc(100vw - 40px))", "max-height:70vh",
            "background:#ffffff", "border-radius:14px",
            "box-shadow:0 18px 40px rgba(15,23,42,0.28)",
            "display:none", "flex-direction:column", "overflow:hidden",
            "font-family:inherit", "border:1px solid #e2e8f0"
        ].join(";");
        panel.innerHTML = [
            "<header style='background:#0c4a3e;color:#fff;padding:0.85rem 1rem;display:flex;justify-content:space-between;align-items:center;'>",
            "  <strong>💬 Assistant citoyen</strong>",
            "  <button type='button' aria-label='Fermer' style='background:transparent;color:#fff;border:none;font-size:1.4rem;cursor:pointer;line-height:1;'>×</button>",
            "</header>",
            "<div data-role='messages' style='flex:1;overflow-y:auto;padding:0.85rem;display:flex;flex-direction:column;gap:0.55rem;background:#f8fafc;'></div>",
            "<form style='display:flex;gap:0.4rem;padding:0.7rem;border-top:1px solid #e2e8f0;background:#fff;'>",
            "  <input type='text' name='question' required maxlength='500' placeholder='Ex : comment obtenir un extrait de naissance ?' autocomplete='off' style='flex:1;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;font:inherit;'>",
            "  <button type='submit' style='padding:0.55rem 0.9rem;background:#0c4a3e;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;'>Envoyer</button>",
            "</form>"
        ].join("");
        document.body.appendChild(panel);

        panel.querySelector("button[aria-label='Fermer']").addEventListener("click", toggle);
        panel.querySelector("form").addEventListener("submit", onSubmit);

        // Message d'accueil uniquement : aucune réponse / suggestion automatique.
        // Tout ne s'affiche qu'après une vraie question de l'utilisateur.
        addBotMessage(welcome);
    }

    function toggle() {
        var open = panel.style.display !== "none" && panel.style.display !== "";
        if (open) {
            panel.style.display = "none";
        } else {
            panel.style.display = "flex";
            var input = panel.querySelector("input[name='question']");
            if (input) setTimeout(function () { input.focus(); }, 60);
        }
    }

    function bubble(text, who, raw) {
        var div = document.createElement("div");
        div.style.cssText = [
            "max-width:88%",
            "padding:0.55rem 0.7rem",
            "border-radius:12px",
            "line-height:1.45",
            "font-size:0.92rem",
            "white-space:pre-wrap"
        ].join(";");
        if (who === "user") {
            div.style.alignSelf = "flex-end";
            div.style.background = "#0c4a3e";
            div.style.color = "#fff";
            div.style.borderBottomRightRadius = "2px";
            div.textContent = text;
        } else {
            div.style.alignSelf = "flex-start";
            div.style.background = "#fff";
            div.style.color = "#1e293b";
            div.style.border = "1px solid #e2e8f0";
            div.style.borderBottomLeftRadius = "2px";
            if (raw === true) {
                div.innerHTML = text;
            } else {
                div.textContent = text;
            }
        }
        return div;
    }

    function addUserMessage(text) {
        panel.querySelector("[data-role='messages']").appendChild(bubble(text, "user"));
        scrollBottom();
    }

    function addBotMessage(text, raw) {
        panel.querySelector("[data-role='messages']").appendChild(bubble(text, "bot", raw));
        scrollBottom();
    }

    function addActionLink(href, label) {
        var box = document.createElement("a");
        box.href = href;
        box.textContent = "▸ " + label;
        box.style.cssText = [
            "align-self:flex-start", "padding:0.45rem 0.75rem", "background:#16a34a",
            "color:#fff", "border-radius:8px", "text-decoration:none", "font-weight:600",
            "font-size:0.88rem"
        ].join(";");
        panel.querySelector("[data-role='messages']").appendChild(box);
        scrollBottom();
    }

    function addSuggestions(list) {
        if (!list || !list.length) return;
        var wrap = document.createElement("div");
        wrap.style.cssText = "display:flex;flex-wrap:wrap;gap:0.35rem;align-self:flex-start;";
        list.forEach(function (s) {
            var b = document.createElement("button");
            b.type = "button";
            b.textContent = s.question;
            b.style.cssText = [
                "background:#fff", "border:1px solid #cbd5e1", "color:#0c4a3e",
                "border-radius:999px", "padding:0.35rem 0.7rem", "font-size:0.85rem",
                "cursor:pointer"
            ].join(";");
            b.addEventListener("click", function () {
                panel.querySelector("input[name='question']").value = s.question;
                onSubmit({ preventDefault: function () {} });
            });
            wrap.appendChild(b);
        });
        panel.querySelector("[data-role='messages']").appendChild(wrap);
        scrollBottom();
    }

    function scrollBottom() {
        var box = panel.querySelector("[data-role='messages']");
        box.scrollTop = box.scrollHeight;
    }

    function onSubmit(e) {
        e.preventDefault();
        var input = panel.querySelector("input[name='question']");
        var q = input.value.trim();
        if (q === "") return;
        addUserMessage(q);
        input.value = "";
        ask(q);
    }

    function escapeHtml(s) {
        return (s || "").replace(/[&<>"']/g, function (c) {
            return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c];
        });
    }

    function ask(question) {
        var form = new FormData();
        form.append("question", question || "Bonjour");
        fetch(endpoint, { method: "POST", body: form, credentials: "same-origin" })
            .then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
            .then(function (out) {
                if (out.status !== 200) {
                    addBotMessage("⚠ " + (out.json && out.json.error ? out.json.error : "Erreur de communication"));
                    return;
                }
                var j = out.json;
                addBotMessage(j.reponse, true);
                if (j.lien_action && j.libelle_action) {
                    addActionLink(j.lien_action, j.libelle_action);
                }
                if (Array.isArray(j.suggestions) && j.suggestions.length) {
                    var lead = document.createElement("p");
                    lead.textContent = "Questions populaires :";
                    lead.style.cssText = "margin:0.4rem 0 0;font-size:0.85rem;color:#64748b;align-self:flex-start;";
                    panel.querySelector("[data-role='messages']").appendChild(lead);
                    addSuggestions(j.suggestions);
                }
            })
            .catch(function () {
                addBotMessage("⚠ Impossible de joindre le serveur, vérifiez votre connexion.");
            });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", build);
    } else {
        build();
    }
})();
