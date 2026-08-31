# 13. Intégrations externes

## Résumé

L'application **n'intègre aucun service externe actif** en production par défaut. Toutes les fonctionnalités sont auto-contenues dans le monolithe Laravel.

## Services configurés mais non actifs

### Email (SMTP)

| Paramètre | Valeur |
|-----------|--------|
| Driver par défaut | `log` (pas d'envoi réel) |
| SMTP configuré | Oui (127.0.0.1:2525) |
| From address | `hello@example.com` |
| From name | `${APP_NAME}` |

**Fichier :** `config/mail.php`

> **Note :** En production, le driver doit être changé vers `smtp` ou un service transactionnel (SES, Postmark, Resend).

### AWS S3

| Paramètre | Valeur |
|-----------|--------|
| Driver | `s3` |
| Configuré | Non (variables vides) |
| Usage potentiel | Stockage fichiers |

**Fichier :** `config/filesystems.php`

### Redis

| Paramètre | Valeur |
|-----------|--------|
| Client | `phpredis` |
| Host | `127.0.0.1` |
| Port | `6379` |
| Usage | Non utilisé par défaut (cache/session sur database) |

**Fichier :** `config/database.php` (section redis)

## Services potentiels (non implémentés)

Les drivers suivants sont configurés mais **pas utilisés** :

| Service | Driver | Fichier config |
|---------|--------|----------------|
| Cache | `memcached` | `config/cache.php` |
| Cache | `dynamodb` | `config/cache.php` |
| Queue | `beanstalkd` | `config/queue.php` |
| Queue | `sqs` | `config/queue.php` |
| Queue | `redis` | `config/queue.php` |
| Mail | `ses` | `config/mail.php` |
| Mail | `postmark` | `config/mail.php` |
| Mail | `resend` | `config/mail.php` |

## Absence d'intégrations

Les éléments suivants **ne sont PAS présents** dans le code :

- ❌ API REST externe
- ❌ Webhooks entrants/sortants
- ❌ OAuth/SSO (LDAP, SAML, Azure AD, etc.)
- ❌ SMS
- ❌ Push notifications
- ❌ Analytics (Google Analytics, Mixpanel, etc.)
- ❌ Monitoring externe (Sentry, New Relic, Datadog, etc.)
- ❌ CDN
- ❌ Stockage cloud actif
- ❌ Service de logs externe (Papertrail, Loggly, etc.)
- ❌ File d'attente externe active (SQS, Redis, etc.)

## Communication sortante

La seule communication sortante potentielle est l'envoi d'email via SMTP, mais le driver par défaut est `log` (pas d'envoi réel).

## API consommées

L'application ne consomme **aucune API externe**. Toutes les données proviennent :
- Des fichiers uploadés par les utilisateurs (CSV/XLSX)
- De la base de données locale
