CREATE DATABASE IF NOT EXISTS mairie_senegal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mairie_senegal;

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    icone VARCHAR(8) DEFAULT '📌'
);

CREATE TABLE IF NOT EXISTS actualites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(180) NOT NULL,
    resume TEXT NOT NULL,
    date_publication DATE NOT NULL
);

CREATE TABLE IF NOT EXISTS messages_contact (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS commune_abonnement (
    id INT PRIMARY KEY DEFAULT 1,
    plan VARCHAR(40) NOT NULL DEFAULT 'municipal_standard',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    auto_renew TINYINT(1) NOT NULL DEFAULT 0,
    renouvellement_jours INT NOT NULL DEFAULT 365,
    suspendu_par_plateforme TINYINT(1) NOT NULL DEFAULT 0,
    suspension_motif VARCHAR(255) DEFAULT NULL,
    suspension_date TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO commune_abonnement (id, plan, actif, date_debut, date_fin, auto_renew, renouvellement_jours)
VALUES (1, 'municipal_standard', 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 0, 365);

-- Comptes super-administrateur de l'éditeur (entreprise qui héberge la plateforme).
-- Distincts du compte institutionnel mairie : sert au suivi et à la suspension des abonnements.
CREATE TABLE IF NOT EXISTS super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    nom VARCHAR(120) NOT NULL DEFAULT '',
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ⚠ COMPTE DE DÉMONSTRATION (commenté par défaut pour la production).
-- Pour le créer en DEV uniquement : décommentez la requête ci-dessous.
-- Identifiants : editeur@demo.rufisque.sn / DemoEditeur2026!
-- INSERT IGNORE INTO super_admins (email, nom, mot_de_passe_hash, actif)
-- VALUES ('editeur@demo.rufisque.sn', 'Éditeur (démonstration)', '$2y$12$8y7eKIqtLsZCi6t2HpjBROV72u8lMlQMjW5kbSUDhfMlgYjIZYbMO', 1);
--
-- ✅ EN PRODUCTION : créez un vrai compte super-admin via l'interface
--    super-admin/login.php ou directement en BDD avec password_hash().

CREATE TABLE IF NOT EXISTS commune_abonnement_historique (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan VARCHAR(40) NOT NULL,
    actif TINYINT(1) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    evenement VARCHAR(40) NOT NULL DEFAULT 'plan_change',
    detail VARCHAR(500) DEFAULT NULL,
    actor_subscriber_id INT NULL,
    actor_source VARCHAR(32) NOT NULL DEFAULT 'inconnu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hist_created (created_at)
);

CREATE TABLE IF NOT EXISTS abonnements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    plan VARCHAR(40) NOT NULL DEFAULT 'municipal_standard',
    role_utilisateur VARCHAR(20) NOT NULL DEFAULT 'subscriber',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    compte_mairie TINYINT(1) NOT NULL DEFAULT 0,
    auto_renew TINYINT(1) NOT NULL DEFAULT 0,
    renouvellement_jours INT NOT NULL DEFAULT 30,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS paiements_abonnements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    abonnement_id INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    montant_fcfa INT NOT NULL DEFAULT 45000,
    devise VARCHAR(10) NOT NULL DEFAULT 'XOF',
    frequence VARCHAR(20) NOT NULL DEFAULT 'mensuel',
    mode_paiement VARCHAR(30) NOT NULL,
    reference_paiement VARCHAR(120) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'valide',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_abonnement_id (abonnement_id),
    INDEX idx_email (email),
    UNIQUE KEY uniq_reference_paiement (reference_paiement),
    CONSTRAINT fk_paiement_abonnement FOREIGN KEY (abonnement_id) REFERENCES abonnements(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS standard_referentiel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categorie ENUM('ecole', 'sante', 'service') NOT NULL,
    nom VARCHAR(180) NOT NULL,
    localisation VARCHAR(220) NOT NULL,
    niveau_ou_type VARCHAR(120) DEFAULT NULL,
    horaires VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO services (nom, description, icone) VALUES
('Etat civil', 'Naissance, mariage, deces et legalisation de documents.', '🪪'),
('Urbanisme', 'Permis de construire, lotissements et travaux.', '🏗️'),
('Action sociale', 'Accompagnement des familles vulnerables.', '🤝'),
('Hygiene', 'Collecte et traitement des dechets.', '♻️');

INSERT INTO actualites (titre, resume, date_publication) VALUES
('Programme municipal de salubrite lance', 'Debut des operations de nettoyage et de sensibilisation.', '2026-04-15'),
('Demarches d etat civil simplifiees', 'Le service numerique permet de gagner du temps.', '2026-04-09'),
('Eclairage public solaire', 'De nouveaux lampadaires sont en cours d installation.', '2026-03-30');

INSERT INTO standard_referentiel (categorie, nom, localisation, niveau_ou_type, horaires) VALUES
('ecole', 'Ecole Elementaire Rufisque-Est 1', 'Quartier Rufisque-Est Centre', 'Elementaire', NULL),
('ecole', 'CEM Rufisque-Est', 'Axe principal Rufisque-Est', 'College', NULL),
('ecole', 'Lycee Municipal de Rufisque-Est', 'Zone administrative', 'Lycee', NULL),
('sante', 'Centre de Sante Rufisque-Est', 'Boulevard de la Commune', 'Centre de sante', NULL),
('sante', 'Poste de Sante Keury Souf', 'Quartier Keury Souf', 'Poste de sante', NULL),
('sante', 'Poste de Sante Darou Rahmane', 'Secteur Darou Rahmane', 'Poste de sante', NULL),
('service', 'Etat civil', 'Guichet principal municipal', 'Service municipal', '08h00 - 16h00'),
('service', 'Urbanisme', 'Direction technique communale', 'Service municipal', '08h30 - 16h30'),
('service', 'Action sociale', 'Maison des services sociaux', 'Service municipal', '08h00 - 15h30'),
('service', 'Hygiene', 'Cellule environnement et salubrite', 'Service municipal', '08h00 - 16h00');

CREATE TABLE IF NOT EXISTS standard_hub_actualites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    resume VARCHAR(500) NOT NULL,
    lien VARCHAR(220) DEFAULT NULL,
    badge VARCHAR(24) NOT NULL DEFAULT 'info',
    published_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO standard_hub_actualites (titre, resume, lien, badge, published_at) VALUES
('Maintenance planifiee du portail', 'Dimanche 04h00-06h00 : sauvegarde des dossiers etat civil. Aucune action requise de votre part.', NULL, 'info', '2026-05-01'),
('Nouveau filtre export CSV', 'Le tableau des services municipaux accepte maintenant le tri multi-colonnes avant export.', 'standard.php#services', 'success', '2026-04-28'),
('Rappel renouvellement', 'La mairie prolonge la formule communale et les accès agents depuis l administration (Comptes & abonnement communal).', 'admin/abonnements.php', 'alert', '2026-04-22'),
('Cellule etat civil : creneaux etendus', 'Le mercredi, permanence supplementaire 16h30-18h00 sur rendez-vous.', 'digitalisation-etat-civil.php', 'info', '2026-04-18');

-- ----------------------------------------------------------------------
-- COMPTES MAIRIE DE DÉMONSTRATION (DÉVELOPPEMENT UNIQUEMENT)
-- ----------------------------------------------------------------------
-- ⚠️ NE PAS exécuter ces blocs en production.
-- Pour activer en local : décommenter manuellement les requêtes ci-dessous.
-- Identifiants : admin@demo.rufisque.sn et abonne@demo.rufisque.sn / DemoStandard2026!
-- INSERT IGNORE INTO abonnements (email, mot_de_passe_hash, plan, role_utilisateur, actif, compte_mairie, date_debut, date_fin) VALUES
-- ('admin@demo.rufisque.sn', '$2y$10$m8yeLjJsPEHeZghzYkzmLeIGYqDT6f8oUlR7iguBlvO/6wBlbtJgm', 'municipal_standard', 'admin', 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY)),
-- ('abonne@demo.rufisque.sn', '$2y$10$m8yeLjJsPEHeZghzYkzmLeIGYqDT6f8oUlR7iguBlvO/6wBlbtJgm', 'municipal_standard', 'admin', 1, 0, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY));
-- UPDATE abonnements SET role_utilisateur = 'admin' WHERE email = 'abonne@demo.rufisque.sn' LIMIT 1;
--
-- ✅ EN PRODUCTION : créez un vrai compte mairie via la page d'inscription
-- ou via le super-admin.

-- =========================================================================
-- ESPACE CITOYEN (habitants de la commune)
-- =========================================================================
-- Compte distinct des agents/mairie/éditeur. Sert à :
--   - faire des signalements (route cassée, lampadaire, déchets…)
--   - (plus tard) voter dans les consultations, payer des services
CREATE TABLE IF NOT EXISTS citoyens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    prenom VARCHAR(80) NOT NULL,
    nom VARCHAR(80) NOT NULL,
    telephone VARCHAR(40) DEFAULT NULL,
    quartier VARCHAR(120) DEFAULT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_citoyens_actif (actif)
);

-- ----------------------------------------------------------------------
-- COMPTE CITOYEN DE DÉMONSTRATION (DÉVELOPPEMENT UNIQUEMENT)
-- ----------------------------------------------------------------------
-- ⚠️ NE PAS exécuter ce bloc en production.
-- Pour activer en local : décommenter manuellement les 2 lignes ci-dessous.
-- Identifiants : citoyen@demo.rufisque.sn / DemoCitoyen2026!
-- INSERT IGNORE INTO citoyens (email, mot_de_passe_hash, prenom, nom, telephone, quartier, actif) VALUES
-- ('citoyen@demo.rufisque.sn', '$2y$12$PLDKalb80QlKAy9DZLkqsuQT1l1jB3pVdwmS/LTHYqSCsK/gPMBQq', 'Aminata', 'Diop', NULL, 'Keury Souf', 1);

-- =========================================================================
-- SIGNALEMENTS CITOYENS
-- =========================================================================
-- Un citoyen signale un problème (route, lampadaire, déchets, inondation, eau...)
-- avec photo et géolocalisation. L'admin traite via /admin/signalements.php.
CREATE TABLE IF NOT EXISTS signalements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citoyen_id INT NOT NULL,
    categorie ENUM('route', 'lampadaire', 'dechet', 'inondation', 'eau', 'securite', 'autre') NOT NULL DEFAULT 'autre',
    titre VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    latitude DECIMAL(10, 7) DEFAULT NULL,
    longitude DECIMAL(10, 7) DEFAULT NULL,
    adresse_libre VARCHAR(255) DEFAULT NULL,
    statut ENUM('nouveau', 'pris_en_charge', 'resolu', 'rejete') NOT NULL DEFAULT 'nouveau',
    admin_notes TEXT DEFAULT NULL,
    traite_par_email VARCHAR(190) DEFAULT NULL,
    traite_le TIMESTAMP NULL DEFAULT NULL,
    visibilite_publique TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_signalements_citoyen (citoyen_id),
    INDEX idx_signalements_statut (statut),
    INDEX idx_signalements_categorie (categorie),
    INDEX idx_signalements_date (created_at),
    CONSTRAINT fk_signalement_citoyen FOREIGN KEY (citoyen_id) REFERENCES citoyens(id) ON DELETE CASCADE
);

-- =========================================================================
-- ESPACE DOCUMENTS PUBLICS (bibliothèque PDF/DOCX)
-- =========================================================================
-- Formulaires, actes administratifs, autorisations, démarches…
-- L'admin mairie publie les documents. Les habitants les téléchargent librement.
-- Un compteur de téléchargements est tenu pour suivre l'usage réel.
-- =========================================================================
-- NOTIFICATIONS COMMUNALES (envoi massé email + SMS aux citoyens)
-- =========================================================================
-- L'admin compose un message (urgence, événement, info, coupure...) et le
-- diffuse à tout ou partie des citoyens. Chaque envoi tient un journal des
-- destinataires (canal utilisé, succès/échec) pour audit et debug.

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categorie ENUM('urgence', 'meteo', 'coupure', 'evenement', 'reunion', 'info', 'autre') NOT NULL DEFAULT 'info',
    canal ENUM('email', 'sms', 'both') NOT NULL DEFAULT 'email',
    sujet VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    cible_quartier VARCHAR(120) DEFAULT NULL,
    nb_destinataires INT NOT NULL DEFAULT 0,
    nb_envois_ok INT NOT NULL DEFAULT 0,
    nb_envois_ko INT NOT NULL DEFAULT 0,
    statut ENUM('en_attente', 'envoye', 'partiel', 'echec') NOT NULL DEFAULT 'en_attente',
    envoye_par_email VARCHAR(190) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_notif_cat (categorie),
    INDEX idx_notif_date (created_at)
);

CREATE TABLE IF NOT EXISTS notifications_envois (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    citoyen_id INT DEFAULT NULL,
    canal ENUM('email', 'sms') NOT NULL,
    destinataire VARCHAR(190) NOT NULL,
    statut ENUM('ok', 'ko') NOT NULL DEFAULT 'ok',
    erreur VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_envoi_notif (notification_id),
    INDEX idx_envoi_citoyen (citoyen_id),
    CONSTRAINT fk_envoi_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
);

-- Préférences citoyen (opt-in/out) — colonnes ajoutées idempotentement par PHP
-- pour les bases existantes via maire_ensure_citoyens_notif_columns().

-- =========================================================================
-- CONSULTATIONS & VOTES ÉLECTRONIQUES (palier Premium)
-- =========================================================================
-- La mairie crée une consultation (vote / sondage / consultation citoyenne)
-- avec N options. Les citoyens authentifiés votent (1 voix par compte) jusqu'à
-- la date de clôture. Les résultats sont publics une fois la consultation fermée.

CREATE TABLE IF NOT EXISTS consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('vote', 'sondage', 'consultation') NOT NULL DEFAULT 'sondage',
    titre VARCHAR(200) NOT NULL,
    question TEXT NOT NULL,
    description TEXT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut ENUM('brouillon', 'ouverte', 'fermee') NOT NULL DEFAULT 'brouillon',
    resultats_publics TINYINT(1) NOT NULL DEFAULT 1,
    multi_choix TINYINT(1) NOT NULL DEFAULT 0,
    nb_votes_total INT NOT NULL DEFAULT 0,
    nb_options INT NOT NULL DEFAULT 0,
    cree_par_email VARCHAR(190) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_consult_statut (statut),
    INDEX idx_consult_date (date_fin)
);

CREATE TABLE IF NOT EXISTS consultations_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    libelle VARCHAR(220) NOT NULL,
    ordre INT NOT NULL DEFAULT 0,
    nb_votes INT NOT NULL DEFAULT 0,
    INDEX idx_option_consult (consultation_id),
    CONSTRAINT fk_option_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS consultations_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    citoyen_id INT NOT NULL,
    option_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_vote (consultation_id, citoyen_id, option_id),
    INDEX idx_vote_consult (consultation_id),
    INDEX idx_vote_citoyen (citoyen_id),
    CONSTRAINT fk_vote_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    CONSTRAINT fk_vote_option FOREIGN KEY (option_id) REFERENCES consultations_options(id) ON DELETE CASCADE,
    CONSTRAINT fk_vote_citoyen FOREIGN KEY (citoyen_id) REFERENCES citoyens(id) ON DELETE CASCADE
);

-- =========================================================================
-- PHASE X — INSTANCES MULTI-COMMUNES (scaffold)
-- =========================================================================
-- Registre des communes hébergées sur la plateforme multi-tenant.
-- Permet de basculer entre plusieurs mairies via le hostname ou le path prefix
-- ('/' = commune par défaut, '/rufisque/' = commune de Rufisque, etc.).
-- La migration complète multi-tenant nécessite ensuite l'ajout d'un commune_id
-- sur chaque table métier (signalements, citoyens, paiements, etc.).

CREATE TABLE IF NOT EXISTS communes_registry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    nom VARCHAR(120) NOT NULL,
    region VARCHAR(80) NULL,
    pays VARCHAR(60) NOT NULL DEFAULT 'Sénégal',
    hostname VARCHAR(150) NULL,
    path_prefix VARCHAR(60) NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    est_principale TINYINT(1) NOT NULL DEFAULT 0,
    contact_email VARCHAR(190) NULL,
    telephone VARCHAR(40) NULL,
    site_web VARCHAR(190) NULL,
    logo_url VARCHAR(255) NULL,
    couleur_primaire VARCHAR(20) DEFAULT '#0c4a3e',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_commune_code (code),
    INDEX idx_commune_host (hostname),
    INDEX idx_commune_actif (actif)
);

-- Commune par défaut (rétro-compat avec l'instance existante).
INSERT IGNORE INTO communes_registry (code, nom, region, pays, actif, est_principale, couleur_primaire)
VALUES ('rufisque', 'Mairie de Rufisque', 'Dakar', 'Sénégal', 1, 1, '#0c4a3e');

-- =========================================================================
-- PHASE X — STREAMING DES CONSEILS MUNICIPAUX
-- =========================================================================
-- Sessions du conseil : titre, date, embed_url (YouTube/Vimeo/Twitch), ordre du
-- jour PDF facultatif, statut (annonce / en_direct / replay / archivée).

CREATE TABLE IF NOT EXISTS conseil_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    description TEXT NULL,
    date_session DATETIME NOT NULL,
    duree_minutes INT NOT NULL DEFAULT 90,
    statut ENUM('annonce', 'en_direct', 'replay', 'archive', 'annule') NOT NULL DEFAULT 'annonce',
    plateforme ENUM('youtube', 'vimeo', 'twitch', 'autre') NOT NULL DEFAULT 'youtube',
    embed_url VARCHAR(500) NULL,
    ordre_du_jour TEXT NULL,
    proces_verbal_url VARCHAR(500) NULL,
    nb_vues INT NOT NULL DEFAULT 0,
    cree_par_email VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_conseil_statut (statut),
    INDEX idx_conseil_date (date_session)
);

-- =========================================================================
-- PHASE X — API PUBLIQUE (clés d'authentification + journal d'accès)
-- =========================================================================

CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(120) NOT NULL,
    cle_hash CHAR(64) NOT NULL,
    cle_prefix CHAR(8) NOT NULL,
    scopes VARCHAR(500) NOT NULL DEFAULT 'public',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    rate_limit_per_min INT NOT NULL DEFAULT 60,
    nb_appels INT NOT NULL DEFAULT 0,
    derniere_utilisation TIMESTAMP NULL DEFAULT NULL,
    cree_par_email VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_api_hash (cle_hash),
    INDEX idx_api_prefix (cle_prefix),
    INDEX idx_api_actif (actif)
);

CREATE TABLE IF NOT EXISTS api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NULL,
    endpoint VARCHAR(120) NOT NULL,
    methode VARCHAR(10) NOT NULL DEFAULT 'GET',
    statut_http INT NOT NULL,
    ip_hash CHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    duree_ms INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_apilog_endpoint (endpoint),
    INDEX idx_apilog_date (created_at),
    INDEX idx_apilog_keyid (api_key_id)
);

-- =========================================================================
-- PHASE X — ASSISTANT IA / CHATBOT FAQ
-- =========================================================================
-- Base de connaissances structurée pour le chatbot d'assistance citoyenne.
-- Le moteur indexe les mots-clés et trouve la meilleure réponse par scoring.

CREATE TABLE IF NOT EXISTS chatbot_faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categorie VARCHAR(60) NOT NULL DEFAULT 'general',
    question VARCHAR(300) NOT NULL,
    reponse TEXT NOT NULL,
    mots_cles VARCHAR(500) NOT NULL,
    lien_action VARCHAR(255) NULL,
    libelle_action VARCHAR(80) NULL,
    priorite INT NOT NULL DEFAULT 0,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    nb_consultations INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_faq_categ (categorie),
    INDEX idx_faq_actif (actif),
    FULLTEXT KEY ft_faq_search (question, mots_cles, reponse)
);

CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citoyen_id INT NULL,
    session_token CHAR(32) NOT NULL,
    question TEXT NOT NULL,
    reponse TEXT NOT NULL,
    faq_id INT NULL,
    score DECIMAL(5, 2) NULL,
    satisfait TINYINT(1) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_session (session_token),
    INDEX idx_chat_citoyen (citoyen_id),
    INDEX idx_chat_date (created_at),
    CONSTRAINT fk_chat_citoyen FOREIGN KEY (citoyen_id) REFERENCES citoyens(id) ON DELETE SET NULL,
    CONSTRAINT fk_chat_faq FOREIGN KEY (faq_id) REFERENCES chatbot_faq(id) ON DELETE SET NULL
);

-- Données initiales FAQ (mots-clés en minuscules séparés par virgules, sans accents)
INSERT IGNORE INTO chatbot_faq (id, categorie, question, reponse, mots_cles, lien_action, libelle_action, priorite) VALUES
(1, 'demarches', 'Comment obtenir un extrait de naissance ?', 'Vous pouvez demander un extrait de naissance en ligne via notre service express (délai 48h, 3 000 FCFA). Vous recevrez le document par e-mail ou pourrez le retirer en mairie.', 'extrait,naissance,acte,etat civil,document,naitre,naissance', '/maire/payer.php?service=doc_extrait_naissance', 'Demander en ligne (3 000 FCFA)', 10),
(2, 'demarches', 'Comment obtenir un certificat de résidence ?', 'Le certificat de résidence se demande en ligne (24h, 2 500 FCFA) ou en mairie aux heures d''ouverture. Présentez une pièce d''identité et un justificatif de logement (facture, bail).', 'residence,certificat,justificatif,domicile,habitation,habiter', '/maire/payer.php?service=doc_certificat_residence', 'Demander en ligne (2 500 FCFA)', 9),
(3, 'demarches', 'Comment obtenir une copie d''acte de mariage ?', 'La copie d''acte de mariage est délivrée en 72h (4 000 FCFA en ligne). Joignez les noms des deux époux et la date du mariage.', 'mariage,marie,acte,copie,union,epoux,maries', '/maire/payer.php?service=doc_certificat_mariage', 'Demander en ligne', 8),
(4, 'paiements', 'Comment payer ma taxe d''habitation ?', 'Vous pouvez régler votre taxe d''habitation (25 000 FCFA/an) en ligne par Orange Money ou Wave. Le paiement est sécurisé et un reçu est délivré immédiatement.', 'taxe,habitation,impot,paiement,payer,annuelle,annuel', '/maire/payer.php?service=taxe_habitation', 'Payer maintenant', 10),
(5, 'paiements', 'Quels sont les moyens de paiement acceptés ?', 'Nous acceptons Orange Money, Wave et le mode démonstration (validation manuelle). Tous les paiements sont sécurisés et journalisés avec une référence unique.', 'paiement,orange money,wave,carte,mobile money,moyens,methode', '/maire/paiements.php', 'Voir les services payants', 7),
(6, 'signalement', 'Comment signaler un problème dans ma commune ?', 'Connectez-vous à votre espace habitant puis utilisez "Faire un signalement" pour reporter routes cassées, lampadaires défaillants, déchets ou inondations — avec photo et géolocalisation.', 'signaler,signalement,probleme,route,lampadaire,dechet,inondation,nid de poule,eclairage', '/maire/citoyen/signaler.php', 'Faire un signalement', 10),
(7, 'compte', 'Comment créer un compte habitant ?', 'L''inscription est gratuite : email, mot de passe, prénom et nom suffisent. Vous pourrez ensuite signaler des problèmes, payer en ligne, voter aux consultations et suivre vos demandes.', 'inscription,compte,creer,creation,enregistrer,habitant,citoyen,sinscrire,inscrire', '/maire/citoyen/inscription.php', 'Créer un compte', 9),
(8, 'compte', 'J''ai oublié mon mot de passe', 'Connectez-vous à l''espace habitant — la procédure de réinitialisation est disponible sur la page de connexion. Si le problème persiste, contactez la mairie.', 'mot de passe,oublie,reinitialiser,perdu,recuperer,connexion', '/maire/citoyen/connexion.php', 'Page de connexion', 6),
(9, 'horaires', 'Quels sont les horaires de la mairie ?', 'La mairie est ouverte du lundi au vendredi de 8h00 à 16h30 (sauf jours fériés). Permanence le samedi matin pour l''état civil de 9h à 12h.', 'horaire,ouverture,fermeture,quand,jour,heures,heure,samedi,dimanche,ferie', '/maire/contact.php', 'Voir les coordonnées', 8),
(10, 'general', 'Comment contacter la mairie ?', 'Adressez-nous un message via la page Contact, ou rendez-vous directement à la mairie aux heures d''ouverture. Pour les urgences (inondation, incendie), composez le numéro vert d''urgence.', 'contact,joindre,telephone,email,adresse,bureau', '/maire/contact.php', 'Page contact', 7),
(11, 'consultation', 'Comment participer aux consultations citoyennes ?', 'Connectez-vous à votre compte habitant et rendez-vous sur la page Consultations. Vous pouvez voter (1 voix par compte) pour toutes les consultations ouvertes.', 'consultation,vote,voter,sondage,participer,democratie,referendum,scrutin', '/maire/consultations.php', 'Voir les consultations', 8),
(12, 'documents', 'Où trouver les documents officiels (arrêtés, comptes-rendus) ?', 'Tous les documents officiels publiés par la mairie sont disponibles dans l''espace Documents. Téléchargement gratuit, classés par catégorie.', 'document,arrete,compte rendu,deliberation,officiel,reglement,texte,papier', '/maire/documents.php', 'Bibliothèque documentaire', 7);

-- =========================================================================
-- PAIEMENTS EN LIGNE — Services communaux (taxes, documents express, réservations)
-- =========================================================================
-- Transactions individuelles initiées par un citoyen ou un visiteur, payées via
-- un agrégateur mobile money (Orange Money, Wave) ou mode démo (log).
-- Statuts : initie → en_attente → paye | echec | annule | rembourse
-- Le citoyen peut être null pour permettre le paiement sans compte (visiteur).

CREATE TABLE IF NOT EXISTS paiements_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(40) NOT NULL,
    citoyen_id INT NULL,
    visiteur_nom VARCHAR(120) NULL,
    visiteur_email VARCHAR(190) NULL,
    visiteur_telephone VARCHAR(40) NULL,
    service_categorie ENUM('taxe', 'document_express', 'reservation', 'autre') NOT NULL DEFAULT 'autre',
    service_code VARCHAR(60) NOT NULL,
    service_libelle VARCHAR(200) NOT NULL,
    service_details TEXT NULL,
    montant DECIMAL(12, 2) NOT NULL,
    devise CHAR(3) NOT NULL DEFAULT 'XOF',
    provider VARCHAR(30) NOT NULL DEFAULT 'log',
    provider_reference VARCHAR(120) NULL,
    statut ENUM('initie', 'en_attente', 'paye', 'echec', 'annule', 'rembourse') NOT NULL DEFAULT 'initie',
    payload_init TEXT NULL,
    payload_callback TEXT NULL,
    paye_le TIMESTAMP NULL DEFAULT NULL,
    expire_le TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_paiement_ref (reference),
    INDEX idx_paie_statut (statut),
    INDEX idx_paie_citoyen (citoyen_id),
    INDEX idx_paie_categorie (service_categorie),
    INDEX idx_paie_provider (provider),
    CONSTRAINT fk_paie_citoyen FOREIGN KEY (citoyen_id) REFERENCES citoyens(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS documents_publics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categorie ENUM('formulaire', 'acte', 'autorisation', 'demarche', 'rapport', 'autre') NOT NULL DEFAULT 'autre',
    titre VARCHAR(180) NOT NULL,
    description TEXT NULL,
    fichier_path VARCHAR(255) NOT NULL,
    fichier_nom_original VARCHAR(255) NOT NULL,
    fichier_taille INT NOT NULL,
    mime_type VARCHAR(80) NOT NULL,
    nb_telechargements INT NOT NULL DEFAULT 0,
    publie TINYINT(1) NOT NULL DEFAULT 1,
    publie_par_email VARCHAR(190) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_documents_cat (categorie),
    INDEX idx_documents_publie (publie),
    INDEX idx_documents_date (created_at)
);
