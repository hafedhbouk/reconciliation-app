# 19. Glossaire métier et technique

## Termes métier

| Terme | Définition |
|-------|------------|
| **Rapprochement bancaire** | Processus de comparaison et d'appariement de transactions financières entre différentes sources (banques, systèmes) pour identifier les correspondances et les écarts |
| **Source** | Origine des données de paiement. L'application gère 4 sources : ALPHA, BNA, WEB/STEG, SMT |
| **Import** | Opération de chargement d'un fichier de paiement (CSV/XLSX) dans le système |
| **Transaction** | Enregistrement d'un paiement importé depuis un fichier source |
| **Transaction normalisée** | Version unifiée d'une transaction avec des champs standardisés (reference, amount, date, dedup_hash) |
| **Matching / Rapprochement** | Processus d'appariement automatique de transactions entre deux sources |
| **Règle de matching** | Configuration définissant comment deux sources doivent être comparées (clé primaire, tolérances, cardinalité) |
| **Exception** | Anomalie détectée lors du matching (montant différent, date différente, doublon, orphelin, conflit) |
| **Millime** | Sous-unité monétaire : 1 TND (dinar tunisien) = 1000 millimes. Tous les montants sont stockés en millimes |
| **num_autorisation** | Numéro d'autorisation bancaire, utilisé comme clé de rapprochement principal |
| **recu_paie** | Numéro de reçu de paiement, utilisé pour le rapprochement WEB-BNA |
| **dedup_hash** | Hash de déduplication calculé sur les champs clés d'une transaction pour détecter les doublons |
| **Batch** | Lot de traitement. Les règles de matching peuvent être exécutées en batch ("Lancer tout") |
| **Batch reference** | Identifiant unique d'un lot de matching, regroupant les résultats d'une exécution |
| **Cardinalité** | Type de relation entre les groupes de transactions : 1:1, 1:N, N:1, N:N |
| **Tolérance** | Marge d'acceptation pour le matching (écart de montant en millimes, écart de date en jours) |
| **Score de confiance** | Pourcentage (0-100%) indiquant la fiabilité d'un appariement (100% = exact, 85% = partiel) |

## Acronymes

| Acronyme | Signification | Description |
|----------|---------------|-------------|
| **STEG** | Société Tunisienne de l'Électricité et du Gaz | Client de l'application |
| **ALPHA** | Source ALPHA | Banque/source de paiement ALPHA |
| **BNA** | Banque Nationale Agricole | Banque/source de paiement BNA |
| **WEB / STEG** | Source WEB (alias STEG) | Paiements en ligne STEG (anciennement séparé en WEB et STEG) |
| **SMT** | Source SMT | Système de Monétique Tunisienne (paiement carte) |
| **CSV** | Comma-Separated Values | Format de fichier texte |
| **XLSX** | Excel Open XML Spreadsheet | Format de fichier Excel |
| **PDF** | Portable Document Format | Format de document |
| **RBAC** | Role-Based Access Control | Contrôle d'accès basé sur les rôles |
| **CRUD** | Create, Read, Update, Delete | Opérations de base sur les données |
| **DTO** | Data Transfer Object | Objet de transfert de données |
| **ORM** | Object-Relational Mapping | Mapping objet-relationnel (Eloquent) |
| **CSP** | Content Security Policy | Politique de sécurité du contenu |
| **CSRF** | Cross-Site Request Forgery | Falsification de requête intersites |
| **XSS** | Cross-Site Scripting | Scripting intersites |
| **HSTS** | HTTP Strict Transport Security | Sécurité stricte de transport HTTP |

## Termes techniques Laravel

| Terme | Définition dans ce projet |
|-------|---------------------------|
| **Blade** | Moteur de template Laravel utilisé pour toutes les vues |
| **Eloquent** | ORM Laravel utilisé pour tous les modèles |
| **Migration** | Fichier de création/modification du schéma de base de données |
| **Seeder** | Fichier de peuplement initial de la base de données |
| **Factory** | Générateur de données de test (peu utilisé ici) |
| **Job** | Tâche asynchrone exécutée par le worker de queue |
| **Notification** | Notification stockée en base de données pour l'utilisateur |
| **Policy** | Classe d'autorisation d'accès par modèle |
| **Middleware** | Filtre de requête HTTP (sécurité, auth, throttle) |
| **Form Request** | Classe de validation des données entrantes |
| **Service Provider** | Fournisseur de services Laravel (enregistrement bindings) |
| **Contract** | Interface PHP (TransformPrimitive, ImportRowReader) |
| **Trait** | Trait PHP réutilisable (HasUserstamps, Auditable) |
| **Enum** | Énumération PHP 8.1+ (ImportStatus, MatchingStatus, etc.) |
| **Artisan** | CLI Laravel (commandes, migrations, etc.) |
| **Vite** | Bundler frontend (compilation SCSS/JS) |
| **Composer** | Gestionnaire de dépendances PHP |
| **NPM** | Gestionnaire de paquets Node.js |

## Termes spécifiques à l'application

| Terme | Définition |
|-------|------------|
| **Mapping** | Configuration associant une colonne d'un fichier source à un champ interne avec des transformations |
| **Transform** | Primitive de transformation appliquée à une valeur (trim, zero_pad, date_parse, etc.) |
| **Reader** | Lecteur de fichier (CSV ou XLSX) qui extrait les en-têtes et les lignes |
| **Normalizer** | Service qui convertit une transaction brute en transaction normalisée |
| **RuleMatcher** | Service qui exécute une règle de rapprochement |
| **DuplicateDetector** | Service qui détecte les transactions en double |
| **UnmatchedSweeper** | Service qui crée des exceptions pour les transactions non appariées |
| **ConfidenceScorer** | Service qui calcule le score de confiance d'un appariement |
| **DashboardMetricsService** | Service qui agrège les KPIs du tableau de bord |
| **AuditLogService** | Service qui écrit les entrées du journal d'audit |
| **SettingsService** | Service qui lit/écrit les paramètres de l'application |
| **SourceColumnMapping** | Modèle de mapping des colonnes d'une source |
| **MatchingRule** | Modèle de règle de rapprochement |
| **MatchingResult** | Modèle de résultat de matching |
| **MatchingDetail** | Modèle de détail (transaction appariée dans un résultat) |
| **ExceptionRecord** | Modèle d'exception/anomalie |
| **ExceptionAttachment** | Modèle de pièce jointe d'une exception |
| **MatchingExport** | Modèle d'export asynchrone de résultats |
| **ImportProcessedNotification** | Notification de fin d'import |
| **MatchingActionCompletedNotification** | Notification d'action de matching terminée |
| **MatchingExportReadyNotification** | Notification d'export prêt |

## Sources de données détaillées

### ALPHA

| Propriété | Valeur |
|-----------|--------|
| **Code** | `ALPHA` |
| **Type fichier** | `xlsx` |
| **Clé primaire** | `num_autorisation` (NUM_AUTO, b/B stripped, zero-padded 6) |
| **Référence** | `reference` (REFERENCE, zero-padded 9) |
| **Montant** | `amount` (MONTANT_ENCAISS, fixed_width_millimes) |
| **Date** | `date` (DAT_ENC, format d/m/Y) |
| **Canal** | `canal` (CANAL) |

### BNA

| Propriété | Valeur |
|-----------|--------|
| **Code** | `BNA` |
| **Type fichier** | `csv` |
| **Clé primaire** | `num_autorisation` (N° autorisation, zero-padded 6) |
| **Montant** | `amount` (Montant, decimal_string_to_millimes, 3 decimals) |
| **Date** | `date` (Date) |
| **Type transaction** | `status_raw` (Type de la transaction) |

### WEB / STEG

| Propriété | Valeur |
|-----------|--------|
| **Code** | `WEB` (affiché "WEB / STEG") |
| **Type fichier** | `csv` |
| **Référence** | `reference` (reference, right_chars 9) |
| **Référence secondaire** | `secondary_reference` (recu_paie, b/B stripped, zero-padded 6) |
| **Montant** | `amount` (montant, fixed_width_millimes) |
| **Date** | `date` (date_paiement, format Y-m-d H:i:s) |
| **Session** | `session` (session, trim) — champ non-core, stocké dans raw_payload |

### SMT

| Propriété | Valeur |
|-----------|--------|
| **Code** | `SMT` |
| **Type fichier** | `csv` (séparateur `;`) |
| **Clé de matching** | Composite `date\|amount` (généré par TransactionNormalizer) |
| **Montant** | `amount` (Montant, decimal_string_to_millimes, 3 decimals) |
| **Date** | `date` (New Deposit date, format Y.m.d H:i:s) |

## Règles de matching détaillées

### ALPHA-BNA

| Propriété | Valeur |
|-----------|--------|
| **Source A** | ALPHA |
| **Source B** | BNA |
| **Clé primaire** | `num_autorisation` |
| **Vérification** | `amount` + `date` |
| **Tolérance** | Configurable (amount_millimes, days) |

### SMT-BNA

| Propriété | Valeur |
|-----------|--------|
| **Source A** | SMT |
| **Source B** | BNA |
| **Clé primaire** | Composite `date\|amount` |
| **Vérification** | Aucune (clé composite suffit) |

### WEB-BNA

| Propriété | Valeur |
|-----------|--------|
| **Source A** | WEB |
| **Source B** | BNA |
| **Clé primaire** | `secondary_reference` (recu_paie) vs `num_autorisation` |
| **Vérification** | Aucune |

### ALPHA-WEB

| Propriété | Valeur |
|-----------|--------|
| **Source A** | ALPHA |
| **Source B** | WEB |
| **Clé primaire** | `reference` |
| **Vérification** | `num_autorisation` vs `secondary_reference` |

### ALPHA-SMT

| Propriété | Valeur |
|-----------|--------|
| **Source A** | ALPHA |
| **Source B** | SMT |
| **Clé primaire** | Composite `date\|amount` |
| **Vérification** | Aucune |

### WEB-SMT

| Propriété | Valeur |
|-----------|--------|
| **Source A** | WEB |
| **Source B** | SMT |
| **Clé primaire** | Composite `date\|amount` |
| **Vérification** | Aucune |
