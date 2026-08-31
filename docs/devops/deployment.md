# Guide de Déploiement

## Méthodes de déploiement

### 1. Déploiement avec Docker Compose (VPS/Serveur dédié)

#### Prérequis

- Serveur Linux (Ubuntu 22.04+ recommandé)
- Docker 24+
- Docker Compose v2.20+
- Accès SSH

#### Étapes

```bash
# 1. Cloner le repository
git clone <repository-url> /opt/reconciliation-app
cd /opt/reconciliation-app

# 2. Configurer les variables d'environnement
cp .env.example .env
nano .env  # Éditer les variables

# 3. Démarrer les conteneurs
docker compose up -d

# 4. Vérifier le déploiement
docker compose ps
curl http://localhost:8080/health
```

#### Avec HTTPS (Nginx reverse proxy)

```bash
# Utiliser un reverse proxy Nginx avec Let's Encrypt
# Voir docker-compose.prod.yml pour un exemple
```

### 2. Déploiement automatique via GitHub Actions

#### Configuration SSH

1. Générer une paire de clés SSH :
```bash
ssh-keygen -t ed25519 -C "deploy@reconciliation-app"
```

2. Ajouter la clé publique sur le serveur :
```bash
ssh-copy-id user@your-server
```

3. Ajouter la clé privée dans GitHub Secrets :
```
Settings → Secrets → New repository secret
Name: SSH_PRIVATE_KEY
Value: Contenu de ~/.ssh/id_ed25519
```

#### Workflow de déploiement

Créer `.github/workflows/deploy.yml` :

```yaml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.DEPLOY_HOST }}
          username: ${{ secrets.DEPLOY_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /opt/reconciliation-app
            git pull origin main
            docker compose pull
            docker compose up -d
```

### 3. Déploiement sur cloud (AWS/Azure/GCP)

#### AWS (ECS/Fargate)

1. Créer un repository ECR
2. Pousser l'image Docker
3. Configurer ECS avec Fargate
4. Configurer ALB (Application Load Balancer)

#### Azure (Container Instances)

1. Créer un Azure Container Registry
2. Pousser l'image Docker
3. Créer une instance de conteneur
4. Configurer le domaine personnalisé

#### GCP (Cloud Run)

1. Créer un Artifact Registry
2. Pousser l'image Docker
3. Déployer sur Cloud Run
4. Configurer le domaine personnalisé

## Checklist de déploiement

### Avant le déploiement

- [ ] Tests passent dans la CI
- [ ] Image Docker construite et poussée
- [ ] Variables d'environnement configurées
- [ ] Base de données accessible
- [ ] Certificat SSL installé (HTTPS)
- [ ] Backup de la base de données existante

### Pendant le déploiement

- [ ] Migrations exécutées
- [ ] Seeders exécutés (si nécessaire)
- [ ] Storage link créé
- [ ] Cache de configuration généré
- [ ] Worker de queue démarré

### Après le déploiement

- [ ] Health check répond 200
- [ ] Pages principales accessibles
- [ ] Logs sans erreurs
- [ ] Monitoring configuré
- [ ] Backup automatique configuré

## Rollback

### Rollback rapide

```bash
# Revenir à l'image précédente
docker compose down
docker tag ghcr.io/<org>/reconciliation-app:previous ghcr.io/<org>/reconciliation-app:latest
docker compose up -d
```

### Rollback de base de données

```bash
# Restaurer un backup
docker compose exec -i mysql mysql -u root -p reconciliation_app < backup.sql
```

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

### Métriques

- **Uptime** : Vérifier que l'application est accessible
- **Response time** : Temps de réponse < 500ms
- **Error rate** : Taux d'erreur < 1%
- **Memory usage** : Utilisation mémoire < 80%
- **CPU usage** : Utilisation CPU < 70%

## Sauvegarde et restauration

### Sauvegarde automatique

Configurer un cron job pour les sauvegardes :

```bash
# Crontab
0 2 * * * cd /opt/reconciliation-app && docker compose exec mysql mysqldump -u root -p$DB_ROOT_PASSWORD reconciliation_app > /backup/backup-$(date +\%Y\%m\%d).sql
```

### Restauration

```bash
# Restaurer un backup
docker compose exec -i mysql mysql -u root -p reconciliation_app < /backup/backup-20240101.sql
```

## Sécurité en production

### Headers de sécurité

Les headers suivants sont configurés dans Nginx :

```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; ...
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### Pare-feu

Configurer le pare-feu pour n'ouvrir que les ports nécessaires :

```bash
# UFW (Ubuntu)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable
```

### Mises à jour de sécurité

- Mettre à jour régulièrement les images Docker
- Appliquer les correctifs de sécurité du système d'exploitation
- Scanner régulièrement les vulnérabilités avec Trivy
