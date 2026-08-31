# 3. Structure du repository

## Arborescence simplifiée

```
reconciliation-app/
├── app/                          # Code métier Laravel
│   ├── Contracts/                # Interfaces PHP (TransformPrimitive, ImportRowReader)
│   ├── DataTransferObjects/      # DTOs (BankData, SourceData, MatchingRunSummary, etc.)
│   ├── Enums/                    # Énumérations PHP (statuts, types, champs)
│   ├── Events/                   # Vide (pas d'événements métier custom)
│   ├── Exceptions/Import/        # Exceptions métier import
│   ├── Exports/                  # Classes d'export Maatwebsite
│   ├── Http/
│   │   ├── Controllers/          # Contrôleurs (Auth/, Admin/)
│   │   ├── Middleware/           # Middleware custom (SecurityHeaders)
│   │   └── Requests/             # Form Requests de validation
│   ├── Jobs/                     # Jobs asynchrones (queue)
│   ├── Listeners/                # Écouteurs d'événements auth
│   ├── Models/                   # Modèles Eloquent (+ Concerns/)
│   ├── Notifications/            # Notifications base de données
│   ├── Observers/                # Observer audit
│   ├── Policies/                 # Policies d'autorisation
│   ├── Providers/                # Service providers
│   ├── Repositories/             # Repository pattern (Settings)
│   ├── Services/                 # Services métier (Import/, Matching/)
│   └── View/Components/          # Composants Blade (AppLayout, GuestLayout)
│
├── bootstrap/                    # Bootstrap Laravel 12
├── config/                       # Configuration Laravel
├── database/
│   ├── factories/                # Factories Eloquent
│   ├── migrations/               # Migrations de schéma
│   └── seeders/                  # Seeders (données initiales)
│
├── docs/                         # Documentation (ce dossier)
├── public/                       # Point d'entrée web
├── resources/
│   ├── css/app.scss              # Styles SCSS (Bootstrap 5 + custom)
│   ├── js/app.js                 # JS entry point (jQuery, DataTables, Chart.js)
│   └── views/                    # Templates Blade
│       ├── admin/                # Vues admin par module
│       ├── auth/                 # Vues authentification
│       ├── components/           # Composants Blade réutilisables
│       ├── layouts/              # Layouts (app, guest)
│       ├── notifications/        # Centre de notifications
│       └── profile/              # Profil utilisateur
│
├── routes/
│   ├── web.php                   # Routes web (principal)
│   ├── auth.php                  # Routes auth Breeze
│   └── console.php               # Commandes Artisan
│
├── storage/                      # Stockage runtime
├── tests/                        # Tests (Pest/PHPUnit)
├── .env.example                  # Variables d'environnement
├── composer.json                 # Dépendances PHP
├── package.json                  # Dépendances JS
├── phpunit.xml                   # Configuration test
└── vite.config.js                # Configuration build
```

## Détail des dossiers clés

### `app/Http/Controllers/`

```
Controllers/
├── Controller.php                # Contrôleur abstrait de base
├── DashboardController.php       # Tableau de bord
├── ProfileController.php         # Profil utilisateur
├── NotificationController.php    # Centre de notifications
├── Auth/                         # Contrôleurs auth (Breeze)
│   ├── AuthenticatedSessionController.php
│   ├── RegisteredUserController.php
│   ├── NewPasswordController.php
│   └── ...
└── Admin/                        # Contrôleurs admin (tous les modules)
    ├── BankController.php
    ├── SourceController.php
    ├── SourceMappingController.php
    ├── ImportController.php
    ├── MatchingRuleController.php
    ├── MatchingResultController.php
    ├── ReconciliationController.php
    ├── ExceptionController.php
    ├── UserController.php
    ├── RoleController.php
    ├── AuditLogController.php
    └── ...
```

### `app/Services/`

```
Services/
├── DashboardMetricsService.php   # Agrégation KPIs dashboard
├── AuditLogService.php           # Service d'audit
├── SettingsService.php           # Lecture/écriture paramètres
├── Import/
│   ├── MappingEngine.php         # Transformation des lignes
│   ├── TransactionNormalizer.php # Normalisation transactions
│   ├── TransformRegistry.php     # Registre des transformations
│   ├── Readers/                  # Lecteurs de fichiers
│   │   ├── ImportRowReaderFactory.php
│   │   ├── CsvRowReader.php
│   │   ├── XlsxRowReader.php
│   │   └── RowRangeReadFilter.php
│   └── Transforms/               # Primitives de transformation
│       ├── TrimTransform.php
│       ├── StripPrefixCharsTransform.php
│       ├── FixedWidthMillimesTransform.php
│       ├── DecimalStringToMillimesTransform.php
│       ├── DateParseTransform.php
│       ├── SubstringAfterNthDelimiterTransform.php
│       ├── ZeroPadTransform.php
│       └── RightCharsTransform.php
└── Matching/                     # Moteur de rapprochement
    ├── RuleMatcher.php           # Appariement selon règles
    ├── DuplicateDetector.php     # Détection doublons
    ├── ConfidenceScorer.php      # Score de confiance
    └── UnmatchedSweeper.php      # Détection orphelins
```

### `app/Models/`

```
Models/
├── User.php                      # Utilisateur (Spatie Roles, SoftDeletes, Audit)
├── Bank.php                      # Banque
├── Source.php                    # Source de données
├── Currency.php                  # Devise
├── Holiday.php                   # Jour férié
├── Setting.php                   # Paramètre application
├── Import.php                    # Import de fichier
├── ImportRow.php                 # Ligne d'import
├── Transaction.php               # Transaction normalisée
├── NormalizedTransaction.php     # Vue normalisée (pour matching)
├── MatchingRule.php              # Règle de rapprochement
├── MatchingResult.php            # Résultat de matching
├── MatchingDetail.php            # Détail (transaction appariée)
├── MatchingExport.php            # Export asynchrone
├── ExceptionRecord.php           # Exception/anomalie
├── ExceptionAttachment.php       # Pièce jointe exception
├── AuditLog.php                  # Entrée journal d'audit
├── SourceColumnMapping.php       # Mapping colonnes source
└── Concerns/
    ├── HasUserstamps.php         # created_by/updated_by auto
    └── Auditable.php             # Observer audit
```

### `app/Enums/`

```
Enums/
├── ImportStatus.php              # pending, processing, completed, failed, partially_completed
├── ImportRowStatus.php           # pending, transformed, normalized, imported, error
├── MatchingStatus.php            # unmatched, matched, partial, conflict, ignored
├── MatchingResultStatus.php      # matched, partial, conflict, rejected
├── MatchingCardinality.php       # one_to_one, one_to_many, many_to_one, many_to_many
├── MappingTargetField.php        # reference, num_autorisation, amount, date, datetime, canal, currency_code, status_raw, secondary_reference, session
├── TransformType.php             # trim, strip_prefix_chars, fixed_width_millimes, decimal_string_to_millimes, date_parse, substring_after_nth_delimiter, zero_pad, right_chars
├── FileType.php                  # csv, xls, xlsx
├── ExceptionType.php             # unmatched, amount_mismatch, date_mismatch, duplicate, orphan, conflict
└── ExceptionStatus.php           # open, in_review, resolved, ignored
```

### `database/migrations/`

Les migrations créent les tables dans cet ordre approximatif :

```
2024_xx_xx_000000_create_users_table.php
2024_xx_xx_create_banks_table.php
2024_xx_xx_create_currencies_table.php
2024_xx_xx_create_sources_table.php
2024_xx_xx_create_source_column_mappings_table.php
2024_xx_xx_create_holidays_table.php
2024_xx_xx_create_settings_table.php
2024_xx_xx_create_imports_table.php
2024_xx_xx_create_import_rows_table.php
2024_xx_xx_create_transactions_table.php
2024_xx_xx_create_normalized_transactions_table.php
2024_xx_xx_create_matching_rules_table.php
2024_xx_xx_create_matching_results_table.php
2024_xx_xx_create_matching_details_table.php
2024_xx_xx_create_matching_exports_table.php
2024_xx_xx_create_exceptions_table.php
2024_xx_xx_create_exception_attachments_table.php
2024_xx_xx_create_audit_logs_table.php
2024_xx_xx_create_permission_tables.php (Spatie)
2024_xx_xx_create_sessions_table.php
2024_xx_xx_create_jobs_table.php
2024_xx_xx_create_cache_table.php
2026_08_29_000001_add_user_profile_fields_to_users_table.php
```

### `database/seeders/`

| Fichier | Rôle |
|---------|------|
| `DatabaseSeeder.php` | Point d'entrée, appelle les autres |
| `RolePermissionSeeder.php` | Rôles Spatie + permissions par module |
| `SourceSeeder.php` | 4 sources : ALPHA, BNA, WEB / STEG, SMT |
| `SourceColumnMappingSeeder.php` | Mappings colonnes par source |
| `MatchingRuleSeeder.php` | 6 règles de rapprochement |
| `UserSeeder.php` | Utilisateurs de démo |
| `BankSeeder.php` | Banques de référence |
| `CurrencySeeder.php` | Devises (TND, EUR, USD) |
| `SettingsSeeder.php` | Paramètres par défaut |

### `tests/`

```
tests/
├── Pest.php                      # Configuration + helpers (actingAsAdmin, etc.)
├── TestCase.php                  # Classe de base
├── Feature/                      # Tests d'intégration
│   ├── Admin/                    # Tests CRUD admin
│   │   ├── BankCrudTest.php
│   │   ├── UserCrudTest.php
│   │   ├── ImportUploadTest.php
│   │   ├── MatchingRuleRunTest.php
│   │   └── ...
│   ├── Auth/                     # Tests auth
│   ├── DashboardTest.php
│   ├── ProcessImportJobTest.php
│   ├── RealSourceFileFormatsTest.php
│   ├── NotificationTest.php
│   └── ...
└── Unit/                         # Tests unitaires
    ├── EnumsTest.php
    ├── MappingEngineTest.php
    ├── DashboardMetricsServiceTest.php
    ├── ModelFillableGuardTest.php
    ├── Matching/
    │   ├── RuleMatcherTest.php
    │   ├── DuplicateDetectorTest.php
    │   └── UnmatchedSweeperTest.php
    └── Transforms/               # Tests de chaque transform
        ├── TrimTransformTest.php
        ├── DateParseTransformTest.php
        └── ...
```

### `resources/views/`

```
views/
├── dashboard.blade.php           # Tableau de bord
├── auth/                         # Authentification
│   ├── login.blade.php
│   ├── register.blade.php
│   ├── forgot-password.blade.php
│   └── ...
├── admin/                        # Modules admin
│   ├── imports/                  # Import fichiers
│   ├── matching-rules/           # Règles de rapprochement
│   ├── matching-results/         # Résultats
│   ├── reconciliation/           # Rapprochement manuel
│   ├── exceptions/               # Gestion exceptions
│   ├── search/                   # Recherche multicritère
│   ├── banks/                    # CRUD banques
│   ├── sources/                  # CRUD sources (+ mappings/)
│   ├── currencies/               # CRUD devises
│   ├── holidays/                 # CRUD jours fériés
│   ├── settings/                 # Paramètres
│   ├── users/                    # CRUD utilisateurs
│   ├── roles/                    # CRUD rôles
│   └── audit-logs/               # Journal d'audit
├── components/                   # Composants Blade
│   ├── admin/                    # Composants admin (sidebar, topbar, breadcrumb)
│   └── ...                       # Composants formulaire (input, button, modal)
├── layouts/
│   ├── app.blade.php             # Layout principal authentifié
│   └── guest.blade.php           # Layout invité
├── notifications/
│   └── index.blade.php           # Centre notifications
└── profile/
    ├── edit.blade.php
    └── partials/                 # Formulaires partiels
```
