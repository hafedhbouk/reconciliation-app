# Environnements

## Vue d'ensemble

L'application supporte trois environnements :

| Environnement | Branche | Fichier Docker Compose | Usage |
|---------------|---------|------------------------|-------|
| Development | `develop` | `docker-compose.dev.yml` | Développement local |
| Staging | `main` | `docker-compose.yml` | Tests pré-production |
| Production | `v*` tags | `docker-compose.yml` | Production |

## Development

### Configuration

```bash
# Utiliser le fichier docker-compose.dev.yml
docker compose -f docker-compose.dev.yml up -d
```

### Caractéristiques

- **Hot reload** : Le code source est monté en volume
- **Mode debug** : `APP_DEBUG=true`
- **Pas d'optimisation** : Pas de cache de configuration
- **Logs détaillés** : Tous les logs sont visibles

### Services

| Service | Port | Description |
|---------|------|-------------|
| `app` | 8080 | Application Laravel |
| `mysql` | 3306 | Base de données |
| `queue-worker` | - | Worker de queue |

### Commandes

```bash
# Démarrer
docker compose -f docker-compose.dev.yml up -d

# Voir les logs
docker compose -f docker-compose.dev.yml logs -f

# Exécuter une commande
docker compose -f docker-compose.dev.yml exec app php artisan migrate

# Arrêter
docker compose -f docker-compose.dev.yml down
```

## Staging

### Configuration

```bash
# Utiliser le fichier docker-compose.yml
docker compose up -d
```

### Caractéristiques

- **Image Docker construite** : Pas de montage de code
- **Mode debug désactivé** : `APP_DEBUG=false`
- **Optimisations activées** : Cache de configuration, routes, vues
- **Données de test** : Seeders exécutés automatiquement

### Variables d'environnement

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.reconciliation-app.local
```

## Production

### Configuration

```bash
# Utiliser le fichier docker-compose.yml avec les variables de production
docker compose up -d
```

### Caractéristiques

- **Image Docker depuis GHCR** : `ghcr.io/<org>/reconciliation-app:latest`
- **Mode debug désactivé** : `APP_DEBUG=false`
- **Optimisations maximales** : OPcache, config cache, route cache
- **HTTPS activé** : `SESSION_SECURE_COOKIE=true`

### Variables d'environnement

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://reconciliation-app.local
SESSION_SECURE_COOKIE=true
```

### Checklist de déploiement

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` généré et sécurisé
- [ ] `DB_PASSWORD` fort et unique
- [ ] `SESSION_SECURE_COOKIE=true` (HTTPS)
- [ ] Worker de queue démarré
- [ ] Migrations exécutées
- [ ] Storage link créé
- [ ] Permissions correctes sur storage/
- [ ] Health check répond 200

## Variables d'environnement par environnement

| Variable | Development | Staging | Production |
|----------|-------------|---------|------------|
| `APP_ENV` | `local` | `staging` | `production` |
| `APP_DEBUG` | `true` | `false` | `false` |
| `APP_URL` | `http://localhost:8080` | `https://staging...` | `https://...` |
| `DB_CONNECTION` | `mysql` | `mysql` | `mysql` |
| `QUEUE_CONNECTION` | `database` | `database` | `database` |
| `CACHE_STORE` | `database` | `database` | `database` |
| `SESSION_DRIVER` | `database` | `database` | `database` |
| `MAIL_MAILER` | `log` | `smtp` | `smtp` |
| `SESSION_SECURE_COOKIE` | `null` | `true` | `true` |

## Bases de données

| Environnement | Base de données | Hôte |
|---------------|-----------------|------|
| Development | `reconciliation_app` | `mysql` (Docker) |
| Staging | `reconciliation_staging` | Serveur staging |
| Production | `reconciliation_prod` | Serveur production |

## Monitoring

### Health checks

```bash
# Vérifier la santé de l'application
curl http://localhost:8080/health

# Vérifier la santé de la base de données
docker compose exec mysql mysqladmin ping
```

### Logs

```bash
# Logs de l'application
docker compose logs -f app

# Logs de la base de données
docker compose logs -f mysql

# Logs du worker
docker compose logs -f queue-worker
```

## Sauvegarde et restauration

### Sauvegarde de la base de données

```bash
docker compose exec mysql mysqldump -u root -p reconciliation_app > backup.sql
```

### Restauration

```bash
docker compose exec -i mysql mysql -u root -p reconciliation_app < backup.sql
```

### Sauvegarde des fichiers

```bash
docker run --rm -v reconciliation-app-app-storage:/data -v $(pwd):/backup alpine tar czf /backup/storage-backup.tar.gz -C /data .
```
