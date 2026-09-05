# Architecture et décomposition en modules

## 1. Vue d’ensemble

L’application de rapprochement est structurée en couches :

- **Présentation** : interface admin Laravel/Blade/Bootstrap
- **Applicative** : 10 modules métier
- **Métier** : services de matching, d’import, de normalisation, de scoring
- **Données** : MySQL, jobs/queue, stockage fichiers

## 2. Décomposition en modules

### Module 1 — Import et gestion des sources
- Gestion des sources (ALPHA, BNA, WEB, SMT)
- Import CSV/XLSX avec mapping de colonnes
- Transformation et normalisation
- Suivi des imports et audit

### Module 2 — Normalisation des transactions
- Nettoyage et standardisation
- Détection/suppression des doublons
- Gestion des valeurs nulles
- Calcul des champs dérivés

### Module 3 — Règles de rapprochement
- Configuration par paire de sources
- Clé primaire simple / composite
- Champs de vérification secondaires
- Tolérances et statuts exclus
- Priorisation et activation/désactivation

### Module 4 — Rapprochement automatique
- Exécution séquentielle des règles
- Traitement par lots (`batch_reference`)
- Groupement par clé primaire
- Calcul du score de confiance
- Résultats : matched / partial / conflict / no_signal
- Traitement asynchrone via jobs

### Module 5 — Rapprochement manuel
- Recherche et filtrage des transactions non rapprochées
- Sélection bidirectionnelle A / B
- Création de rapprochements manuels
- Traçabilité opérateur

### Module 6 — Gestion des exceptions
- Détection automatique des anomalies
- Types : doublons, dépassement de tolérance, orphelins
- Workflow de revue (assignation, résolution, commentaires)
- Pièces jointes et suivi d’état

### Module 7 — Affichage des écarts
- Sélection de deux imports
- Tableau A sans correspondance dans B
- Tableau B sans correspondance dans A
- Filtres avancés

### Module 8 — Résultats et reporting
- Liste et détail des résultats
- Export CSV / Excel / PDF
- Génération asynchrone des exports volumineux

### Module 9 — Sécurité et audit
- Authentification et autorisations par rôles
- Journal d’audit
- Notifications
- Permissions fines par ressource

### Module 10 — Administration
- Gestion des banques, devises, sources
- Paramétrage des mappings
- Gestion des jours fériés
- Utilisateurs et rôles

## 3. Diagramme PlantUML

```plantuml
@startuml ReconciliationApp Modules

skinparam componentStyle rectangle
skinparam backgroundColor #FEFEFF
skinparam handwritten false
skinparam defaultTextAlignment center

title Décomposition en modules — Application de rapprochement

package "Présentation" {
    [Interface Admin\n(Laravel Blade + Bootstrap)] as UI
}

package "Couche applicative" {
    [Module d’import\net gestion des sources] as M1
    [Module de normalisation\ndes transactions] as M2
    [Module de règles\nde rapprochement] as M3
    [Module de rapprochement\nautomatique] as M4
    [Module de rapprochement\nmanuel] as M5
    [Module de gestion\ndes exceptions] as M6
    [Module d’affichage\ndes écarts] as M7
    [Module de résultats\net reporting] as M8
    [Module de sécurité\net audit] as M9
    [Module d’administration] as M10
}

package "Couche métier" {
    [Services de matching\nRuleMatcher] as S1
    [Services d’import\nImportService] as S2
    [Services de normalisation\nTransactionNormalizer] as S3
    [Scoring et\névaluation] as S4
}

package "Couche données" {
    [Base de données\nMySQL] as DB
    [Jobs & Queue\n(Background)] as Q
    [Stockage\nfichiers/export] as FS
}

UI --> M1
UI --> M2
UI --> M3
UI --> M4
UI --> M5
UI --> M6
UI --> M7
UI --> M8
UI --> M10

M1 --> S2
M2 --> S3
M3 --> S1
M4 --> S1
M5 --> S1
M6 --> S4
M7 --> S1
M8 --> S1

M9 --> M1
M9 --> M2
M9 --> M3
M9 --> M4
M9 --> M5
M9 --> M6
M9 --> M8

S1 --> DB
S2 --> DB
S3 --> DB
S4 --> DB

M4 --> Q
M8 --> Q
Q --> DB

M8 --> FS

@enduml
```

## 4. Règles de rapprochement par combinaison

| Combinaison | Clé primaire A | Clé primaire B | Vérification |
|-------------|----------------|----------------|--------------|
| ALPHA ↔ BNA | `num_autorisation` | `num_autorisation` | montant + date |
| ALPHA ↔ WEB | `reference` + `num_autorisation` | `reference` + `recu_paie` | montant + date |
| ALPHA ↔ SMT | `date|amount` | `date|amount` | — |
| SMT ↔ BNA | `date|amount` | `date|amount` | — |
| WEB ↔ BNA | `secondary_reference` | `num_autorisation` | montant + date |
| WEB ↔ SMT | `date|amount` | `date|amount` | — |
