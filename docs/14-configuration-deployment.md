# 14. Configuration et déploiement

## Variables d'environnement

### Fichiers

| Fichier | Rôle |
|---------|------|
| `.env` | Variables locales (non versionné) |
| `.env.example` | Template des variables |

### Variables principales

| Variable | Valeur par défaut | Description |
|----------|-------------------|-------------|
| `APP_NAME` | `Rapprochement STEG Département Informatique` | Nom application |
| `APP_ENV` | `local` | Environnement |
| `APP_DEBUG` | `true` | Mode debug |
| `APP_URL` | `http://localhost` | URL publique |
| `APP_KEY` | (vide) | Clé chiffrement |
| `APP_LOCALE` | `en` | Langue |
| `APP_FALLBACK_LOCALE` | `en` | Langue fallback |
| `APP_TIMEZONE` | `UTC` | Fuseau horaire |
| `DB_CONNECTION` | `mysql` | Driver BDD |
| `DB_HOST` | `127.0.0.1` | Hôte BDD |
| `DB_PORT` | `3306` | Port BDD |
| `DB_DATABASE` | `reconciliation_app` | Nom BDD |
| `DB_USERNAME` | (vide) | Utilisateur BDD |
| `DB_PASSWORD` | (vide) | Mot de passe BDD |
| `SESSION_DRIVER` | `database` | Driver session |
| `SESSION_LIFETIME` | `120` | Durée session (min) |
| `QUEUE_CONNECTION` | `database` | Driver queue |
| `CACHE_STORE` | `database` | Driver cache |
| `MAIL_MAILER` | `log` | Driver mail |
| `MAIL_HOST` | `127.0.0.1` | Hôte SMTP |
| `MAIL_PORT` | `2525` | Port SMTP |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | Expéditeur |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Nom expéditeur |
| `BCRYPT_ROUNDS` | `12` | Rounds bcrypt |
| `LOG_CHANNEL` | `stack` | Canal log |
| `LOG_LEVEL` | `debug` | Niveau log |

### Variables de test (phpunit.xml)

| Variable | Valeur |
|----------|--------|
| `APP_ENV` | `testing` |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `:memory:` |
| `CACHE_STORE` | `array` |
| `QUEUE_CONNECTION` | `sync` |
| `SESSION_DRIVER` | `array` |
| `MAIL_MAILER` | `array` |
| `BCRYPT_ROUNDS` | `4` |

## Configuration Laravel

### Fichiers de configuration

| Fichier | Description |
|---------|-------------|
| `config/app.php` | Application (name, env, debug, timezone, locale) |
| `config/auth.php` | Authentification (guards, providers, passwords) |
| `config/database.php` | Base de données (connections, migrations) |
| `config/queue.php` | Files d'attente (connections, batching, failed) |
| `config/cache.php` | Cache (stores, prefix) |
| `config/session.php` | Sessions (driver, lifetime, encrypt) |
| `config/filesystems.php` | Systèmes de fichiers (disks) |
| `config/mail.php` | Mail (mailers, from) |
| `config/logging.php` | Logging (channels, level) |
| `config/matching.php` | Matching (chunk_size: 1000) |
| `config/imports.php` | Imports (chunk_size: 500) |
| `config/excel.php` | Maatwebsite/Excel (chunk, csv, cache) |
| `config/permission.php` | Spatie Permission (teams, cache) |

### Configuration personnalisée

**Fichier :** `config/matching.php`

```php
return [
    'chunk_size' => env('MATCHING_CHUNK_SIZE', 1000),
];
```

**Fichier :** `config/imports.php`

```php
return [
    'chunk_size' => env('IMPORT_CHUNK_SIZE', 500),
];
```

## Build et exécution locale

### Prérequis

| Outil | Version | Usage |
|-------|---------|-------|
| PHP | >= 8.2 | Backend |
| Composer | 2.x | Dépendances PHP |
| Node.js | >= 18 | Build frontend |
| NPM | >= 9 | Dépendances JS |
| MySQL | 8.x | Base de données |

### Extensions PHP requises

- `mbstring`
- `openssl`
- `pdo_mysql`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`
- `zip`

### Installation

```bash
# Cloner le repository
git clone <repo-url> reconciliation-app
cd reconciliation-app

# Installer dépendances PHP
composer install

# Copier et configurer l'environnement
cp .env.example .env
php artisan key:generate

# Configurer la base de données dans .env
# DB_DATABASE=reconciliation_app
# DB_USERNAME=root
# DB_PASSWORD=

# Lancer migrations et seeders
php artisan migrate --seed

# Installer dépendances JS
npm install

# Build assets
npm run build
# OU pour le développement
npm run dev
```

### Commandes Composer définies

| Commande | Action |
|----------|--------|
| `composer setup` | Install, key:generate, migrate, npm install, npm run build |
| `composer dev` | Lance artisan serve, queue:listen, pail, npm run dev (concurrently) |
| `composer test` | config:clear + artisan test |

### Lancement du serveur

```bash
# Serveur de développement
php artisan serve

# Worker de queue (obligatoire pour les jobs)
php artisan queue:work

# Ou pour développement (écoute continue)
php artisan queue:listen

# Logs en temps réel
php artisan pail
```

### Seeders disponibles

| Seeder | Rôle |
|--------|------|
| `DatabaseSeeder` | Point d'entrée |
| `RolePermissionSeeder` | Rôles et permissions |
| `SourceSeeder` | 4 sources (ALPHA, BNA, WEB/STEG, SMT) |
| `SourceColumnMappingSeeder` | Mappings colonnes par source |
| `MatchingRuleSeeder` | 6 règles de matching |
| `UserSeeder` | Utilisateurs de démo |
| `BankSeeder` | Banques de référence |
| `CurrencySeeder` | Devises (TND, EUR, USD) |
| `SettingsSeeder` | Paramètres par défaut |

### Comptes de démonstration

D'après le README :

| Email | Rôle | Mot de passe |
|-------|------|--------------|
| `admin@reconciliation.local` | super-admin | `password` |

> **Note :** Vérifier dans `database/seeders/UserSeeder.php` pour les comptes exacts.

## Docker et infrastructure

### État

| Élément | Présent |
|---------|---------|
| `Dockerfile` | ❌ Non |
| `docker-compose.yml` | ❌ Non |
| `.docker/` | ❌ Non |
| `laravel/sail` | ✅ Dans composer.json mais non configuré |

> **Note :** Le projet n'a pas de configuration Docker. L'environnement de développement utilise Laragon (Windows).

## CI/CD

### État

| Élément | Présent |
|---------|---------|
| `.github/workflows/` | ❌ Non |
| `.gitlab-ci.yml` | ❌ Non |
| `Jenkinsfile` | ❌ Non |
| `azure-pipelines.yml` | ❌ Non |

> **Note :** Aucun pipeline CI/CD n'est configuré.

## Déploiement en production

### Recommandations (d'après README)

```bash
# Optimisation
php artisan optimize

# Cache config/routes/views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations
php artisan migrate --force

# Worker queue (production)
php artisan queue:work --memory=1024 --tries=3 --timeout=0

# Variables d'environnement production
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
```

### Points de vigilance production

| Élément | Recommandation |
|---------|----------------|
| Queue worker | Lancer avec supervisor/systemd |
| Mémoire | `--memory=1024` (pools de transactions potentiellement larges) |
| HTTPS | Activer `SESSION_SECURE_COOKIE=true` |
| Mail | Changer `MAIL_MAILER` vers smtp/SES/etc. |
| Logs | Configurer canal externe si nécessaire |
| Backup | Sauvegarder `storage/app/` (fichiers importés) |

## Stockage de fichiers

| Disk | Chemin | Usage |
|------|--------|-------|
| `local` | `storage/app/private` | Fichiers importés, pièces jointes |
| `public` | `storage/app/public` | Fichiers publics |
| `s3` | AWS (non configuré) | Stockage cloud |

### Liens symboliques

```bash
php artisan storage:link
# Crée public/storage → storage/app/public
```

## Monitoring et observabilité

### Logs

| Canal | Destination |
|-------|-------------|
| `stack` | Multiple canaux |
| `single` | `storage/logs/laravel.log` |
| `daily` | `storage/logs/laravel-YYYY-MM-DD.log` |

### Audit

Le journal d'audit en base (`audit_logs`) permet de tracer :
- Créations/modifications/suppressions de modèles
- Connexions/déconnexions
- Échecs de connexion

### Observabilité externe

| Service | Présent |
|---------|---------|
| Laravel Telescope | ❌ Non |
| Laravel Pulse | ❌ Non (`PULSE_ENABLED=false`) |
| Nightwatch | ❌ Non (`NIGHTWATCH_ENABLED=false`) |
| Sentry | ❌ Non |
| New Relic | ❌ Non |
| Datadog | ❌ Non |
