# Gestion des Secrets

## Principes

1. **Jamais de secrets dans le code source**
2. **Jamais de secrets dans les fichiers de configuration versionnés**
3. **Utiliser des variables d'environnement ou des gestionnaires de secrets**
4. **Rotation régulière des secrets**

## Secrets requis

### Application

| Secret | Description | Comment le générer |
|--------|-------------|-------------------|
| `APP_KEY` | Clé de chiffrement Laravel | `php artisan key:generate` |
| `DB_PASSWORD` | Mot de passe base de données | Mot de passe fort aléatoire |
| `DB_ROOT_PASSWORD` | Mot de passe root MySQL | Mot de passe fort aléatoire |

### Services externes (optionnels)

| Secret | Description | Usage |
|--------|-------------|-------|
| `MAIL_PASSWORD` | Mot de passe SMTP | Envoi d'emails |
| `REDIS_PASSWORD` | Mot de passe Redis | Cache/Queue |
| `AWS_SECRET_ACCESS_KEY` | Clé secrète AWS | Stockage S3 |

### CI/CD

| Secret | Description | Configuration |
|--------|-------------|---------------|
| `GITHUB_TOKEN` | Token GitHub Actions | Automatique, fourni par GitHub |

## Configuration des secrets GitHub

### Ajouter un secret

1. Allez sur votre repository GitHub
2. **Settings → Secrets and variables → Actions**
3. **New repository secret**
4. Nommez le secret et ajoutez sa valeur

### Secrets recommandés

```
Settings → Secrets and variables → Actions → New repository secret
```

| Nom | Valeur | Usage |
|-----|--------|-------|
| `DB_PASSWORD` | Mot de passe fort | Base de données |
| `DB_ROOT_PASSWORD` | Mot de passe fort | Root MySQL |

## Configuration locale

### Fichier .env

Le fichier `.env` contient les secrets locaux et **ne doit jamais être versionné**.

```bash
# Générer une nouvelle clé
php artisan key:generate

# Créer le fichier .env
cp .env.example .env
```

### Exemple de .env sécurisé

```env
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
DB_PASSWORD=your-secure-password-here
DB_ROOT_PASSWORD=your-secure-root-password-here
```

## Gestion des secrets Docker

### Variables d'environnement

Les secrets sont passés via des variables d'environnement dans `docker-compose.yml` :

```yaml
services:
  app:
    environment:
      - DB_PASSWORD=${DB_PASSWORD}
```

### Fichier .env pour Docker

Créez un fichier `.env` à la racine du projet (versionné en `.env.example`) :

```env
# Database
DB_DATABASE=reconciliation_app
DB_USERNAME=reconciliation
DB_PASSWORD=your-secure-password
DB_ROOT_PASSWORD=your-secure-root-password

# Application
APP_KEY=base64:your-app-key
APP_PORT=8080
```

## Bonnes pratiques

### Génération de mots de passe forts

```bash
# Générer un mot de passe aléatoire de 32 caractères
openssl rand -base64 32

# Générer un mot de passe aléatoire de 16 caractères
openssl rand -hex 16
```

### Rotation des secrets

1. **APP_KEY** : La uniquement en cas de compromission
   ```bash
   php artisan key:generate --force
   ```

2. **DB_PASSWORD** : La périodiquement (tous les 90 jours recommandé)
   ```sql
   ALTER USER 'reconciliation'@'%' IDENTIFIED BY 'new-password';
   ```

3. **DB_ROOT_PASSWORD** : La périodiquement
   ```sql
   ALTER USER 'root'@'%' IDENTIFIED BY 'new-password';
   ```

### Audit des secrets

Vérifiez régulièrement qu'aucun secret n'a été commité :

```bash
# Installer GitLeaks
brew install gitleaks

# Scanner le repository
gitleaks detect --source . --verbose
```

## Sécurité des secrets dans GitHub Actions

### Permissions minimales

Les workflows GitHub Actions utilisent les permissions minimales :

```yaml
permissions:
  contents: read
  packages: write  # Uniquement pour le push d'images
```

### Protection des branches

Activez la protection des branches pour empêcher les push directs :

1. **Settings → Branches → Add rule**
2. **Branch name pattern:** `main`
3. **Protection rules:**
   - ✅ Require a pull request before merging
   - ✅ Require status checks to pass
   - ✅ Include administrators

### Environments

Pour les environnements de staging et production, utilisez GitHub Environments :

1. **Settings → Environments → New environment**
2. Nommez l'environnement (`staging`, `production`)
3. Configurez les règles de protection :
   - Required reviewers
   - Wait timer
   - Deployment secrets

## Détection de secrets

Le workflow `security.yml` inclut GitLeaks pour détecter les secrets dans le code :

```yaml
- name: Run GitLeaks
  uses: gitleaks/gitleaks-action@v2
```

### Prévention des fuites

Ajoutez un pre-commit hook :

```bash
# .git/hooks/pre-commit
#!/bin/sh
gitleaks protect --staged --verbose
```

## Checklist de sécurité

- [ ] `.env` dans `.gitignore`
- [ ] `.env.example` sans valeurs sensibles
- [ ] Secrets GitHub configurés
- [ ] Permissions GitHub Actions minimales
- [ ] Branch protection activée
- [ ] GitLeaks configuré
- [ ] Rotation des secrets planifiée
