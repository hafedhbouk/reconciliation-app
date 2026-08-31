# Rapport de Justification du Projet de Fin d'Étude

## Master Informatique — Spécialité Développement des Systèmes et Réseaux

---

**Projet :** Rapprochement STEG — Application de Rapprochement Bancaire Automatisé

**Contexte :** Projet de fin d'étude pour l'obtention du diplôme de Master en Informatique, spécialité Développement des Systèmes et Réseaux.

**Date :** Août 2026

---

## Table des matières

1. [Introduction](#1-introduction)
2. [Contexte et Problématique](#2-contexte-et-problématique)
3. [Pertinence par rapport à la spécialité](#3-pertinence-par-rapport-à-la-spécialité)
4. [Architecture Technique et Démonstration des Compétences](#4-architecture-technique-et-démonstration-des-compétences)
5. [Complexité et Défis Techniques](#5-complexité-et-défis-techniques)
6. [Aspects Systèmes et Réseaux](#6-aspects-systèmes-et-réseaux)
7. [Qualité Logicielle et Bonnes Pratiques](#7-qualité-logicielle-et-bonnes-pratiques)
8. [Impact et Valeur Ajoutée](#8-impact-et-valeur-ajoutée)
9. [Conclusion](#9-conclusion)

---

## 1. Introduction

Le présent document justifie la pertinence du projet **"Rapprochement STEG"** en tant que projet de fin d'étude pour le diplôme de **Master en Informatique, spécialité Développement des Systèmes et Réseaux**.

Cette application constitue une solution complète de **rapprochement bancaire automatisé** destinée au Département Informatique de la Société Tunisienne de l'Électricité et du Gaz (STEG). Elle permet l'import, la normalisation et l'appariement automatique de transactions financières provenant de quatre sources bancaires hétérogènes.

Ce rapport démontre que ce projet satisfait pleinement les exigences académiques et professionnelles d'un master en Développement des Systèmes et Réseaux, en couvrant l'ensemble des compétences attendues : conception logicielle, architecture système, gestion de bases de données, traitement asynchrone, sécurité, et déploiement.

---

## 2. Contexte et Problématique

### 2.1 Contexte Métier

La STEG, en tant que grand organisme public tunisien, gère des flux financiers importants transitant par plusieurs canaux bancaires :

- **ALPHA** : Banque ALPHA
- **BNA** : Banque Nationale Agricole
- **WEB / STEG** : Paiements en ligne
- **SMT** : Système de Monétique Tunisienne

Chacune de ces sources produit des fichiers de formats différents (CSV, XLSX) avec des conventions de nommage, des structures de colonnes et des formats de données hétérogènes.

### 2.2 Problématique

Le rapprochement bancaire manuel pose plusieurs problèmes majeurs :

| Problème | Impact |
|----------|--------|
| **Hétérogénéité des formats** | Difficulté de comparaison entre sources |
| **Volume de données** | Traitement manuel fastidieux et source d'erreurs |
| **Absence de traçabilité** | Impossibilité d'auditer les décisions |
| **Délais de traitement** | Rapprochement tardif impactant la trésorerie |
| **Risque d'erreurs** | Pertes financières potentielles |

### 2.3 Objectifs du Projet

1. **Automatiser** l'import et la normalisation de formats hétérogènes
2. **Implémenter** un moteur de rapprochement configurable basé sur des règles métier
3. **Fournir** un tableau de bord de suivi avec KPIs et visualisations
4. **Garantir** la traçabilité complète via un journal d'audit
5. **Permettre** la gestion collaborative des exceptions

---

## 3. Pertinence par rapport à la spécialité

### 3.1 Alignement avec le Référentiel de Compétences

La spécialité **Développement des Systèmes et Réseaux** couvre les compétences suivantes, toutes démontrées dans ce projet :

| Compétence du Référentiel | Démontrée dans le Projet |
|---------------------------|-------------------------|
| **Conception et développement d'applications** | Architecture MVC complète avec Laravel 12 |
| **Gestion de bases de données** | Modèle relationnel complexe (18+ tables), migrations, optimisation |
| **Architecture logicielle** | Patterns MVC, Service Layer, Repository, Observer, Strategy |
| **Traitement asynchrone** | Système de jobs et queues pour le traitement différé |
| **Sécurité des systèmes** | RBAC, policies, headers sécurité, protection CSRF/XSS |
| **Gestion de projet** | Méthodologie agile, tests complets, documentation |
| **Intégration de systèmes** | Interfaçage avec 4 sources bancaires hétérogènes |

### 3.2 Dimension "Systèmes"

Le projet démontre une maîtrise des aspects **systèmes** à travers :

- **Architecture monolithe modulaire** : Séparation claire des couches (présentation, métier, données)
- **Traitement par lots (batch processing)** : Jobs asynchrones pour le traitement de gros volumes
- **Gestion des files d'attente** : Système de queues avec Laravel (driver database, extensible à Redis)
- **Stockage et persistance** : Gestion des fichiers uploadés, pièces jointes, exports
- **Cache et performance** : Mise en cache des KPIs, optimisation des requêtes

### 3.3 Dimension "Réseaux"

Le projet intègre les aspects **réseaux** à travers :

- **Communication HTTP/HTTPS** : Application web accessible via protocole HTTP
- **Architecture Client-Serveur** : Frontend (navigateur) ↔ Backend (serveur Laravel)
- **API REST interne** : Endpoints JSON pour DataTables (pattern RESTful)
- **Sécurité réseau** : Headers CORS, HSTS, Content Security Policy
- **Sessions distribuées** : Stockage des sessions en base de données (scalabilité horizontale)

---

## 4. Architecture Technique et Démonstration des Compétences

### 4.1 Stack Technologique

| Technologie | Justification Académique |
|-------------|-------------------------|
| **Laravel 12** | Framework PHP moderne, démontrant la maîtrise d'un framework MVC professionnel |
| **PHP 8.3** | Utilisation des types avancés, enums, match expressions, fibers |
| **MySQL 8** | SGBD relationnel avancé, démontrant la modélisation de données complexes |
| **Bootstrap 5** | Framework CSS moderne, responsive design |
| **jQuery + DataTables** | Traitement serveur de données volumineuses |
| **Chart.js** | Visualisation de données |
| **Maatwebsite/Excel** | Intégration de librairies tierces pour l'import/export |
| **DomPDF** | Génération de documents PDF |
| **Spatie Permission** | Système RBAC professionnel |

### 4.2 Architecture Logicielle

L'application suit une **architecture en couches** démontrant la maîtrise des patterns de conception :

```
┌─────────────────────────────────────────────────────────────────┐
│                    COUCHE PRÉSENTATION                          │
│              Blade Templates + jQuery + Bootstrap               │
├─────────────────────────────────────────────────────────────────┤
│                    COUCLE CONTRÔLE                              │
│         Contrôleurs + Form Requests + Middleware                │
├─────────────────────────────────────────────────────────────────┤
│                    COUCLE MÉTIER                                │
│    Services (MappingEngine, RuleMatcher, etc.) + Jobs           │
├─────────────────────────────────────────────────────────────────┤
│                    COUCLE DONNÉES                               │
│         Modèles Eloquent + Migrations + Seeders                 │
├─────────────────────────────────────────────────────────────────┤
│                    COUCLE INFRASTRUCTURE                        │
│         Base de données + Filesystem + Cache + Queue            │
└─────────────────────────────────────────────────────────────────┘
```

### 4.3 Patterns de Conception Utilisés

| Pattern | Implémentation | Justification |
|---------|----------------|---------------|
| **MVC** | Structure Laravel classique | Séparation des responsabilités |
| **Service Layer** | `app/Services/` | Logique métier réutilisable et testable |
| **Repository** | `SettingsRepositoryInterface` | Abstraction de l'accès aux données |
| **Observer** | `AuditObserver` | Réaction aux événements modèle |
| **Strategy** | `TransformPrimitive` + implementations | Transformations interchangeables |
| **Factory** | `ImportRowReaderFactory` | Création de lecteurs selon le type |
| **DTO** | `BankData`, `MatchingRunSummary`, etc. | Transfert de données typé |
| **Job/Queue** | `ProcessImportJob`, etc. | Traitement asynchrone |

---

## 5. Complexité et Défis Techniques

### 5.1 Complexité du Moteur de Rapprochement

Le cœur du système est un **moteur de matching** qui doit :

1. **Gérer l'hétérogénéité** : 4 sources avec des formats et clés de matching différents
2. **Supporter 6 règles de matching** avec des cardinalités variées (1:1, 1:N, N:1, N:N)
3. **Appliquer des tolérances** configurables (montant en millimes, date en jours)
4. **Calculer un score de confiance** (100% exact, 85% partiel)
5. **Détecter les conflits** et créer des exceptions typées
6. **Être idempotent** : re-exécution sans duplication

### 5.2 Complexité de la Normalisation

Le système de normalisation doit :

- **Transformer** des formats hétérogènes (dates d/m/Y, Y-m-d, Y.m.d, etc.)
- **Convertir** les montants en millimes (unité commune)
- **Calculer** des hashs de déduplication
- **Gérer** les préfixes (B/b à supprimer), le zero-padding, les décimales

### 5.3 Complexité du Traitement Asynchrone

Le système de jobs doit :

- **Isoler les erreurs** par ligne (une ligne en erreur ne bloque pas l'import)
- **Traiter par chunks** pour gérer de gros volumes
- **Notifier** l'utilisateur à la fin du traitement
- **Supporter les batches** (chaînes de jobs avec Laravel Batch)

### 5.4 Défis de Sécurité

| Défi | Solution Implémentée |
|------|---------------------|
| Authentification | Laravel Breeze + sessions en base |
| Autorisation | RBAC avec Spatie Permission + Policies |
| Protection XSS | Échappement Blade + CSP |
| Protection CSRF | Token Laravel + middleware |
| Upload sécurisé | Validation type MIME, stockage hashé |
| Audit | Journal d'audit complet avec observer |

---

## 6. Aspects Systèmes et Réseaux

### 6.1 Architecture Client-Serveur

L'application suit une architecture **client-serveur classique** avec :

- **Client** : Navigateur web (HTML/CSS/JS)
- **Serveur d'application** : PHP-FPM + Laravel
- **Serveur de base de données** : MySQL 8
- **Protocole** : HTTP/HTTPS

### 6.2 Traitement Asynchrone et Files d'Attente

Le système de **queues** démontre la maîtrise des concepts de traitement distribué :

```
┌─────────────────────────────────────────────────────────────────┐
│                         PRODUCTEUR                              │
│              Contrôleur → dispatch(new Job())                   │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                         FILE D'ATTENTE                          │
│                    Table `jobs` (MySQL)                         │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                         CONSOMMATEUR                            │
│              Worker PHP → php artisan queue:work                │
└─────────────────────────────────────────────────────────────────┘
```

### 6.3 Gestion des Sessions

Les sessions sont stockées en **base de données**, permettant :

- La **scalabilité horizontale** (plusieurs serveurs d'application)
- La **persistance** des sessions après redémarrage
- L'**inspection** et l'administration des sessions actives

### 6.4 Stockage et Persistance

| Type de données | Stockage | Justification |
|-----------------|----------|---------------|
| Données structurées | MySQL 8 | Intégrité relationnelle, requêtes complexes |
| Fichiers importés | Système de fichiers local | Performance, simplicité |
| Sessions | Base de données | Scalabilité |
| Cache | Base de données | Configuration simple |
| Files d'attente | Base de données | Pas de dépendance externe |

### 6.5 Sécurité Réseau

| Mesure | Implémentation |
|--------|----------------|
| HSTS | Header `Strict-Transport-Security` |
| CSP | Content Security Policy configurée |
| X-Frame-Options | `DENY` (protection clickjacking) |
| X-Content-Type-Options | `nosniff` |
| Sessions sécurisées | HttpOnly, SameSite=Lax |

---

## 7. Qualité Logicielle et Bonnes Pratiques

### 7.1 Tests

| Métrique | Valeur |
|----------|--------|
| **Nombre de tests** | 238 tests |
| **Assertions** | 821 assertions |
| **Couverture fonctionnelle** | Tous les modules CRUD |
| **Tests d'intégration** | Import réel, matching, notifications |
| **Tests unitaires** | Transforms, services, enums |
| **Tests de sécurité** | Headers, RBAC, rate limiting |
| **Tests de performance** | Budget requêtes (N+1 guard) |

### 7.2 Structure du Code

| Bonne Pratique | Implémentation |
|----------------|----------------|
| **Séparation des responsabilités** | Controllers → Services → Models |
| **Validation centralisée** | Form Requests dédiés |
| **Typage strict** | Enums PHP, DTOs, types scalaires |
| **Documentation** | PHPDoc, commentaires métier |
| **Nommage cohérent** | Conventions Laravel respectées |

### 7.3 Gestion de Projet

| Aspect | Implémentation |
|--------|----------------|
| **Contrôle de version** | Git |
| **Environnements** | local, testing, production |
| **Configuration** | Variables d'environnement (.env) |
| **Migrations** | Versioning de la base de données |
| **Seeders** | Données de démonstration |

### 7.4 Documentation

Le projet inclut une **documentation technique complète** (20 fichiers Markdown) couvrant :

- Architecture globale et détaillée
- Modèle de données (ERD)
- API et endpoints
- Workflows métier (diagrammes de séquence)
- Guide d'onboarding développeur
- Glossaire métier et technique

---

## 8. Impact et Valeur Ajoutée

### 8.1 Impact sur l'Organisation (STEG)

| Avant | Après |
|-------|-------|
| Rapprochement manuel (jours) | Rapprochement automatique (minutes) |
| Erreurs fréquentes | Détection automatique des anomalies |
| Pas de traçabilité | Journal d'audit complet |
| Pas de visibilité | Tableau de bord temps réel |

### 8.2 Compétences Démontrées pour le Marché

Ce projet démontre la maîtrise des compétences suivantes, recherchées dans l'industrie :

| Compétence | Niveau de Maîtrise |
|------------|-------------------|
| **Laravel/PHP** | Avancé (architecture, patterns, queues) |
| **Base de données** | Avancé (modélisation, optimisation, migrations) |
| **Frontend** | Intermédiaire (Bootstrap, jQuery, Chart.js) |
| **Sécurité** | Avancé (RBAC, headers, protection OWASP) |
| **Architecture logicielle** | Avancé (patterns, couches, services) |
| **Tests** | Avancé (Pest, intégration, unitaires) |
| **DevOps** | Intermédiaire (configuration, déploiement) |

### 8.3 Potentiel d'Évolution

Le projet est conçu pour être **extensible** :

| Évolution | Faisabilité |
|-----------|-------------|
| Ajout de nouvelles sources | ✅ Mappings configurables |
| API REST externe | ✅ Architecture prête |
| Scalabilité horizontale | ✅ Sessions/Cache/Queue en base |
| Intégration LDAP/SSO | ✅ Spatie Permission compatible |
| Notifications email | ✅ Configuration SMTP |
| Stockage cloud (S3) | ✅ Disk configuré |
| Monitoring (Telescope/Pulse) | ✅ Compatible Laravel |

---

## 9. Conclusion

### 9.1 Synthèse

Le projet **Rapprochement STEG** constitue un excellent projet de fin d'étude pour les raisons suivantes :

1. **Pertinence académique** : Couvre l'ensemble des compétences du référentiel Master Informatique — Développement des Systèmes et Réseaux.

2. **Complexité technique** : Architecture multi-couches, moteur de matching algorithmique, traitement asynchrone, intégration de systèmes hétérogènes.

3. **Qualité logicielle** : 238 tests, patterns de conception, documentation complète, bonnes pratiques.

4. **Valeur métier** : Répond à un besoin réel d'une grande organisation, avec mesurable impact sur la productivité.

5. **Dimension systèmes** : Architecture client-serveur, files d'attente, sessions distribuées, stockage.

6. **Dimension réseaux** : Communication HTTP, sécurité réseau, headers, scalabilité.

### 9.2 Argumentaire Final

> Ce projet démontre qu'un développeur master est capable de :
>
> - **Analyser** un problème métier complexe (rapprochement bancaire multi-sources)
> - **Concevoir** une solution logicielle robuste et extensible
> - **Implémenter** une application complète en utilisant les technologies modernes
> - **Garantir** la qualité par des tests complets et des bonnes pratiques
> - **Documenter** et **communiquer** sur son travail
> - **Sécuriser** une application web selon les standards de l'industrie
>
> Ces compétences sont exactement celles attendues d'un titulaire d'un Master en Informatique, spécialité Développement des Systèmes et Réseaux.

### 9.3 Recommandation

Nous recommandons ce projet pour la soutenance de Master, avec la mention **"Très Bien"** justifiée par :

- L'excellence de la documentation technique
- La couverture de tests (238 tests, 821 assertions)
- La complexité algorithmique du moteur de matching
- La qualité de l'architecture logicielle
- L'impact réel sur l'organisation partenaire

---

**Rédigé par :** [Nom de l'étudiant]

**Encadré par :** [Nom de l'encadrant]

**Date :** Août 2026

---

## Annexes

### Annexe A : Statistiques du Projet

| Métrique | Valeur |
|----------|--------|
| Lignes de code PHP | ~15,000+ |
| Lignes de code Blade | ~5,000+ |
| Nombre de modèles | 18 |
| Nombre de contrôleurs | 20+ |
| Nombre de services | 15+ |
| Nombre de jobs | 6 |
| Nombre de tests | 238 |
| Nombre de fichiers de test | 47 |
| Nombre de migrations | 25+ |
| Nombre de seeders | 9 |
| Nombre de policies | 11 |
| Nombre d'enums | 10 |
| Nombre de transforms | 8 |

### Annexe B : Technologies Utilisées

| Catégorie | Technologies |
|-----------|--------------|
| Backend | Laravel 12, PHP 8.3, MySQL 8 |
| Frontend | Bootstrap 5, jQuery 4, Chart.js 4, DataTables |
| Outils | Composer, NPM, Vite, Git |
| Tests | Pest 3, PHPUnit 11 |
| Librairies | Spatie Permission, Maatwebsite/Excel, DomPDF |

### Annexe C : Références

- Laravel 12 Documentation : https://laravel.com/docs/12.x
- Spatie Permission : https://spatie.be/docs/laravel-permission
- OWASP Security Headers : https://owasp.org/www-project-secure-headers/
- Pest PHP : https://pestphp.com/
