# 8. API et endpoints

> **Note :** Cette application n'expose **pas d'API REST publique** (`routes/api.php` n'existe pas). Tous les endpoints sont des routes web qui retournent du HTML (Blade) ou du JSON (pour DataTables/AJAX).

## Endpoints DataTables (JSON)

Ces endpoints alimentent les tableaux DataTables côté client avec traitement serveur.

### Imports

| Méthode | URL | Contrôleur | Paramètres |
|---------|-----|------------|------------|
| `GET` | `/admin/imports/data` | `ImportController@data` | DataTables standard (draw, start, length, search, order) |

**Retour JSON :**
```json
{
    "draw": 1,
    "recordsTotal": 42,
    "recordsFiltered": 10,
    "data": [...]
}
```

### Matching Rules

| Méthode | URL | Contrôleur | Paramètres |
|---------|-----|------------|------------|
| `GET` | `/admin/matching-rules/data` | `MatchingRuleController@data` | DataTables standard |

### Matching Results

| Méthode | URL | Contrôleur | Paramètres |
|---------|-----|------------|------------|
| `GET` | `/admin/matching-results/data` | `MatchingResultController@data` | `matching_rule_id`, `matched_at_from`, `matched_at_to`, `status` + DataTables standard |

### Exceptions

| Méthode | URL | Contrôleur | Paramètres |
|---------|-----|------------|------------|
| `GET` | `/admin/exceptions/data` | `ExceptionController@data` | DataTables standard |

### Search

| Méthode | URL | Contrôleur | Paramètres |
|---------|-----|------------|------------|
| `GET` | `/admin/search/data` | `SearchController@data` | `source_id`, `reference`, `amount_min`, `amount_max`, `date_from`, `date_to`, `status`, `canal` + DataTables standard |

### Audit Logs

| Méthode | URL | Contrôleur | Paramètres |
|---------|-----|------------|------------|
| `GET` | `/admin/audit-logs/data` | `AuditLogController@data` | DataTables standard |

### Users

| Méthode | URL | Contrôleur | Paramètres |
|---------|-----|------------|------------|
| `GET` | `/admin/users/data` | `UserController@data` | DataTables standard |

## Endpoints d'action

### Imports

| Méthode | URL | Nom | Contrôleur | Auth | Description |
|---------|-----|-----|------------|------|-------------|
| `POST` | `/admin/imports` | `admin.imports.store` | `ImportController@store` | admin | Upload fichier |
| `POST` | `/admin/imports/{import}/process` | `admin.imports.process` | `ImportController@process` | admin | Déclencher traitement |
| `DELETE` | `/admin/imports/{import}` | `admin.imports.destroy` | `ImportController@destroy` | admin | Supprimer import |

### Matching

| Méthode | URL | Nom | Contrôleur | Auth | Description |
|---------|-----|-----|------------|------|-------------|
| `POST` | `/admin/matching-rules` | `admin.matching-rules.store` | `MatchingRuleController@store` | admin | Créer règle |
| `PUT` | `/admin/matching-rules/{rule}` | `admin.matching-rules.update` | `MatchingRuleController@update` | admin | Modifier règle |
| `DELETE` | `/admin/matching-rules/{rule}` | `admin.matching-rules.destroy` | `MatchingRuleController@destroy` | admin | Supprimer règle |
| `POST` | `/admin/matching-rules/{rule}/run` | `admin.matching-rules.run` | `MatchingRuleController@run` | admin + throttle | Exécuter règle |
| `POST` | `/admin/matching-rules/run-all` | `admin.matching-rules.run-all` | `MatchingRuleController@runAll` | admin + throttle | Exécuter toutes les règles |
| `POST` | `/admin/matching-rules/detect-duplicates` | `admin.matching-rules.detect-duplicates` | `MatchingRuleController@detectDuplicates` | admin + throttle | Détecter doublons |
| `POST` | `/admin/matching-rules/sweep-unmatched` | `admin.matching-rules.sweep-unmatched` | `MatchingRuleController@sweepUnmatched` | admin + throttle | Balayer orphelins |
| `DELETE` | `/admin/matching-results/{result}` | `admin.matching-results.destroy` | `MatchingResultController@destroy` | admin | Supprimer résultat |

### Reconciliation

| Méthode | URL | Nom | Contrôleur | Auth | Description |
|---------|-----|-----|------------|------|-------------|
| `GET` | `/admin/reconciliation/search` | `admin.reconciliation.search` | `ReconciliationController@search` | admin | Rechercher transactions non appariées |
| `POST` | `/admin/reconciliation` | `admin.reconciliation.store` | `ReconciliationController@store` | admin | Créer appariement manuel |

### Exceptions

| Méthode | URL | Nom | Contrôleur | Auth | Description |
|---------|-----|-----|------------|------|-------------|
| `PATCH` | `/admin/exceptions/{exception}` | `admin.exceptions.update` | `ExceptionController@update` | admin | Résoudre/reclasser |
| `POST` | `/admin/exceptions/{exception}/attachments` | `admin.exceptions.attachments.store` | `ExceptionAttachmentController@store` | admin | Upload pièce jointe |
| `GET` | `/admin/exceptions/{exception}/attachments/{attachment}/download` | `admin.exceptions.attachments.download` | `ExceptionAttachmentController@download` | admin | Télécharger pièce jointe |
| `DELETE` | `/admin/exceptions/{exception}/attachments/{attachment}` | `admin.exceptions.attachments.destroy` | `ExceptionAttachmentController@destroy` | admin | Supprimer pièce jointe |

### Sources

| Méthode | URL | Nom | Contrôleur | Auth | Description |
|---------|-----|-----|------------|------|-------------|
| `GET` | `/admin/sources/{source}/mappings` | `admin.sources.mappings.edit` | `SourceMappingController@edit` | admin | Écran mapping colonnes |
| `PUT` | `/admin/sources/{source}/mappings` | `admin.sources.mappings.update` | `SourceMappingController@update` | admin | Sauvegarder mapping |

### Notifications

| Méthode | URL | Nom | Contrôleur | Auth | Description |
|---------|-----|-----|------------|------|-------------|
| `POST` | `/notifications/{notification}/read` | `notifications.read` | `NotificationController@markAsRead` | auth | Marquer comme lue |
| `POST` | `/notifications/read-all` | `notifications.read-all` | `NotificationController@markAllAsRead` | auth | Tout marquer lu |

## Endpoints d'export

| Méthode | URL | Nom | Contrôleur | Format | Auth |
|---------|-----|-----|------------|--------|------|
| `GET` | `/admin/search/export/{format}` | `admin.search.export` | `SearchController@export` | csv/xlsx/pdf | admin + throttle |
| `GET` | `/admin/exceptions/export/{format}` | `admin.exceptions.export` | `ExceptionController@export` | csv/xlsx/pdf | admin + throttle |
| `GET` | `/admin/matching-results/export/{format}` | `admin.matching-results.export` | `MatchingResultController@export` | csv/xlsx/pdf | admin + throttle |
| `POST` | `/admin/matching-results/export-async` | `admin.matching-results.export-async` | `MatchingResultController@exportAsync` | csv/xlsx/pdf | admin + throttle |
| `GET` | `/admin/matching-results/exports` | `admin.matching-results.exports` | `MatchingResultController@exports` | html | admin |
| `GET` | `/admin/matching-results/exports/{token}/download` | `admin.matching-results.exports.download` | `MatchingResultController@downloadExport` | fichier | admin |

## Endpoints CRUD génériques

Pour chaque ressource (`banks`, `currencies`, `holidays`, `users`, `roles`, `sources`, `settings`) :

| Méthode | URL | Action | Description |
|---------|-----|--------|-------------|
| `GET` | `/admin/{resource}` | `index` | Liste |
| `GET` | `/admin/{resource}/create` | `create` | Formulaire création |
| `POST` | `/admin/{resource}` | `store` | Enregistrer |
| `GET` | `/admin/{resource}/{id}/edit` | `edit` | Formulaire édition |
| `PUT` | `/admin/{resource}/{id}` | `update` | Modifier |
| `DELETE` | `/admin/{resource}/{id}` | `destroy` | Supprimer |

## Endpoints Auth (Breeze)

| Méthode | URL | Nom | Description |
|---------|-----|-----|-------------|
| `GET` | `/login` | `login` | Formulaire connexion |
| `POST` | `/login` | - | Authentification |
| `POST` | `/logout` | `logout` | Déconnexion |
| `GET` | `/register` | `register` | Formulaire inscription |
| `POST` | `/register` | - | Création compte |
| `GET` | `/forgot-password` | - | Demande reset |
| `POST` | `/forgot-password` | - | Envoi email reset |
| `GET` | `/reset-password/{token}` | - | Formulaire reset |
| `POST` | `/reset-password` | - | Application nouveau mot de passe |
| `GET` | `/verify-email` | - | Notice vérification |
| `GET` | `/verify-email/{id}/{hash}` | - | Vérification email |
| `POST` | `/email/verification-notification` | - | Renvoi email |
| `GET` | `/confirm-password` | - | Confirmation mot de passe |
| `POST` | `/confirm-password` | - | Validation mot de passe |

## Détail des endpoints critiques

### POST `/admin/imports` — Upload fichier

**Contrôleur :** `ImportController@store`
**Request :** `StoreImportRequest`
**Services :** `ImportRowReaderFactory`, `MappingEngine`

```
Flow:
1. Valide source_id, file (csv/txt/xls/xlsx), confirmed_duplicate
2. Calcule hash fichier, stocke fichier
3. Crée Import (status=Pending)
4. Valide headers via MappingEngine::validateHeaders()
5. Si headers OK → dispatch ProcessImportJob
6. Si headers manquants → redirect vers mapping
7. Si doublon → warning en session
```

**Erreurs possibles :**
- 422 : Validation (fichier manquant, extension invalide)
- Redirect avec erreurs si headers manquants

---

### POST `/admin/imports/{import}/process` — Déclencher traitement

**Contrôleur :** `ImportController@process`
**Services :** `ImportRowReaderFactory`, `MappingEngine`

```
Flow:
1. Vérifie qu'un job n'est pas déjà en cours
2. Reset statut import
3. Dispatch ProcessImportJob
4. Redirect avec message succès
```

**Erreurs possibles :**
- 422 : Job déjà en cours
- Redirect avec erreur si déjà dispatched

---

### POST `/admin/matching-rules/{rule}/run` — Exécuter une règle

**Contrôleur :** `MatchingRuleController@run`
**Middleware :** `throttle:expensive-actions` (10/min)

```
Flow:
1. Vérifie permissions
2. Génère batch_reference unique
3. Dispatch RunMatchingRuleJob(ruleId, batchRef, userId)
4. Redirect avec message
```

**Job :** `RunMatchingRuleJob` → `RuleMatcher::match()` → `MatchingActionCompletedNotification`

---

### POST `/admin/matching-rules/run-all` — Exécuter toutes les règles

**Contrôleur :** `MatchingRuleController@runAll`
**Middleware :** `throttle:expensive-actions` (10/min)

```
Flow:
1. Récupère règles actives ordonnées par priority
2. Batch Laravel : chaque rule → RunMatchingRuleJob
3. En fin de batch : DetectDuplicatesJob → SweepUnmatchedJob → NotifyMatchingBatchCompleteJob
4. Dispatch le batch
```

**Jobs chaînés :**
- `RunMatchingRuleJob` (×N règles)
- `DetectDuplicatesJob`
- `SweepUnmatchedJob`
- `NotifyMatchingBatchCompleteJob`

---

### POST `/admin/reconciliation` — Appariement manuel

**Contrôleur :** `ReconciliationController@store`
**Request :** `StoreManualMatchRequest`

```
Flow:
1. Valide side_a_ids[], side_b_ids[] (non vides, distincts)
2. Crée MatchingResult manuel
3. Crée MatchingDetail pour chaque transaction
4. Met à jour statut NormalizedTransaction → Matched
5. Redirect avec succès
```

**Erreurs possibles :**
- 422 : Sélections vides, même ID des deux côtés, transaction déjà appariée
