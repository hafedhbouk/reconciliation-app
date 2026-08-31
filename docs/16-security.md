# 16. Sécurité

## Authentification

| Aspect | Implémentation | Fichier |
|--------|----------------|---------|
| **Driver** | Session Laravel | `config/auth.php` |
| **Guard** | `web` | `config/auth.php` |
| **Hash** | Bcrypt (12 rounds) | `config/hashing.php` |
| **Remember me** | Cookie sécurisé | Laravel Breeze |
| **Email verification** | Requise pour admin | `routes/web.php` middleware `verified` |
| **Rate limiting** | 5-6 attempts | `app/Http/Requests/Auth/LoginRequest.php` |

## Autorisation

### RBAC (Role-Based Access Control)

| Aspect | Implémentation | Fichier |
|--------|----------------|---------|
| **Package** | `spatie/laravel-permission` ^8.3 | `config/permission.php` |
| **Rôles** | 7 rôles définis | `database/seeders/RolePermissionSeeder.php` |
| **Permissions** | ~40+ permissions par module | Contrôleurs + Policies |
| **Policies** | 11 policies | `app/Policies/` |
| **Super-admin bypass** | Via Spatie Permission | Configuration package |

### Policies

| Policy | Modèle | Contrôles |
|--------|--------|-----------|
| `BankPolicy` | `Bank` | CRUD + restore + forceDelete |
| `SourcePolicy` | `Source` | idem |
| `CurrencyPolicy` | `Currency` | idem |
| `HolidayPolicy` | `Holiday` | idem |
| `SettingPolicy` | `Setting` | viewAny, view, update |
| `UserPolicy` | `User` | CRUD + protection auto-suppression |
| `RolePolicy` | `Role` | CRUD |
| `AuditLogPolicy` | `AuditLog` | viewAny, view |
| `ImportPolicy` | `Import` | viewAny, view, create, delete |
| `MatchingRulePolicy` | `MatchingRule` | viewAny, create, update, delete |
| `MatchingResultPolicy` | `MatchingResult` | viewAny, view, create, delete |
| `ExceptionPolicy` | `ExceptionRecord` | viewAny, view, update |

## Validation des entrées

### Form Requests

Toutes les entrées utilisateur sont validées via des Form Requests dédiés :

| Request | Règles principales |
|---------|-------------------|
| `StoreBankRequest` | `code` unique, `name` required |
| `StoreSourceRequest` | `code` unique, `file_type` in, `bank_id` exists |
| `StoreImportRequest` | `source_id` active, `file` mimes, `confirmed_duplicate` |
| `StoreUserRequest` | `email` unique, `matricule` unique, `password` Password::defaults() |
| `StoreMatchingRuleRequest` | `source_a_id` != `source_b_id`, `cardinality` in, `priority` min 0 |
| `UpdateSourceMappingRequest` | Paramètres de transformation |

### Validation inline

Certaines validations sont faites directement dans les contrôleurs via `$request->validate([...])`.

## Protection CSRF

| Aspect | Implémentation |
|--------|----------------|
| **Protection** | Laravel intégré via `VerifyCsrfToken` |
| **Token** | `@csrf` dans les formulaires Blade |
| **Header AJAX** | `X-CSRF-TOKEN` via meta tag |
| **Axios** | Configure automatiquement le token |

## Protection XSS

| Aspect | Implémentation |
|--------|----------------|
| **Blade `{{ }}** | Échappement automatique |
| **`@json()`** | Échappement pour JS |
| **CSP** | Content Security Policy via middleware |

## Headers de sécurité

**Fichier :** `app/Http/Middleware/SecurityHeaders.php`

Ajouté globalement via `bootstrap/app.php` :

| Header | Valeur | Protection |
|--------|--------|------------|
| `X-Frame-Options` | `DENY` | Clickjacking |
| `X-Content-Type-Options` | `nosniff` | MIME sniffing |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Fuite referrer |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';` | XSS |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | HTTPS forcé |

> **Note :** Le CSP autorise `'unsafe-inline'` pour les scripts et styles, ce qui réduit la protection XSS mais est nécessaire pour le fonctionnement de certaines fonctionnalités (scripts inline dans les vues).

## Protection CORS

| Aspect | État |
|--------|------|
| **Configuration** | Non configurée (pas d'API externe) |
| **Headers CORS** | Non ajoutés |

## Protection SQL Injection

| Aspect | Implémentation |
|--------|----------------|
| **Eloquent ORM** | Requêtes paramétrées automatiquement |
| **Query Builder** | Bindings automatiques |
| **Raw queries** | Non utilisées dans le code analysé |

## Protection brute-force

| Cible | Limite | Fichier |
|-------|--------|---------|
| Login | 5 attempts | `app/Http/Requests/Auth/LoginRequest.php` |
| Register | 6 attempts | `routes/auth.php` |
| Forgot password | 6 attempts | `routes/auth.php` |
| Reset password | 6 attempts | `routes/auth.php` |
| Run matching rule | 10/min | `routes/web.php` |
| Export | 10/min | `routes/web.php` |

## Gestion des secrets

| Secret | Stockage |
|--------|----------|
| `APP_KEY` | `.env` (32 chars, base64) |
| `DB_PASSWORD` | `.env` |
| `MAIL_PASSWORD` | `.env` |
| `AWS_SECRET_ACCESS_KEY` | `.env` |
| `REDIS_PASSWORD` | `.env` |

## Upload de fichiers

| Aspect | Implémentation |
|--------|----------------|
| **Validation type** | `mimes:csv,txt,xls,xlsx` |
| **Stockage** | `storage/app/private` (disk local) |
| **Nom fichier** | Hash unique (pas le nom original) |
| **Nom original** | Stocké en base (`original_filename`) |
| **Hash** | SHA256 pour détection doublons |

### Pièces jointes exception

| Aspect | Implémentation |
|--------|----------------|
| **Validation** | `StoreExceptionAttachmentRequest` |
| **Rejet exécutables** | Vérification extension |
| **Stockage** | Disk configuré (`local` par défaut) |

## Gestion des sessions

| Paramètre | Valeur |
|-----------|--------|
| Driver | `database` |
| Table | `sessions` |
| Lifetime | 120 minutes |
| Encrypt | `false` (configurable) |
| HttpOnly | `true` (défaut Laravel) |
| Secure | `null` (dépend de l'environnement) |
| SameSite | `lax` (défaut Laravel) |

## Audit et traçabilité

| Aspect | Implémentation |
|--------|----------------|
| **Journal audit** | Table `audit_logs` |
| **Observer** | `AuditObserver` sur modèles `Auditable` |
| **Événements auth** | `LogSuccessfulLogin`, `LogFailedLogin`, `LogSuccessfulLogout` |
| **Données tracées** | IP, user agent, URL, old/new values |

## Points de vigilance

| Point | Niveau | Description |
|-------|--------|-------------|
| CSP `unsafe-inline` | ⚠️ Moyen | Permet l'exécution de scripts inline |
| Session encrypt `false` | ⚠️ Faible | Les données de session ne sont pas chiffrées en base |
| SECURE_COOKIE `null` | ⚠️ Moyen | Les cookies peuvent être transmis en HTTP |
| Pas de 2FA | ℹ️ Info | Authentification à un seul facteur |
| Hash MD5 ? | ✅ Non | Bcrypt utilisé |
| Mots de passe en clair ? | ✅ Non | Hashés avec Bcrypt |
| SQL raw ? | ✅ Non | Eloquent/Query Builder utilisé |
| Secrets en dur ? | ✅ Non | Tous dans `.env` |

## Recommandations sécurité

| Priorité | Recommandation |
|----------|----------------|
| **Haute** | Activer `SESSION_ENCRYPT=true` en production |
| **Haute** | Configurer `SESSION_SECURE_COOKIE=true` avec HTTPS |
| **Moyenne** | Renforcer le CSP (retirer `unsafe-inline` si possible) |
| **Moyenne** | Ajouter un système 2FA |
| **Faible** | Configurer CORS si API ajoutée |
| **Faible** | Rotation régulière de `APP_KEY` |
