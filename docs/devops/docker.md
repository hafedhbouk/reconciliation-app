# Docker Guide

## Structure des fichiers Docker

```
docker/
├── php/
│   ├── php.ini-production    # Configuration PHP pour production
│   └── opcache.ini           # Configuration OPcache
├── nginx/
│   └── default.conf          # Configuration Nginx
├── supervisor/
│   └── supervisord.conf      # Configuration Supervisor (process manager)
└── mysql/
    └── my.cnf                # Configuration MySQL personnalisée
```

## Dockerfile

Le Dockerfile utilise un **build multi-stage** pour optimiser la taille de l'image finale :

### Stage 1: `frontend-build`
- Image de base: `node:20-alpine`
- Installe les dépendances NPM
- Compile les assets frontend avec Vite

### Stage 2: `vendor-build`
- Image de base: `composer:2`
- Installe les dépendances Composer (sans dev)

### Stage 3: `production`
- Image de base: `php:8.3-fpm-alpine`
- Installe les extensions PHP nécessaires
- Configure PHP, Nginx et Supervisor
- Copie les artefacts des stages précédents
- Crée un utilisateur non-root (`www`)
- Configure les health checks

## Configuration des services

### PHP-FPM

Configuration optimisée pour la production :
- OPcache activé avec preloading
- Memory limit: 512M
- Upload max: 50M
- Sessions sécurisées

### Nginx

Configuration de sécurité :
- Headers de sécurité (CSP, HSTS, X-Frame-Options)
- Compression Gzip
- Cache des assets statiques
- Blocage des fichiers sensibles

### Supervisor

Gestion des processus :
- PHP-FPM
- Nginx

## Utilisation

### Construction de l'image

```bash
docker compose build
```

### Démarrage des services

```bash
docker compose up -d
```

### Vérification

```bash
# Vérifier que tous les services sont en cours d'exécution
docker compose ps

# Tester l'application
curl http://localhost:8080/health
```

### Logs

```bash
# Tous les logs
docker compose logs -f

# Logs d'un service spécifique
docker compose logs -f app
docker compose logs -f mysql
docker compose logs -f queue-worker
```

### Exécution de commandes

```bash
# Artisan commands
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# Shell
docker compose exec app sh

# Composer
docker compose exec app composer install

# NPM
docker compose exec app npm run dev
```

## Environnements

### Développement

Le fichier `docker-compose.dev.yml` est configuré pour le développement :
- Montage du code source en volume (hot reload)
- Mode debug activé
- Mailer en mode log

```bash
docker compose -f docker-compose.dev.yml up -d
```

### Production

Le fichier `docker-compose.yml` est optimisé pour la production :
- Image Docker construite
- Mode debug désactivé
- Optimisations PHP (OPcache, config cache)

```bash
docker compose up -d
```

## Health Checks

Chaque service dispose d'un health check :

| Service | Health check |
|---------|--------------|
| `app` | Vérifie que l'application répond sur /health |
| `mysql` | `mysqladmin ping` |
| `queue-worker` | Vérifie que le processus queue:work est actif |

## Optimisations

### Cache Docker

Le build utilise le cache GitHub Actions (`type=gha`) pour accélérer les builds CI/CD.

### Taille de l'image

La taille de l'image est minimisée par :
- L'utilisation d'images Alpine Linux
- La suppression des dépendances de développement
- La suppression des fichiers inutiles
- L'utilisation de `.dockerignore`

### Sécurité

- Utilisateur non-root (`www`)
- Pas de secrets dans l'image
- Configuration PHP sécurisée (disable_functions, expose_php=Off)
- Headers de sécurité Nginx

## Dépannage

### Les conteneurs ne démarrent pas

```bash
# Vérifier les logs
docker compose logs app

# Vérifier les health checks
docker compose ps --format "table {{.Name}}\t{{.Status}}"
```

### Erreurs de permissions

```bash
# Réinitialiser les permissions
docker compose exec app chown -R www:www /var/www/html/storage
docker compose exec app chmod -R 755 /var/www/html/storage
```

### Problèmes de connexion à la base de données

```bash
# Vérifier que MySQL est prêt
docker compose exec mysql mysqladmin ping

# Se connecter à MySQL
docker compose exec mysql mysql -u reconciliation -p
```

### Reconstruire les images

```bash
# Reconstruction complète
docker compose build --no-cache

# Reconstruction d'un service spécifique
docker compose build --no-cache app
```
