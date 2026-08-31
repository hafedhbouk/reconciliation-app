# DevOps Documentation

## Table des matières

- [Docker](docker.md)
- [CI/CD](ci-cd.md)
- [Environnements](environments.md)
- [Secrets](secrets.md)
- [Déploiement](deployment.md)

---

## Vue d'ensemble

Cette documentation décrit l'infrastructure DevOps mise en place pour l'application **Rapprochement STEG**.

### Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              GitHub Repository                               │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           GitHub Actions (CI/CD)                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │    Lint     │  │    Tests    │  │    Build    │  │   Docker    │        │
│  │   (Pint)    │  │   (Pest)    │  │   (Vite)    │  │   Build     │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    GitHub Container Registry (ghcr.io)                       │
│                    ghcr.io/<org>/reconciliation-app                         │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Environnements de déploiement                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                        │
│  │ Development │  │   Staging   │  │ Production  │                        │
│  │  (docker-    │  │  (docker-   │  │  (docker-   │                        │
│  │ compose.dev) │  │  compose)   │  │  compose)   │                        │
│  └─────────────┘  └─────────────┘  └─────────────┘                        │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Services Docker

| Service | Description | Port |
|---------|-------------|------|
| `app` | Application Laravel (PHP-FPM + Nginx) | 8080 |
| `mysql` | Base de données MySQL 8.0 | 3306 |
| `queue-worker` | Worker pour les jobs asynchrones | - |
| `scheduler` | Planificateur de tâches Laravel | - |

### Pipelines CI/CD

| Workflow | Déclencheur | Actions |
|----------|-------------|---------|
| `ci.yml` | Push/PR sur main, develop | Lint, Tests, Build, Docker build |
| `docker.yml` | Push sur main, tags v* | Build & Push image Docker |
| `security.yml** | Push/PR, schedule | Audit dépendances, scan Trivy, détection secrets |
| `release.yml` | Tags v*.*.* | Création de release GitHub |

---

## Démarrage rapide

### Prérequis

- Docker Desktop 24+
- Docker Compose v2.20+
- Git 2.40+

### Installation

```bash
# Cloner le repository
git clone <repository-url>
cd reconciliation-app

# Copier les variables d'environnement
cp .env.example .env

# Démarrer les conteneurs Docker
docker compose up -d

# Vérifier que tout fonctionne
docker compose ps
curl http://localhost:8080/health
```

### Commandes utiles

```bash
# Voir les logs
docker compose logs -f app

# Exécuter une commande dans le conteneur
docker compose exec app php artisan migrate

# Reconstruire les images
docker compose build --no-cache

# Arrêter les conteneurs
docker compose down

# Arrêter et supprimer les volumes
docker compose down -v
```

---

## Configuration requise sur GitHub

### Secrets à configurer

Allez dans **Settings → Secrets and variables → Actions** et ajoutez :

| Secret | Description | Requis |
|--------|-------------|--------|
| `GITHUB_TOKEN` | Token automatique GitHub | ✅ Automatique |

### Variables d'environnement

Allez dans **Settings → Secrets and variables → Variables** :

| Variable | Description | Environnement |
|----------|-------------|---------------|
| `APP_ENV` | Environnement de l'application | staging, production |
| `APP_DEBUG` | Mode debug | false |
| `APP_KEY` | Clé de chiffrement Laravel | - |

### Branch protection

Configurez la protection de branche pour `main` :

1. Allez dans **Settings → Branches → Add rule**
2. Branch name pattern: `main`
3. Activez :
   - ✅ Require a pull request before merging
   - ✅ Require status checks to pass before merging
   - ✅ Require branches to be up to date before merging
   - ✅ Include administrators
4. Status checks requis:
   - `lint`
   - `tests`
   - `frontend`
   - `docker-build`

---

## Environnements

### Development (local)

```bash
docker compose -f docker-compose.dev.yml up -d
```

### Staging

```bash
# Déployer sur staging
docker compose -f docker-compose.yml -f docker-compose.staging.yml up -d
```

### Production

```bash
# Déployer en production
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

---

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

---

## Sécurité

### Bonnes pratiques appliquées

- ✅ Images Docker multi-stage (réduction de la surface d'attaque)
- ✅ Utilisateur non-root dans les conteneurs
- ✅ Variables d'environnement pour les secrets
- ✅ Scan de vulnérabilités avec Trivy
- ✅ Audit des dépendances (Composer, NPM)
- ✅ Détection de secrets avec GitLeaks
- ✅ Headers de sécurité (CSP, HSTS, X-Frame-Options)

### Scans de sécurité

Les scans sont exécutés automatiquement :
- À chaque push sur `main` et `develop`
- Chaque lundi à 00:00 UTC (schedule)
- À chaque Pull Request sur `main`

---

## Dépannage

### Problèmes courants

#### Les conteneurs ne démarrent pas

```bash
# Vérifier les logs
docker compose logs

# Vérifier la configuration
docker compose config

# Reconstruire les images
docker compose build --no-cache
```

#### Erreurs de permissions

```bash
# Réinitialiser les permissions
docker compose exec app chown -R www:www /var/www/html/storage
docker compose exec app chmod -R 755 /var/www/html/storage
```

#### Base de données non accessible

```bash
# Vérifier la santé de MySQL
docker compose exec mysql mysqladmin ping

# Se connecter à MySQL
docker compose exec mysql mysql -u root -p
```

---

## Ressources

- [Documentation Docker](https://docs.docker.com/)
- [Documentation GitHub Actions](https://docs.github.com/en/actions)
- [Documentation Laravel 12](https://laravel.com/docs/12.x)
- [Documentation Laravel Sail](https://laravel.com/docs/12.x/sail)
