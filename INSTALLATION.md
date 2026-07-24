# Guide d'installation

Ce guide décrit pas à pas l'installation locale de **Reconciliation App**, une
plateforme de rapprochement bancaire et de paiements construite avec
Laravel 12 / PHP 8.3 / MySQL 8.

## 1. Prérequis

Avant de commencer, assurez-vous de disposer des éléments suivants :

- **PHP 8.2+** (8.3 recommandé) avec les extensions suivantes activées :
  `bcmath`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `pdo_mysql`, `zip`,
  `openssl`, `sodium`
- **Composer 2**
- **MySQL 8+** (un serveur accessible avec un utilisateur disposant des
  droits de création de base de données)
- **Node.js 20+** et **npm 10+** (pour la compilation des assets front-end)
- **Git**

> Astuce Windows : un environnement comme [Laragon](https://laragon.org/)
> ou [XAMPP](https://www.apachefriends.org/) fournit PHP, MySQL et les
> extensions nécessaires en une seule installation.

Vérifiez vos versions installées :

```bash
php -v
composer -V
mysql --version
node -v
npm -v
```

## 2. Cloner le dépôt

```bash
git clone https://github.com/hafedhbouk/reconciliation-app.git
cd reconciliation-app
```

## 3. Installer les dépendances PHP

```bash
composer install
```

## 4. Configurer l'environnement

Copiez le fichier d'exemple `.env.example` vers `.env` puis générez la clé
d'application :

```bash
cp .env.example .env
php artisan key:generate
```

Ouvrez ensuite le fichier `.env` et renseignez les informations de connexion
à votre base de données MySQL :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=reconciliation_app
DB_USERNAME=root
DB_PASSWORD=
```

> Le fichier `.env.example` utilise `sqlite` par défaut pour simplifier les
> tests rapides, mais l'application est documentée et validée pour
> **MySQL 8**. Créez au préalable la base de données correspondante
> (`CREATE DATABASE reconciliation_app;`).

## 5. Exécuter les migrations et les seeders

```bash
php artisan migrate --seed
```

Cette étape crée le schéma complet et alimente la base avec :

- un utilisateur **super-admin** : `admin@reconciliation.local` /
  `password` (⚠️ à changer immédiatement en dehors d'un environnement
  purement local) ;
- les rôles `admin`, `auditor` et `operator` ;
- des données de référence : banques, devises, sources (ALPHA, BNA, WEB,
  SMT actives, STEG inactive/non vérifiée), mappings de colonnes par
  source, règles de rapprochement, paramètres par défaut.

## 6. Installer et compiler les assets front-end

```bash
npm install
npm run build
```

Pour le développement avec rechargement à chaud, utilisez plutôt :

```bash
npm run dev
```

## 7. Démarrer l'application

### Option A — tout lancer en une commande

```bash
composer run dev
```

Cette commande démarre en parallèle le serveur PHP, le worker de file
d'attente, les logs (`pail`) et Vite.

### Option B — lancer chaque service manuellement

```bash
php artisan serve          # serveur web (http://127.0.0.1:8000)
php artisan queue:work      # traitement des imports/rapprochements en file d'attente
npm run dev                 # compilation des assets (si besoin en développement)
```

> Les imports et les exécutions de rapprochement sont des jobs mis en file
> d'attente : **le worker `queue:work` est indispensable** pour qu'ils
> s'exécutent.

En production, augmentez la limite mémoire du worker :

```bash
php artisan queue:work --memory=1024
```

## 8. Se connecter

Rendez-vous sur `http://127.0.0.1:8000` (ou l'URL configurée dans
`APP_URL`) et connectez-vous avec le compte super-admin créé par le
seeder :

- **Email** : `admin@reconciliation.local`
- **Mot de passe** : `password`

## 9. Lancer les tests (optionnel)

```bash
php artisan test
```

La suite compte 223 tests Pest couvrant les CRUD/policies, le moteur de
mapping/transformation, le moteur de rapprochement, les exports, les
notifications, les en-têtes de sécurité, le rate limiting et la politique
de mots de passe.

## 10. Résolution de problèmes courants

| Problème | Solution |
|---|---|
| `SQLSTATE[HY000] [2002] Connection refused` | Vérifiez que MySQL est démarré et que `DB_HOST`/`DB_PORT` dans `.env` sont corrects. |
| `Class "PDO" not found` ou erreur `pdo_mysql` | Activez l'extension PHP `pdo_mysql` dans `php.ini`. |
| Assets non chargés / erreurs Vite au démarrage | Exécutez `npm install` puis `npm run build` (ou `npm run dev`). |
| Les imports/rapprochements restent bloqués en "en cours" | Le worker `php artisan queue:work` n'est pas lancé ou a planté (vérifiez la mémoire allouée). |
| `No application encryption key has been specified` | Exécutez `php artisan key:generate`. |

## 11. Aller plus loin

- [`README.md`](README.md) — présentation générale, architecture en 5
  phases, rôles et permissions.
- [`docs/ERD.md`](docs/ERD.md) — schéma entité-relation complet.
- [`docs/SEQUENCES.md`](docs/SEQUENCES.md) — diagrammes de séquence des
  trois workflows principaux.
- [`docs/ENDPOINTS.md`](docs/ENDPOINTS.md) — liste des endpoints AJAX/export.

## 12. Checklist de déploiement en production

- `APP_ENV=production`, `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=true` (une fois servi en HTTPS)
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`
  (ou simplement `php artisan optimize`)
- Worker de file d'attente lancé avec `--memory=1024` ou plus
- Un vrai driver mail si des notifications par e-mail sont ajoutées
  (`MAIL_MAILER=log` par défaut, aucun SMTP n'est configuré)
