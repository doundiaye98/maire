# Mairie de Rufisque-Est — Plateforme SaaS communale

> Site officiel et plateforme administrative numérique de la commune de **Rufisque-Est**, Sénégal.
> Architecture multi-tenant pensée pour servir d'autres communes du pays.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1)](https://www.mysql.com/)
[![Tailwind](https://img.shields.io/badge/Tailwind-3.4-38BDF8)](https://tailwindcss.com/)
[![PWA](https://img.shields.io/badge/PWA-ready-5A0FC8)]()
[![Licence](https://img.shields.io/badge/Licence-Propri%C3%A9taire-red)]()

---

## Sommaire

1. [À propos](#à-propos)
2. [Fonctionnalités](#fonctionnalités)
3. [Architecture](#architecture)
4. [Prérequis](#prérequis)
5. [Installation locale](#installation-locale)
6. [Comptes de démonstration](#comptes-de-démonstration)
7. [Variables d'environnement](#variables-denvironnement)
8. [Migrations de base de données](#migrations-de-base-de-données)
9. [Build Tailwind](#build-tailwind)
10. [Tests automatisés](#tests-automatisés)
11. [Logs applicatifs](#logs-applicatifs)
12. [Déploiement en production](#déploiement-en-production)
13. [Structure du projet](#structure-du-projet)
14. [Contribuer](#contribuer)
15. [Licence](#licence)

---

## À propos

Plateforme **SaaS multi-tenant** offrant à une commune sénégalaise :

- Un **site institutionnel public** (présentation, services, actualités, projets, conseil municipal en direct…)
- Un **espace citoyen** (signalements géolocalisés, consultations citoyennes, suivi état civil)
- Une **administration interne** complète pour les agents et l'exécutif municipal
- Une **passerelle de paiement** (Wave, Orange Money, Mobile Money)
- Un **back-office éditeur** (super-admin) pour gérer les abonnements de plusieurs communes
- Une **API REST** (v1) pour brancher une application mobile ou des intégrations tierces
- Une **PWA installable** avec mode hors-ligne

## Fonctionnalités

| Domaine | Fonctionnalités |
|---|---|
| **Citoyen** | Inscription, profil, signalements avec photos+GPS, consultation des projets, votes citoyens, demande de documents, suivi état civil |
| **Communication** | Actualités, ticker temps réel, vie culturelle, projets municipaux, conseil municipal en direct (embed YouTube/Vimeo) + replay |
| **Démarches** | État civil, urbanisme, santé, éducation, hygiène, action sociale, paiements en ligne |
| **Documents** | Bibliothèque téléchargeable par catégorie (formulaires, actes, autorisations, guides, rapports) avec upload sécurisé |
| **Administration** | Gestion des comptes agents/admin, signalements, paiements, consultations, FAQ chatbot, API keys, journal d'audit |
| **Super-admin (éditeur)** | Gestion multi-communes : abonnements, plans tarifés, suspensions, historique, comptes de support |
| **Sécurité** | CSRF, password hashing, prepared statements, rate limiting, path traversal protection, .htaccess restrictifs sur uploads |
| **DX** | Logger structuré JSON, migrations versionnées, tests PHPUnit, build Tailwind, PWA service worker |

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│  PUBLIC (citoyens)                                            │
│  index.php, services.php, signaler.php, citoyen/*…           │
├──────────────────────────────────────────────────────────────┤
│  ADMIN MAIRIE (agents, admin local)                          │
│  abonnement.php → admin/*                                     │
├──────────────────────────────────────────────────────────────┤
│  SUPER-ADMIN (éditeur de la plateforme)                       │
│  super-admin/*                                                │
├──────────────────────────────────────────────────────────────┤
│  API REST v1                                                  │
│  api/v1/{actualites,consultations,documents,paiements,…}     │
├──────────────────────────────────────────────────────────────┤
│  COUCHE MÉTIER (includes/)                                    │
│  ~200 fonctions par domaine — pas de framework, PHP natif    │
├──────────────────────────────────────────────────────────────┤
│  MySQL — 25+ tables (auto-créées si manquantes)              │
└──────────────────────────────────────────────────────────────┘
```

- **Stack** : PHP 8.1+ (strict types partout), MySQL 5.7+ / MariaDB 10.3+, Apache (mod_rewrite optionnel)
- **Front** : Tailwind 3.4 (build local PostCSS), CSS legacy `style.css` (en cours de migration), Vanilla JS
- **PWA** : Service Worker avec stratégies network-first (HTML) et cache-first (assets)

## Prérequis

| Outil | Version minimum | Pourquoi |
|---|---|---|
| **PHP** | 8.1 | `match`, types stricts, `readonly` (futur) |
| **MySQL/MariaDB** | 5.7 / 10.3 | InnoDB, `JSON`, `utf8mb4_unicode_ci` |
| **Apache** | 2.4 | `.htaccess` (mod_rewrite, mod_headers) |
| **Composer** | 2.x | Autoload PSR-4 + PHPUnit |
| **Node.js** | 18+ | Build Tailwind |
| **npm** | 9+ | Idem |

Extensions PHP requises : `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `curl`, `openssl`, `gd` (uploads d'images).

## Installation locale

### 1. Cloner le dépôt et placer dans le webroot

```bash
# Sur Windows / WAMP
cd c:\wamp64\www
git clone <url-du-repo> maire
cd maire
```

### 2. Créer la base de données

```bash
mysql -u root -p < database/schema.sql
```

(ou via phpMyAdmin : importer `database/schema.sql`)

### 3. Configurer les secrets

```bash
cp config/secrets.sample.php config/secrets.php
# puis éditer config/secrets.php avec vos credentials BDD réels
```

### 4. Installer les dépendances PHP

```bash
composer install
```

### 5. Installer & compiler les assets front

```bash
npm install
npm run build       # build minifié pour la prod
# ou
npm run dev         # mode watch pour le développement
```

Si vous oubliez cette étape, le site retombe automatiquement sur le CDN Tailwind (utilisable en dev, mais affiche un warning en console).

### 6. Lancer les migrations

```bash
php bin/migrate.php up
```

### 7. Accéder au site

Ouvrez [http://localhost/maire/](http://localhost/maire/) dans votre navigateur.

## Comptes de démonstration

> ⚠ **À supprimer en production.** Ces comptes sont créés automatiquement par `database/schema.sql`.

| Rôle | Email | Mot de passe | Accès |
|---|---|---|---|
| **Super-admin (éditeur)** | `editeur@demo.rufisque.sn` | `DemoEditeur2026!` | `super-admin/login.php` |
| **Admin mairie** | _à créer via super-admin_ | — | `abonnement.php` |
| **Agent mairie** | _à créer via admin mairie_ | — | `abonnement.php` |
| **Citoyen** | _à créer via inscription_ | — | `citoyen/inscription.php` |

Pour supprimer les comptes démo avant production :

```sql
DELETE FROM super_admins WHERE email = 'editeur@demo.rufisque.sn';
```

## Variables d'environnement

Toutes les variables sont **optionnelles** — des valeurs par défaut sécurisées sont prévues. Si vous voulez les surcharger :

| Variable | Défaut | Description |
|---|---|---|
| `APP_ENV` | `production` | `development` / `staging` / `production` / `testing` |
| `MAIRE_LOG_LEVEL` | `info` (prod) / `debug` (dev) | `debug`/`info`/`notice`/`warning`/`error`/`critical` |
| `DB_HOST` | `localhost` | Hôte MySQL |
| `DB_NAME` | `mairie_senegal` | Base de données |
| `DB_USER` | `root` | Utilisateur MySQL |
| `DB_PASS` | _(vide)_ | Mot de passe MySQL |
| `WAVE_API_KEY` | — | Clé API Wave (paiements) |
| `ORANGE_MONEY_API_KEY` | — | Clé API Orange Money |

Voir `.env.example` pour la liste complète, et `config/secrets.sample.php` pour la configuration BDD.

## Migrations de base de données

Le projet utilise un **système maison de migrations versionnées** (pur PHP, zéro dépendance).

### Commandes

```bash
php bin/migrate.php status                # voir l'état
php bin/migrate.php up                    # appliquer toutes les migrations en attente
php bin/migrate.php up --step=1           # appliquer la suivante seulement
php bin/migrate.php down                  # rollback de la dernière migration
php bin/migrate.php create add_new_table  # créer un nouveau fichier de migration
```

### Convention

Chaque migration vit dans `migrations/{YYYY_MM_DD_HHMMSS}_{nom}.php` et retourne un array :

```php
return [
    'description' => 'Ajoute la colonne X à la table Y',
    'up'   => fn (PDO $pdo) => $pdo->exec('ALTER TABLE …'),
    'down' => fn (PDO $pdo) => $pdo->exec('ALTER TABLE …'),
];
```

L'état est tracké dans la table `schema_migrations`.

## Build Tailwind

```bash
npm install            # installe Tailwind + plugins
npm run dev            # watch (rebuild auto en dev)
npm run build          # build minifié pour la prod
```

Le CSS compilé est généré dans `assets/css/tailwind.css`. Le projet `header.php` utilise **prioritairement** ce fichier ; si absent (build pas encore lancé), il **retombe automatiquement sur le CDN** Tailwind.

Pour personnaliser le thème (couleurs, fonts, animations), éditez `tailwind.config.js`.

## Tests automatisés

```bash
composer install         # installe PHPUnit (dev only)
composer test            # lance tous les tests (--testdox)
composer test:unit       # tests unitaires seulement
vendor/bin/phpunit       # ou directement
```

Suites disponibles :

- `tests/Unit/` — tests unitaires des fonctions métier (logger, rate-limit, migrations, …)
- `tests/Integration/` — tests d'intégration (placeholder pour la suite)

## Logs applicatifs

- Dossier : `logs/` (créé automatiquement, protégé par `.htaccess`)
- Format : **JSON Lines** (1 entrée = 1 ligne JSON)
- Rotation : quotidienne (`app-YYYY-MM-DD.log`)
- Rétention : 30 jours (configurable via `MAIRE_LOG_RETENTION_DAYS`)
- Tracking : chaque ligne contient un `correlation_id` (cid) stable durant la requête

Utilisation dans le code :

```php
maire_log_info('Document {id} créé', ['id' => $docId, 'email' => $email]);
maire_log_warning('Tentative login invalide', ['email' => $email]);
maire_log_exception($e, 'Erreur lors du paiement');
```

Purger les vieux logs (à ajouter en cron) :

```bash
php -r "require 'includes/logger.php'; echo maire_log_purge_old() . ' fichiers purgés';"
```

## Déploiement en production

### Checklist pré-prod

- [ ] Lancer `npm run build` (Tailwind compilé)
- [ ] Lancer `composer install --no-dev --optimize-autoloader`
- [ ] Lancer `php bin/migrate.php up`
- [ ] Supprimer les comptes démo (`editeur@demo.rufisque.sn`)
- [ ] Mettre à jour `config/secrets.php` avec les **vrais** credentials
- [ ] Configurer `APP_ENV=production`
- [ ] Vérifier que `logs/`, `uploads/` sont **writables** par l'utilisateur web (mais non-listables)
- [ ] Activer HTTPS (Let's Encrypt recommandé)
- [ ] Configurer un cron quotidien : `php /chemin/bin/migrate.php status` (alerte si pending) + purge des logs
- [ ] Vérifier le service worker (`service-worker.js`) avec la bonne version de cache
- [ ] Tester le fallback `offline.html`
- [ ] Charger les vraies données : photos, numéros de téléphone, témoignages réels

### Recommandations serveur

- PHP-FPM + Nginx ou Apache + mod_php
- OPcache activé en prod
- `expose_php = Off`
- `max_upload_filesize = 10M` (cohérent avec `MAIRE_DOCUMENTS_MAX_OCTETS`)
- Headers HTTP de sécurité : CSP, X-Frame-Options, X-Content-Type-Options, Strict-Transport-Security

## Structure du projet

```
.
├── admin/                       # Console admin mairie
├── api/v1/                      # API REST publique
├── assets/                      # CSS legacy, JS, images
│   ├── css/style.css           # CSS legacy (en migration)
│   └── css/tailwind.css        # CSS compilé (généré par npm run build)
├── bin/migrate.php              # CLI migrations
├── citoyen/                     # Espace citoyen connecté
├── config/                      # Connexion BDD + secrets
├── database/schema.sql          # Schéma initial (point de départ)
├── includes/                    # Modules métier (1 fichier = 1 domaine)
│   ├── logger.php              # ★ Logger applicatif
│   ├── migrations.php          # ★ Système de migrations
│   └── …                       # 30+ modules métier
├── migrations/                  # Migrations versionnées
├── src/css/input.css            # Source Tailwind (compilé vers assets/css/tailwind.css)
├── super-admin/                 # Console éditeur de la plateforme
├── tests/                       # Tests PHPUnit
├── uploads/                     # Fichiers uploadés (protégé .htaccess)
├── logs/                        # Logs applicatifs (créé auto)
├── composer.json
├── package.json
├── phpunit.xml
├── tailwind.config.js
└── README.md
```

## Contribuer

Voir [`CONTRIBUTING.md`](CONTRIBUTING.md) pour les conventions de code, workflow Git, et processus de revue.

En résumé :

1. Créer une branche depuis `main` (ex : `feature/ma-feature`, `fix/mon-bug`)
2. Coder en respectant les conventions (PSR-12, `declare(strict_types=1)`, types partout)
3. Ajouter ou mettre à jour les tests
4. Lancer `composer test` + `npm run build` localement avant de pousser
5. Ouvrir une Pull Request avec une description claire

## Licence

Propriétaire — © Mairie de Rufisque-Est & éditeur de la plateforme. Tous droits réservés.

Pour toute demande de licence commerciale ou de déploiement dans une autre commune, contactez l'éditeur via [contact](contact.php).

---

**Maintenu par** : équipe technique de la Mairie de Rufisque-Est
**Contact mairie** : Rufisquest02@gmail.com
**Localisation** : Castor, face pharmacie DIOR — Arafat II, Rufisque-Est, Sénégal
