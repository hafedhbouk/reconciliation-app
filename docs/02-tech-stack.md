# 2. Stack technique

## Langage et framework principal

| Technologie | Version | Rôle | Fichier de référence |
|-------------|---------|------|---------------------|
| **PHP** | ^8.2 | Langage backend | `composer.json` |
| **Laravel** | ^12.0 | Framework backend MVC | `composer.json`, `bootstrap/app.php` |

## Dépendances backend (production)

| Package | Version | Rôle | Fichier |
|---------|---------|------|---------|
| `laravel/framework` | ^12.0 | Framework principal | `composer.json` |
| `laravel/tinker` | ^2.10.1 | REPL interactif | `composer.json` |
| `spatie/laravel-permission` | ^8.3 | Gestion des rôles et permissions (RBAC) | `config/permission.php` |
| `yajra/laravel-datatables-oracle` | ^12.7 | Serveur DataTables AJAX | `composer.json` |
| `maatwebsite/excel` | ^3.1 | Import/Export Excel (XLSX/CSV) | `config/excel.php` |
| `dompdf/dompdf` | ^3.1 | Génération PDF | `composer.json` |

## Dépendances backend (développement)

| Package | Version | Rôle | Fichier |
|---------|---------|------|---------|
| `fakerphp/faker` | ^1.23 | Données de test | `composer.json` |
| `laravel/breeze` | ^2.4 | Scaffold authentification | `composer.json` |
| `laravel/pint` | ^1.24 | Style code (PHP-CS-Fixer) | `composer.json` |
| `laravel/sail` | ^1.41 | Docker dev (non configuré ici) | `composer.json` |
| `laravel/pail` | ^1.2.2 | Tail logs en temps réel | `composer.json` |
| `pestphp/pest` | ^3.8 | Framework de test | `phpunit.xml` |
| `pestphp/pest-plugin-laravel` | ^3.2 | Intégration Pest/Laravel | `composer.json` |
| `phpunit/phpunit` | ^11.5.50 | Moteur de test | `composer.json` |
| `mockery/mockery` | ^1.6 | Mocking | `composer.json` |
| `nunomaduro/collision` | ^8.6 | Error reporting CLI | `composer.json` |

## Frontend

| Technologie | Version | Rôle | Fichier de référence |
|-------------|---------|------|---------------------|
| **Bootstrap** | ^5.3.8 | Framework CSS/JS | `package.json`, `resources/css/app.scss` |
| **Bootstrap Icons** | ^1.13.1 | Icônes | `package.json`, `resources/css/app.scss` |
| **jQuery** | ^4.0.0 | DOM, AJAX, plugins | `package.json`, `resources/js/app.js` |
| **DataTables** | ^2.3.8 | Tableaux serveur | `package.json`, `resources/js/app.js` |
| **Chart.js** | ^4.5.1 | Graphiques dashboard | `package.json`, `dashboard.blade.php` |
| **Sass** | ^1.99.0 | Préprocesseur CSS | `package.json` |
| **Vite** | ^7.0.7 | Bundler frontend | `vite.config.js`, `package.json` |
| **Axios** | ^1.11.0 | Client HTTP | `package.json`, `resources/js/bootstrap.js` |
| **@popperjs/core** | ^2.11.8 | Positionnement tooltips | `package.json` |

## Base de données

| Technologie | Rôle | Configuration |
|-------------|------|---------------|
| **MySQL 8** | Base principale (production) | `config/database.php`, `.env` (DB_CONNECTION=mysql) |
| **SQLite** | Base de test en mémoire | `phpunit.xml` (DB_DATABASE=:memory:) |

## Files d'attente et cache

| Service | Driver | Configuration |
|---------|--------|---------------|
| **Queues** | `database` | `config/queue.php` — table `jobs` |
| **Cache** | `database` | `config/cache.php` — table `cache` |
| **Sessions** | `database` | `config/session.php` — table `sessions` |

## Stockage de fichiers

| Disk | Driver | Usage | Configuration |
|------|--------|-------|---------------|
| `local` | local | Fichiers privés (imports, pièces jointes) | `config/filesystems.php` |
| `public` | local | Fichiers publics | `config/filesystems.php` |
| `s3` | AWS S3 | Non configuré par défaut | `config/filesystems.php` |

## Authentification et sécurité

| Composant | Technologie | Fichier |
|-----------|-------------|---------|
| Authentification | Laravel Breeze (session) | `routes/auth.php`, `app/Http/Controllers/Auth/` |
| RBAC | Spatie Permission | `config/permission.php`, `app/Policies/` |
| Hash mot de passe | Bcrypt (12 rounds) | `config/hashing.php` |
| CSRF | Laravel intégré | Middleware `VerifyCsrfToken` |
| Headers sécurité | Custom `SecurityHeaders` | `app/Http/Middleware/SecurityHeaders.php` |

## Logging

| Canal | Driver | Configuration |
|-------|--------|---------------|
| Défaut | `stack` (single) | `config/logging.php` |

## Outils de build

| Outil | Rôle | Commande |
|-------|------|----------|
| **Composer** | Dépendances PHP | `composer install` |
| **NPM** | Dépendances JS/CSS | `npm install` |
| **Vite** | Compilation assets | `npm run dev` / `npm run build` |
| **Artisan** | CLI Laravel | `php artisan ...` |

## Environnement d'exécution

| Élément | Valeur | Source |
|---------|--------|--------|
| APP_ENV | `local` | `.env` |
| APP_DEBUG | `true` | `.env` |
| APP_LOCALE | `en` | `.env` |
| APP_TIMEZONE | `UTC` | `config/app.php` |
| SESSION_LIFETIME | `120` minutes | `.env` |
| BCRYPT_ROUNDS | `12` | `.env` |

## Absence des éléments suivants

Les éléments suivants **ne sont PAS présents** dans le projet :
- ❌ Docker / docker-compose
- ❌ CI/CD (GitHub Actions, GitLab CI, etc.)
- ❌ Redis (configuré mais non utilisé par défaut)
- ❌ API REST publique (pas de `routes/api.php`)
- ❌ SPA (React/Vue/Alpine)
- ❌ Service externe (email configuré mais `log` par défaut)
- ❌ CDN / Cloud storage actif
