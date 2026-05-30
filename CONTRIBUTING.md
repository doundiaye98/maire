# Guide du contributeur

Merci de votre intérêt pour ce projet ! Ce document décrit les conventions, l'outillage et le workflow attendus pour contribuer.

## Prérequis dev

- PHP 8.1+
- Composer 2.x
- Node.js 18+ & npm 9+
- MySQL 5.7+ ou MariaDB 10.3+
- Git

## Workflow Git

1. **Créer une branche depuis `main`**, en respectant la convention :
   - `feature/ma-feature` pour les nouvelles fonctionnalités
   - `fix/mon-bug` pour les corrections de bug
   - `refactor/nom-zone` pour les refactos
   - `docs/sujet` pour les changements de doc uniquement
   - `chore/sujet` pour les tâches d'outillage

2. **Faire des commits atomiques** avec des messages descriptifs en français ou anglais. Suggéré : convention [Conventional Commits](https://www.conventionalcommits.org/) :
   ```
   feat(documents): ajoute le filtre par catégorie
   fix(paiements): corrige le calcul du montant TTC
   docs(readme): met à jour la section installation
   ```

3. **Pousser & ouvrir une Pull Request** avec :
   - Un titre clair
   - Une description du **pourquoi** (et non juste du quoi)
   - Des captures d'écran si l'UI change
   - La liste des tests effectués

4. **Code review** obligatoire — la PR n'est mergeable qu'après approbation.

## Conventions de code

### PHP

- `declare(strict_types=1);` **en haut de chaque fichier**
- PSR-12 (formatage), PSR-4 (autoload)
- **Toujours typer** les paramètres et retours de fonction (`int`, `string`, `?array`, `Throwable`, etc.)
- Préfixer les fonctions globales par `maire_` (namespace simulé)
- Utiliser des **prepared statements** pour TOUTE requête SQL avec paramètre
- **Échapper la sortie HTML** avec `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')`
- Ne **jamais** committer de credentials ou de tokens
- Pour les nouveaux modules métier, créer un fichier dans `includes/{domaine}.php` avec une fonction `maire_ensure_*_table(PDO $pdo): void` si une nouvelle table est nécessaire — OU mieux : créer une migration

### Base de données

- **Toute modification de schéma passe par une migration** :
  ```bash
  php bin/migrate.php create nom_descriptif
  ```
- Toujours fournir un bloc `down` qui annule proprement le `up`
- Charset : `utf8mb4` / Collation : `utf8mb4_unicode_ci`
- Moteur : `InnoDB`

### Front

- Tailwind 3.4 — utiliser les composants partagés (`tw-card`, `tw-btn-primary`, etc.) plutôt que de réinventer
- **Pas de classes Tailwind dynamiques** en PHP non scannées (le purge cassera le rendu)
- Dark mode systématique (`dark:` partout)
- Animations avec les keyframes définis dans `src/css/input.css`
- Compiler avec `npm run build` avant de pousser si le CSS change

### Tests

- Tout nouveau module métier doit avoir au moins **un test unitaire** dans `tests/Unit/`
- Les fonctions financières ou de sécurité doivent avoir une **couverture > 80%**
- Lancer `composer test` avant chaque PR

## Sécurité

Avant chaque PR, vérifier :

- [ ] Pas de SQL injection (prepared statements obligatoires)
- [ ] Pas de XSS (échappement HTML systématique)
- [ ] CSRF token sur tous les formulaires POST sensibles
- [ ] Path traversal : valider chaque chemin de fichier avec `realpath()`
- [ ] Validation des MIME types pour les uploads
- [ ] Rate limiting sur les points sensibles (login, contact, signaler)

Si vous découvrez une faille, **ne créez pas de ticket public** : contactez directement l'équipe par e-mail à `Rufisquest02@gmail.com` avec le préfixe `[SECURITY]`.

## Performance

- OPcache doit rester activé
- Préférer une requête SQL bien indexée à une boucle PHP
- Ne pas charger l'intégralité d'une table — paginer
- Utiliser `LIMIT` partout
- Les compteurs lourds doivent être cachés (table de stats agrégées)

## Documentation

- Documenter chaque nouveau module dans son en-tête de fichier (commentaire docblock)
- Mettre à jour le `README.md` si vous ajoutez une commande CLI, une variable d'env, ou une dépendance
- Ajouter une entrée dans le `CHANGELOG.md` (à créer) si la modif est utilisateur-visible

## Outils utiles

```bash
# Lint PHP
php -l fichier.php

# Vérifier la conformité PSR-12 (optionnel, à installer)
composer require --dev squizlabs/php_codesniffer
vendor/bin/phpcs --standard=PSR12 includes/

# Tests
composer test

# Migrations
php bin/migrate.php status
php bin/migrate.php up

# Build front
npm run dev    # watch
npm run build  # prod
```

Merci pour votre contribution !
