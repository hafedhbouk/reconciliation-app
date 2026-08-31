# 11. Workflows métier principaux

## 1. Import d'un fichier de paiement

### Description fonctionnelle

L'utilisateur upload un fichier CSV/XLSX provenant d'une banque (ALPHA, BNA, WEB/STEG, SMT). Le système valide les en-têtes, transforme les données, les normalise et les stocke en base.

### Diagramme de séquence

```mermaid
sequenceDiagram
    participant U as Utilisateur
    participant F as Formulaire Upload
    participant IC as ImportController
    participant IRF as ImportRowReaderFactory
    participant ME as MappingEngine
    participant DB as Base de données
    participant J as ProcessImportJob
    participant N as Notification

    U->>F: Sélection source + fichier
    F->>IC: POST /admin/imports
    IC->>IRF: make(source)
    IC->>ME: validateHeaders(headers, mappings)
    alt Headers OK
        IC->>DB: Create Import (status=Pending)
        IC->>J: dispatch(ProcessImportJob)
        IC-->>F: Redirect vers show
        J->>IRF: read(fichier)
        loop Pour chaque ligne
            J->>ME: transformRow(row, mappings)
            J->>DB: Insert ImportRow
            J->>DB: Insert Transaction
            J->>DB: Insert NormalizedTransaction
        end
        J->>DB: Update Import (status=Completed)
        J->>N: ImportProcessedNotification
        N-->>U: Notification en base
    else Headers manquants
        IC-->>F: Redirect vers mapping
    end
```

### Fichiers concernés

| Étape | Fichier | Méthode/Classe |
|-------|---------|----------------|
| Formulaire | `resources/views/admin/imports/create.blade.php` | - |
| Upload | `app/Http/Controllers/Admin/ImportController.php` | `store()` |
| Validation | `app/Http/Requests/StoreImportRequest.php` | `rules()` |
| Lecteur | `app/Services/Import/Readers/ImportRowReaderFactory.php` | `make()` |
| Validation headers | `app/Services/Import/MappingEngine.php` | `validateHeaders()` |
| Job | `app/Jobs/ProcessImportJob.php` | `handle()` |
| Transformation | `app/Services/Import/MappingEngine.php` | `transformRow()` |
| Normalisation | `app/Services/Import/TransactionNormalizer.php` | `buildTransactionRow()` |
| Notification | `app/Notifications/ImportProcessedNotification.php` | - |

### Entités modifiées

| Entité | Opération |
|--------|-----------|
| `imports` | INSERT |
| `import_rows` | INSERT (par ligne) |
| `transactions` | INSERT (par ligne) |
| `normalized_transactions` | INSERT (par ligne) |
| `notifications` | INSERT (à la fin) |

### Permissions

- `imports.create` — Pour uploader
- `imports.view` — Pour voir le détail

### Erreurs possibles

| Erreur | Cause | Gestion |
|--------|-------|---------|
| 422 Validation | Fichier manquant, extension invalide | Retour formulaire avec erreurs |
| Headers manquants | Colonnes requises absentes | Redirect vers mapping |
| Doublon détecté | Hash fichier identique | Warning en session |
| Job déjà en cours | Double dispatch | Message d'erreur |
| Erreur transformation | Données invalides dans une ligne | Ligne marquée Error, import continue |
| Erreur globale | Exception non catchée | Import marqué Failed |

---

## 2. Rapprochement automatique (matching)

### Description fonctionnelle

Le système exécute une règle de rapprochement qui compare les transactions normalisées de deux sources et crée des résultats (matched/partial/conflict) avec score de confiance.

### Diagramme de séquence

```mermaid
sequenceDiagram
    participant U as Utilisateur
    participant RC as MatchingRuleController
    participant J as RunMatchingRuleJob
    participant RM as RuleMatcher
    participant DB as Base de données
    participant N as Notification

    U->>RC: POST /admin/matching-rules/{rule}/run
    RC->>J: dispatch(RunMatchingRuleJob)
    RC-->>U: Redirect avec message
    J->>RM: match(rule, batchRef)
    RM->>DB: Fetch NormalizedTransactions (source A)
    RM->>DB: Fetch NormalizedTransactions (source B)
    RM->>RM: Group by primary key
    loop Pour chaque groupe
        RM->>RM: Verify amount + date
        alt Exact match
            RM->>DB: Insert MatchingResult (matched, 100%)
            RM->>DB: Insert MatchingDetails
            RM->>DB: Update NormalizedTransaction (matched)
        else Partial (tolerance)
            RM->>DB: Insert MatchingResult (partial, 85%)
            RM->>DB: Insert MatchingDetails
            RM->>DB: Update NormalizedTransaction (partial)
        else Conflict
            RM->>DB: Insert MatchingResult (conflict)
            RM->>DB: Insert Exception (amount/date mismatch)
        else No signal
            RM->>RM: Skip
        end
    end
    RM-->>J: MatchingRunSummary
    J->>N: MatchingActionCompletedNotification
    N-->>U: Notification en base
```

### Fichiers concernés

| Étape | Fichier | Méthode/Classe |
|-------|---------|----------------|
| Déclenchement | `app/Http/Controllers/Admin/MatchingRuleController.php` | `run()` |
| Job | `app/Jobs/RunMatchingRuleJob.php` | `handle()` |
| Moteur | `app/Services/Matching/RuleMatcher.php` | `match()` |
| Score | `app/Services/Matching/ConfidenceScorer.php` | `score()` |
| Notification | `app/Notifications/MatchingActionCompletedNotification.php` | - |

### Entités modifiées

| Entité | Opération |
|--------|-----------|
| `matching_results` | INSERT |
| `matching_details` | INSERT (par transaction appariée) |
| `normalized_transactions` | UPDATE (matching_status) |
| `exceptions` | INSERT (si conflit) |
| `notifications` | INSERT |

### Permissions

- `matching-rules.update` — Pour exécuter une règle

### Règles de matching disponibles

| Règle | Source A | Source B | Clé primaire | Vérification |
|-------|----------|----------|--------------|--------------|
| ALPHA-BNA | ALPHA | BNA | num_autorisation | amount, date |
| SMT-BNA | SMT | BNA | date\|amount (composite) | - |
| WEB-BNA | WEB | BNA | secondary_reference (recu_paie) vs num_autorisation | - |
| ALPHA-WEB | ALPHA | WEB | reference | num_autorisation vs secondary_reference |
| ALPHA-SMT | ALPHA | SMT | date\|amount (composite) | - |
| WEB-SMT | WEB | SMT | date\|amount (composite) | - |

---

## 3. Exécution batch (Lancer tout)

### Description fonctionnelle

L'utilisateur déclenche l'exécution de toutes les règles actives en une seule action, suivie de la détection des doublons et du balayage des orphelins.

### Diagramme de séquence

```mermaid
sequenceDiagram
    participant U as Utilisateur
    participant RC as MatchingRuleController
    participant Batch as Laravel Batch
    participant R1..N as RunMatchingRuleJob ×N
    participant DD as DetectDuplicatesJob
    participant SU as SweepUnmatchedJob
    participant NC as NotifyMatchingBatchCompleteJob
    participant N as Notification

    U->>RC: POST /admin/matching-rules/run-all
    RC->>Batch: batch([R1, R2, ..., DD, SU, NC])
    RC-->>U: Redirect avec message
    par Exécution parallèle
        R1->>R1: match(rule1)
        R2->>R2: match(rule2)
    end
    DD->>DD: scan()
    SU->>SU: sweep()
    NC->>N: MatchingActionCompletedNotification
    N-->>U: Notification résumé batch
```

### Fichiers concernés

| Étape | Fichier | Méthode/Classe |
|-------|---------|----------------|
| Déclenchement | `app/Http/Controllers/Admin/MatchingRuleController.php` | `runAll()` |
| Job règle | `app/Jobs/RunMatchingRuleJob.php` | `handle()` |
| Job doublons | `app/Jobs/DetectDuplicatesJob.php` | `handle()` |
| Job orphelins | `app/Jobs/SweepUnmatchedJob.php` | `handle()` |
| Job notification | `app/Jobs/NotifyMatchingBatchCompleteJob.php` | `handle()` |
| Détection doublons | `app/Services/Matching/DuplicateDetector.php` | `scan()` |
| Balayage orphelins | `app/Services/Matching/UnmatchedSweeper.php` | `sweep()` |

### Permissions

- `matching-rules.update` — Pour lancer le batch
- Rate limit : 10/min (`throttle:expensive-actions`)

---

## 4. Rapprochement manuel

### Description fonctionnelle

L'utilisateur recherche des transactions non appariées, sélectionne des transactions côté A et côté B, et crée un appariement manuel.

### Diagramme de séquence

```mermaid
sequenceDiagram
    participant U as Utilisateur
    participant V as Vue reconciliation
    participant RC as ReconciliationController
    participant DB as Base de données

    U->>V: Ouvrir /admin/reconciliation
    V->>RC: GET /admin/reconciliation/search
    RC->>DB: Query unmatched NormalizedTransactions
    DB-->>RC: Results
    RC-->>V: JSON (Side A / Side B)
    U->>V: Sélectionner transactions A + B
    V->>RC: POST /admin/reconciliation
    RC->>DB: Insert MatchingResult (manual)
    RC->>DB: Insert MatchingDetails
    RC->>DB: Update NormalizedTransactions (matched)
    RC-->>V: Redirect avec succès
```

### Fichiers concernés

| Étape | Fichier | Méthode/Classe |
|-------|---------|----------------|
| Interface | `resources/views/admin/reconciliation/index.blade.php` | - |
| Recherche | `app/Http/Controllers/Admin/ReconciliationController.php` | `search()` |
| Validation | `app/Http/Requests/StoreManualMatchRequest.php` | `rules()` |
| Création | `app/Http/Controllers/Admin/ReconciliationController.php` | `store()` |

### Permissions

- `matching-results.create` — Pour créer un appariement manuel

### Erreurs possibles

| Erreur | Cause |
|--------|-------|
| 422 | Sélections vides |
| 422 | Même transaction des deux côtés |
| 422 | Transaction déjà appariée |

---

## 5. Résolution d'une exception

### Description fonctionnelle

L'utilisateur consulte une exception, la résout (avec commentaire) ou la reclassifie.

### Diagramme de séquence

```mermaid
sequenceDiagram
    participant U as Utilisateur
    participant V = Vue exception
    participant EC = ExceptionController
    participant DB = Base de données

    U->>V: Ouvrir /admin/exceptions/{exception}
    V->>EC: GET show(exception)
    EC->>DB: Fetch exception + transaction + attachments
    DB-->>EC: Data
    EC-->>V: Vue détail
    U->>V: Résoudre / Reclasser
    V->>EC: PATCH /admin/exceptions/{exception}
    EC->>DB: Update exception (status=resolved, resolved_by, resolved_at)
    EC->>DB: Insert audit_log
    EC-->>V: Redirect avec succès
```

### Fichiers concernés

| Étape | Fichier | Méthode/Classe |
|-------|---------|----------------|
| Liste | `resources/views/admin/exceptions/index.blade.php` | - |
| Détail | `resources/views/admin/exceptions/show.blade.php` | - |
| Contrôleur | `app/Http/Controllers/Admin/ExceptionController.php` | `show()`, `update()` |
| Pièces jointes | `app/Http/Controllers/Admin/ExceptionAttachmentController.php` | `store()`, `download()`, `destroy()` |

### Permissions

- `exceptions.view` — Pour voir
- `exceptions.update` — Pour résoudre/reclasser

---

## 6. Export de données

### Description fonctionnelle

L'utilisateur exporte les résultats de recherche, matching ou exceptions en CSV, XLSX ou PDF.

### Fichiers concernés

| Étape | Fichier | Méthode/Classe |
|-------|---------|----------------|
| Export synchrone | `app/Http/Controllers/Admin/SearchController.php` | `export()` |
| Export synchrone | `app/Http/Controllers/Admin/MatchingResultController.php` | `export()` |
| Export synchrone | `app/Http/Controllers/Admin/ExceptionController.php` | `export()` |
| Export asynchrone | `app/Http/Controllers/Admin/MatchingResultController.php` | `exportAsync()` |
| Job export | `app/Jobs/GenerateMatchingExportJob.php` | `handle()` |
| Classe export | `app/Exports/GenericTableExport.php` | - |

### Permissions

- `search.viewAny` / `matching-results.viewAny` / `exceptions.viewAny`
- Rate limit : 10/min

### Formats supportés

| Format | Librairie | Limite lignes |
|--------|-----------|---------------|
| CSV | Maatwebsite/Excel | Illimité |
| XLSX | Maatwebsite/Excel | 1000 |
| PDF | DomPDF | 1000 |

---

## 7. Création d'un utilisateur

### Description fonctionnelle

L'admin crée un utilisateur avec profil complet (prénom, nom, matricule, portable, email, mot de passe, rôles).

### Fichiers concernés

| Étape | Fichier | Méthode/Classe |
|-------|---------|----------------|
| Formulaire | `resources/views/admin/users/create.blade.php` | - |
| Composant formulaire | `resources/views/components/admin/users/form.blade.php` | - |
| Contrôleur | `app/Http/Controllers/Admin/UserController.php` | `store()` |
| Validation | `app/Http/Requests/StoreUserRequest.php` | `rules()` |

### Permissions

- `users.create`

### Champs du profil

| Champ | Règle |
|-------|-------|
| `prenom` | nullable, string |
| `nom` | nullable, string |
| `name` | required, string |
| `matricule` | nullable, unique |
| `portable` | nullable, string |
| `email` | required, unique, email |
| `password` | required, Password::defaults() |
| `roles` | array, exists in roles |

---

## 8. Configuration du mapping de colonnes

### Description fonctionnelle

L'administrateur configure comment les colonnes d'un fichier source sont mappées vers les champs internes, avec les transformations à appliquer.

### Fichiers concernés

| Étape | Fichier | Méthode/Classe |
|-------|---------|----------------|
| Interface | `resources/views/admin/sources/mappings/edit.blade.php` | - |
| Contrôleur | `app/Http/Controllers/Admin/SourceMappingController.php` | `edit()`, `update()` |
| Validation | `app/Http/Requests/UpdateSourceMappingRequest.php` | `rules()` |
| Construction transform | `app/Http/Controllers/Admin/SourceMappingController.php` | `buildTransformSteps()` |

### Permissions

- `sources.update` (implicitement via admin)

### Transformations configurables

| Transform | Paramètres |
|-----------|------------|
| `trim` | - |
| `strip_prefix_chars` | `chars` (array) |
| `fixed_width_millimes` | - |
| `decimal_string_to_millimes` | `decimals` |
| `date_parse` | `format`, `output` (date/datetime) |
| `substring_after_nth_delimiter` | `delimiter`, `n`, `length` |
| `zero_pad` | `length` |
| `right_chars` | `length` |
