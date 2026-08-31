# 15. Tests

## Framework de test

| Élément | Détail |
|---------|--------|
| **Framework principal** | Pest 3.x |
| **Moteur sous-jacent** | PHPUnit 11.5 |
| **Plugin Laravel** | `pestphp/pest-plugin-laravel` |
| **Base de test** | `tests\TestCase.php` |
| **Helpers** | `tests/Pest.php` |

## Configuration

### phpunit.xml

| Paramètre | Valeur |
|-----------|--------|
| Bootstrap | `vendor/autoload.php` |
| Test suites | `Unit` (`tests/Unit`), `Feature` (`tests/Feature`) |
| Couleurs | `true` |

### Environnement de test

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

### Helpers Pest (tests/Pest.php)

| Helper | Description |
|--------|-------------|
| `actingAsAdmin()` | Crée admin + authentifie |
| `actingAsPlainUser()` | Crée utilisateur sans permission |
| `actingAsDirector()` | Crée directeur |
| `actingAsDepartmentHead()` | Crée chef de département |
| `actingAsExecutionAgent()` | Crée agent d'exécution |

## Structure des tests

```
tests/
├── Pest.php                      # Configuration + helpers
├── TestCase.php                  # Classe de base
├── Feature/                      # Tests d'intégration
│   ├── Admin/                    # Tests admin
│   │   ├── BankCrudTest.php
│   │   ├── CurrencyCrudTest.php
│   │   ├── ExceptionCrudTest.php
│   │   ├── ExpensiveActionRateLimitTest.php
│   │   ├── ExportTest.php
│   │   ├── HolidayCrudTest.php
│   │   ├── ImportCrudTest.php
│   │   ├── ImportUploadTest.php
│   │   ├── MatchingResultCrudTest.php
│   │   ├── MatchingRuleCrudTest.php
│   │   ├── MatchingRuleRunTest.php
│   │   ├── ReconciliationTest.php
│   │   ├── RoleCrudTest.php
│   │   ├── SearchTest.php
│   │   ├── SettingCrudTest.php
│   │   ├── SourceColumnMappingCrudTest.php
│   │   ├── SourceCrudTest.php
│   │   └── UserCrudTest.php
│   ├── Auth/                     # Tests auth
│   │   ├── AuthenticationTest.php
│   │   ├── EmailVerificationTest.php
│   │   ├── PasswordConfirmationTest.php
│   │   ├── PasswordResetTest.php
│   │   ├── PasswordUpdateTest.php
│   │   ├── RateLimitingTest.php
│   │   └── RegistrationTest.php
│   ├── AuditLogTest.php
│   ├── DashboardTest.php
│   ├── NotificationControllerTest.php
│   ├── NotificationTest.php
│   ├── ProcessImportJobTest.php
│   ├── ProfileTest.php
│   ├── QueryBudgetTest.php
│   ├── RealSourceFileFormatsTest.php
│   ├── RolePermissionTest.php
│   └── SecurityHeadersTest.php
└── Unit/                         # Tests unitaires
    ├── DashboardMetricsServiceTest.php
    ├── EnumsTest.php
    ├── HasUserstampsTest.php
    ├── MappingEngineTest.php
    ├── ModelFillableGuardTest.php
    ├── Matching/
    │   ├── DuplicateDetectorTest.php
    │   ├── RuleMatcherTest.php
    │   └── UnmatchedSweeperTest.php
    └── Transforms/
        ├── DateParseTransformTest.php
        ├── DecimalStringToMillimesTransformTest.php
        ├── FixedWidthMillimesTransformTest.php
        ├── StripPrefixCharsTransformTest.php
        ├── SubstringAfterNthDelimiterTransformTest.php
        ├── TrimTransformTest.php
        └── ZeroPadTransformTest.php
```

## Couverture par domaine

### Tests Auth (7 fichiers)

| Test | Description |
|------|-------------|
| `AuthenticationTest` | Login, logout, invalid password |
| `EmailVerificationTest` | Vérification email, hash invalide |
| `PasswordConfirmationTest` | Confirmation mot de passe |
| `PasswordResetTest` | Demande reset, reset avec token |
| `PasswordUpdateTest` | Changement mot de passe |
| `RateLimitingTest` | Throttle register/forgot/reset |
| `RegistrationTest` | Inscription |

### Tests Admin (19 fichiers)

| Test | Description |
|------|-------------|
| `BankCrudTest` | CRUD banques, unique code, soft delete |
| `CurrencyCrudTest` | CRUD devises |
| `ExceptionCrudTest` | Liste, détail, résolution, pièces jointes |
| `ExpensiveActionRateLimitTest` | Rate limit run rule, export |
| `ExportTest` | Export CSV/XLSX/PDF, limites, permissions |
| `HolidayCrudTest` | CRUD jours fériés, doublon date/pays |
| `ImportCrudTest` | Liste, détail, filtres, permissions |
| `ImportUploadTest` | Upload, validation headers, doublon, process |
| `MatchingResultCrudTest` | Liste, détail, DataTables, permissions |
| `MatchingRuleCrudTest` | CRUD règles, sources identiques rejetées |
| `MatchingRuleRunTest` | Run single, run-all, permissions |
| `ReconciliationTest` | Recherche, appariement manuel, rejets |
| `RoleCrudTest` | CRUD rôles, protection rôles système |
| `SearchTest` | Recherche multicritère, filtres |
| `SettingCrudTest` | Liste groupée, update, cast integer |
| `SourceColumnMappingCrudTest` | Mapping, sauvegarde, permissions |
| `SourceCrudTest` | CRUD sources |
| `UserCrudTest` | CRUD utilisateurs, auto-suppression, permissions |

### Tests métier (6 fichiers)

| Test | Description |
|------|-------------|
| `AuditLogTest` | Audit sur CRUD banques, login/logout |
| `DashboardTest` | Rendu dashboard, KPIs, permissions |
| `NotificationTest` | Notifications import, matching, doublons, sweep |
| `NotificationControllerTest` | Marquer lu, permissions |
| `ProcessImportJobTest` | Job import, erreurs par ligne, échec headers |
| `ProfileTest` | Profil, update, suppression compte |

### Tests d'intégration (3 fichiers)

| Test | Description |
|------|-------------|
| `QueryBudgetTest` | Budget requêtes (N+1 guard) |
| `RealSourceFileFormatsTest` | Import fichiers réels WEB/SMT |
| `RolePermissionTest` | Matrice rôles/permissions |
| `SecurityHeadersTest` | Headers sécurité |

### Tests unitaires (16 fichiers)

| Test | Description |
|------|-------------|
| `DashboardMetricsServiceTest` | Agrégation KPIs, cache |
| `EnumsTest` | Labels, badge classes, core fields |
| `HasUserstampsTest` | Auto-fill created_by/updated_by |
| `MappingEngineTest` | Transformation, validation headers |
| `ModelFillableGuardTest` | Guard non-vide sur tous les modèles |
| `DuplicateDetectorTest` | Détection doublons, idempotence |
| `RuleMatcherTest` | Matching exact, partiel, conflit, tolérance |
| `UnmatchedSweeperTest` | Balayage orphelins, idempotence |
| `DateParseTransformTest` | Parsing formats dates |
| `DecimalStringToMillimesTransformTest` | Conversion décimal → millimes |
| `FixedWidthMillimesTransformTest` | Parsing fixed-width |
| `StripPrefixCharsTransformTest` | Suppression préfixes |
| `SubstringAfterNthDelimiterTransformTest` | Sous-chaîne après délimiteur |
| `TrimTransformTest` | Trim, null si vide |
| `ZeroPadTransformTest` | Zero-padding |

## Exécution des tests

```bash
# Tous les tests
php artisan test

# Filtre par nom
php artisan test --filter="RuleMatcherTest"

# Suite spécifique
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Avec couverture (si Xdebug/PCOV installé)
php artisan test --coverage
```

## Résultats actuels

| Métrique | Valeur |
|----------|--------|
| Tests | 238 passed |
| Assertions | 821 assertions |
| Durée | ~107 secondes |

## Fixtures et factories

### Factories

| Fichier | Modèle |
|---------|--------|
| `database/factories/UserFactory.php` | `User` |

> **Note :** Peu de factories sont définis. Les tests utilisent principalement des seeders et des insertions directes.

### Seeders utilisés dans les tests

| Seeder | Usage |
|--------|-------|
| `RolePermissionSeeder` | Rôles et permissions pour tests RBAC |
| `SourceSeeder` | Sources pour tests import |
| `SourceColumnMappingSeeder` | Mappings pour tests import |
| `MatchingRuleSeeder` | Règles pour tests matching |

## Points forts des tests

| Point | Description |
|-------|-------------|
| **Couverture RBAC** | Tests complets des permissions par rôle |
| **Tests job** | ProcessImportJob testé end-to-end |
| **Tests réels** | Import de vrais fichiers WEB/SMT |
| **Budget requêtes** | N+1 guard sur dashboard et search |
| **Idempotence** | Tests de RuleMatcher, DuplicateDetector, Sweep |
| **Transforms** | Chaque transform testé unitairement |

## Couverture manquante estimée

| Domaine | Couverture |
|---------|------------|
| Auth | ✅ Complète |
| Admin CRUD | ✅ Complète |
| Import | ✅ Complète |
| Matching | ✅ Complète |
| Reconciliation | ✅ Partielle |
| Exceptions | ✅ Partielle |
| Notifications | ✅ Complète |
| Dashboard | ✅ Partielle |
| Exports | ✅ Partielle |
| Settings | ✅ Partielle |
| Audit | ✅ Partielle |
| Frontend JS | ❌ Non testé |
| E2E (navigateur) | ❌ Non testé |
