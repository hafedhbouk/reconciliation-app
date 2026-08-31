# 18. Guide d'onboarding développeur

## Parcours recommandé pour comprendre l'application

### Étape 1 — Comprendre le contexte métier (30 min)

1. **Lire la documentation** :
   - [`docs/01-overview.md`](01-overview.md) — But de l'application
   - [`docs/11-workflows.md`](11-workflows.md) — Workflows métier principaux

2. **Comprendre le domaine** :
   - Qu'est-ce que le rapprochement bancaire ?
   - Les 4 sources : ALPHA, BNA, WEB/STEG, SMT
   - Les 6 règles de matching

---

### Étape 2 — Comprendre l'architecture technique (30 min)

1. **Lire** :
   - [`docs/02-tech-stack.md`](02-tech-stack.md) — Technologies utilisées
   - [`docs/04-architecture.md`](04-architecture.md) — Architecture globale

2. **Examiner les fichiers clés** :
   - `composer.json` — Dépendances PHP
   - `package.json` — Dépendances JS
   - `bootstrap/app.php` — Configuration Laravel 12
   - `routes/web.php` — Routes principales

---

### Étape 3 — Comprendre le modèle de données (45 min)

1. **Lire** :
   - [`docs/09-database.md`](09-database.md) — Schéma de la base de données

2. **Examiner les fichiers clés** :
   - `database/migrations/` — Migrations (dans l'ordre)
   - `app/Models/` — Modèles Eloquent
   - `database/seeders/SourceSeeder.php` — Sources de données
   - `database/seeders/MatchingRuleSeeder.php` — Règles de matching

---

### Étape 4 — Suivre le workflow d'import (1h)

C'est le flux le plus important de l'application. Suivez-le de bout en bout :

1. **Frontend** : `resources/views/admin/imports/create.blade.php`
2. **Contrôleur** : `app/Http/Controllers/Admin/ImportController.php::store()`
3. **Validation** : `app/Http/Requests/StoreImportRequest.php`
4. **Job** : `app/Jobs/ProcessImportJob.php::handle()`
5. **Service transformation** : `app/Services/Import/MappingEngine.php`
6. **Service normalisation** : `app/Services/Import/TransactionNormalizer.php`
7. **Transforms** : `app/Services/Import/Transforms/*.php`
8. **Notification** : `app/Notifications/ImportProcessedNotification.php`

**Exercice** : Créer un petit fichier CSV et suivre son parcours pas à pas.

---

### Étape 5 — Suivre le workflow de matching (1h)

1. **Contrôleur** : `app/Http/Controllers/Admin/MatchingRuleController.php::run()`
2. **Job** : `app/Jobs/RunMatchingRuleJob.php::handle()`
3. **Service** : `app/Services/Matching/RuleMatcher.php::match()`
4. **Score** : `app/Services/Matching/ConfidenceScorer.php`
5. **Règles** : `database/seeders/MatchingRuleSeeder.php`

**Exercice** : Lancer une règle de matching et observer les résultats en base.

---

### Étape 6 — Comprendre l'authentification et les rôles (30 min)

1. **Lire** :
   - [`docs/10-authentication-roles.md`](10-authentication-roles.md) — Auth et RBAC

2. **Examiner les fichiers clés** :
   - `routes/auth.php` — Routes auth
   - `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — Login/Logout
   - `database/seeders/RolePermissionSeeder.php` — Rôles et permissions
   - `app/Policies/` — Policies d'autorisation
   - `app/Http/Middleware/SecurityHeaders.php` — Headers sécurité

---

### Étape 7 — Comprendre le frontend (30 min)

1. **Lire** :
   - [`docs/05-frontend.md`](05-frontend.md) — Documentation frontend
   - [`docs/12-design-system.md`](12-design-system.md) — Design et UI

2. **Examiner les fichiers clés** :
   - `resources/views/layouts/app.blade.php` — Layout principal
   - `resources/views/components/admin/sidebar.blade.php` — Navigation
   - `resources/views/dashboard.blade.php` — Dashboard
   - `resources/css/app.scss` — Styles
   - `resources/js/app.js` — JavaScript

---

### Étape 8 — Comprendre les tests (30 min)

1. **Lire** :
   - [`docs/15-testing.md`](15-testing.md) — Tests

2. **Examiner les fichiers clés** :
   - `tests/Pest.php` — Configuration + helpers
   - `tests/Feature/Admin/ImportUploadTest.php` — Test import
   - `tests/Unit/Matching/RuleMatcherTest.php` — Test matching
   - `tests/Feature/RealSourceFileFormatsTest.php` — Test fichiers réels

3. **Lancer les tests** :
   ```bash
   php artisan test
   ```

---

### Étape 9 — Mettre en place l'environnement local (30 min)

1. **Suivre** :
   - [`docs/14-configuration-deployment.md`](14-configuration-deployment.md) — Configuration

2. **Commandes** :
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   npm install
   npm run dev
   php artisan serve
   ```

3. **Comptes de test** :
   - `admin@reconciliation.local` / `password`

---

### Étape 10 — Explorer les domaines optionnels

Une fois les bases maîtrisées, explorer selon les besoins :

| Si vous travaillez sur... | Consultez |
|---------------------------|-----------|
| Exports | `app/Exports/GenericTableExport.php`, `app/Jobs/GenerateMatchingExportJob.php` |
| Exceptions | `app/Http/Controllers/Admin/ExceptionController.php` |
| Audit | `app/Observers/AuditObserver.php`, `app/Services/AuditLogService.php` |
| Notifications | `app/Notifications/`, `routes/listeners.php` |
| Reconciliation manuelle | `resources/views/admin/reconciliation/index.blade.php` |

---

## Fichiers à comprendre en priorité (Top 20)

| # | Fichier | Raison |
|---|---------|--------|
| 1 | `routes/web.php` | Toutes les routes de l'application |
| 2 | `app/Jobs/ProcessImportJob.php` | Le job le plus important |
| 3 | `app/Services/Import/MappingEngine.php` | Transformation des données |
| 4 | `app/Services/Import/TransactionNormalizer.php` | Normalisation des transactions |
| 5 | `app/Services/Matching/RuleMatcher.php` | Le moteur de rapprochement |
| 6 | `database/seeders/MatchingRuleSeeder.php` | Les 6 règles de matching |
| 7 | `database/seeders/SourceColumnMappingSeeder.php` | Mappings colonnes |
| 8 | `database/seeders/SourceSeeder.php` | Les 4 sources |
| 9 | `database/seeders/RolePermissionSeeder.php` | Rôles et permissions |
| 10 | `app/Http/Controllers/Admin/ImportController.php` | Contrôleur import |
| 11 | `app/Http/Controllers/Admin/MatchingRuleController.php` | Contrôleur matching |
| 12 | `app/Models/Transaction.php` | Modèle transaction |
| 13 | `app/Models/NormalizedTransaction.php` | Modèle normalisé (pour matching) |
| 14 | `app/Models/MatchingRule.php` | Modèle règle de matching |
| 15 | `app/Models/Import.php` | Modèle import |
| 16 | `app/Policies/*.php` | Policies d'autorisation |
| 17 | `resources/views/layouts/app.blade.php` | Layout principal |
| 18 | `resources/views/dashboard.blade.php` | Dashboard avec graphiques |
| 19 | `resources/views/admin/reconciliation/index.blade.php` | Rapprochement manuel |
| 20 | `tests/Feature/RealSourceFileFormatsTest.php` | Test fichiers réels |

---

## Ressources externes utiles

| Sujet | Documentation |
|-------|---------------|
| Laravel 12 | https://laravel.com/docs/12.x |
| Spatie Permission | https://spatie.be/docs/laravel-permission/v6 |
| Maatwebsite Excel | https://docs.laravel-excel.com/3.1 |
| Yajra DataTables | https://yajrabox.com/docs/laravel-datatables |
| Pest PHP | https://pestphp.com/docs/installation |
| Bootstrap 5 | https://getbootstrap.com/docs/5.3 |
| Chart.js | https://www.chartjs.org/docs/latest |

---

## Checklist de validation

Avant de commencer à développer, vérifier :

- [ ] L'application se lance localement (`php artisan serve`)
- [ ] Les tests passent (`php artisan test`)
- [ ] La base de données est seedée (`php artisan migrate --seed`)
- [ ] Les assets sont buildés (`npm run build`)
- [ ] Le compte admin fonctionne (`admin@reconciliation.local`)
- [ ] Le worker de queue tourne (`php artisan queue:work`)
