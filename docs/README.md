# Documentation Technique — Rapprochement STEG

> **Application :** Rapprochement STEG — Département Informatique
> **Version du code analysé :** 2026-08-31
> **Stack :** Laravel 12 / PHP 8.3 / MySQL 8 / Bootstrap 5 / Blade
> **Base de code :** `C:\laragon\www\reconciliation-app`

---

## Table des matières

| # | Fichier | Sujet |
|---|---------|-------|
| 01 | [01-overview.md](01-overview.md) | Résumé général de l'application |
| 02 | [02-tech-stack.md](02-tech-stack.md) | Stack technique |
| 03 | [03-project-structure.md](03-project-structure.md) | Structure du repository |
| 04 | [04-architecture.md](04-architecture.md) | Architecture globale |
| 05 | [05-frontend.md](05-frontend.md) | Documentation frontend |
| 06 | [06-pages-navigation.md](06-pages-navigation.md) | Pages et navigation |
| 07 | [07-backend.md](07-backend.md) | Architecture backend |
| 08 | [08-api.md](08-api.md) | API et endpoints |
| 09 | [09-database.md](09-database.md) | Modèle de données |
| 10 | [10-authentication-roles.md](10-authentication-roles.md) | Authentification, rôles et permissions |
| 11 | [11-workflows.md](11-workflows.md) | Workflows métier principaux |
| 12 | [12-design-system.md](12-design-system.md) | Design et UI |
| 13 | [13-integrations.md](13-integrations.md) | Intégrations externes |
| 14 | [14-configuration-deployment.md](14-configuration-deployment.md) | Configuration et déploiement |
| 15 | [15-testing.md](15-testing.md) | Tests |
| 16 | [16-security.md](16-security.md) | Sécurité |
| 17 | [17-technical-debt.md](17-technical-debt.md) | Dette technique |
| 18 | [18-developer-onboarding.md](18-developer-onboarding.md) | Guide d'onboarding |
| 19 | [19-glossary.md](19-glossary.md) | Glossaire |
| 20 | [20-system-map.md](20-system-map.md) | Carte finale du système |

### Fichiers complémentaires

| Fichier | Sujet |
|---------|-------|
| [ERD.md](ERD.md) | Diagramme entité-relation détaillé |
| [SEQUENCES.md](SEQUENCES.md) | Diagrammes de séquence des workflows |
| [ENDPOINTS.md](ENDPOINTS.md) | Référentiel complet des endpoints |

---

## Résumé exécutif

**Rapprochement STEG** est une application web de rapprochement bancaire (reconciliation) permettant d'importer, normaliser et faire correspondre automatiquement des fichiers de paiement provenant de quatre sources distinctes : **ALPHA**, **BNA**, **WEB / STEG** et **SMT**.

L'application est construite sur **Laravel 12** avec un frontend **Blade + Bootstrap 5** (pas de SPA). Elle utilise un système de **files d'attente (queues)** pour le traitement asynchrone des imports et du matching, et expose un tableau de bord avec KPIs et graphiques.

---

## Pour commencer rapidement

1. [Vue d'ensemble](01-overview.md) — Comprendre le but de l'application
2. [Architecture](04-architecture.md) — Comprendre la structure technique
3. [Base de données](09-database.md) — Comprendre le modèle de données
4. [Workflows](11-workflows.md) — Comprendre les parcours métier
5. [Guide d'onboarding](18-developer-onboarding.md) — Commencer à développer
