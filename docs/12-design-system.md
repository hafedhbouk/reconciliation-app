# 12. Design et UI

## Framework CSS

| Élément | Détail |
|---------|--------|
| **Framework** | Bootstrap 5.3 (SCSS source) |
| **Préprocesseur** | Sass |
| **Icônes** | Bootstrap Icons 1.13 |
| **Thème** | Light/Dark (data-bs-theme) |
| **Responsive** | Grille Bootstrap |

### Fichiers CSS

| Fichier | Rôle |
|---------|------|
| `resources/css/app.scss` | Point d'entrée SCSS principal |
| `node_modules/bootstrap/scss/bootstrap` | Source Bootstrap 5 |
| `node_modules/bootstrap-icons/font/bootstrap-icons.css` | Icônes Bootstrap |

### Compilation SCSS

```scss
// resources/css/app.scss
@import 'bootstrap/scss/bootstrap';
@import 'bootstrap-icons/font/bootstrap-icons.css';
@import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// Custom admin shell styles
.admin-sidebar { ... }
.admin-sidebar .nav-link { ... }
.admin-content { ... }
```

## Bibliothèque de composants

L'application utilise les **composants Blade** de Laravel comme bibliothèque de composants UI.

### Composants disponibles

| Catégorie | Composants |
|-----------|------------|
| **Formulaire** | `text-input`, `input-label`, `input-error`, `auth-session-status` |
| **Boutons** | `primary-button`, `secondary-button`, `danger-button` |
| **Layout** | `modal`, `application-logo` |
| **Admin** | `admin.sidebar`, `admin.topbar`, `admin.breadcrumb`, `admin.users.form`, `admin.banks.form`, `admin.sources.form`, `admin.roles.form`, `admin.matching-rules.form`, etc. |

## Layouts

### Layout invité

**Fichier :** `resources/views/layouts/guest.blade.php`

```
┌─────────────────────────────────────────────┐
│                                             │
│              [Logo STEG]                    │
│                                             │
│         ┌─────────────────────┐             │
│         │                     │             │
│         │    Contenu invité   │             │
│         │    (formulaires)    │             │
│         │                     │             │
│         └─────────────────────┘             │
│                                             │
└─────────────────────────────────────────────┘
```

### Layout principal (authentifié)

**Fichier :** `resources/views/layouts/app.blade.php`

```
┌─────────────────────────────────────────────────────────────────┐
│ [Logo] Rapprochement STEG    [Toggle thème] [Notifs] [User ▼]  │
├──────────┬──────────────────────────────────────────────────────┤
│          │                                                      │
│ Sidebar  │                    Contenu principal                 │
│          │                    (yield slot)                      │
│ ┌──────┐ │                                                      │
│ │Dashboard│                                                     │
│ ├──────┤ │                                                      │
│ │Recherche│                                                     │
│ ├──────┤ │                                                      │
│ │Imports  │                                                     │
│ ├──────┤ │                                                      │
│ │Rapprochement│                                                 │
│ │  Règles  │                                                     │
│ │  Résultats│                                                    │
│ │  Manuel  │                                                     │
│ │  Exceptions│                                                   │
│ ├──────┤ │                                                      │
│ │Paramétrage│                                                    │
│ │  Banques  │                                                    │
│ │  Sources  │                                                    │
│ │  Devises  │                                                    │
│ │  Jours fériés│                                                 │
│ │  Paramètres│                                                    │
│ ├──────┤ │                                                      │
│ │Admin    │                                                      │
│ │  Users   │                                                     │
│ │  Rôles   │                                                     │
│ ├──────┤ │                                                      │
│ │Suivi    │                                                      │
│ │  Audit   │                                                     │
│ └──────┘ │                                                      │
│          │                                                      │
└──────────┴──────────────────────────────────────────────────────┘
```

## Composants visuels récurrents

### Cards KPI (Dashboard)

**Fichier :** `resources/views/dashboard.blade.php`

```
┌─────────────────────────┐
│ [icon] Label            │
│         1,234           │
│    Description          │
└─────────────────────────┘
```

### Badges de statut

| Statut | Classe Bootstrap |
|--------|-----------------|
| `completed` | `bg-success` |
| `processing` | `bg-warning` |
| `pending` | `bg-secondary` |
| `failed` | `bg-danger` |
| `matched` | `bg-success` |
| `partial` | `bg-warning` |
| `conflict` | `bg-danger` |
| `open` | `bg-danger` |
| `resolved` | `bg-success` |

### DataTables

Les tableaux interactifs utilisent jQuery DataTables avec traitement serveur :

| Page | Fichier Vue | Colonnes principales |
|------|-------------|---------------------|
| Imports | `admin/imports/index.blade.php` | Source, fichier, statut, lignes, erreurs, durée |
| Matching Rules | `admin/matching-rules/index.blade.php` | Nom, sources, cardinalité, priorité, actif |
| Matching Results | `admin/matching-results/index.blade.php` | Règle, batch, statut, score, date |
| Exceptions | `admin/exceptions/index.blade.php` | Type, statut, transaction, assigné |
| Audit Logs | `admin/audit-logs/index.blade.php` | Date, utilisateur, événement, modèle |
| Users | `admin/users/index.blade.php` | Nom, email, rôles, statut |

### Graphiques

**Fichier :** `resources/views/dashboard.blade.php`

| Graphique | Type | Données |
|-----------|------|---------|
| Résultats matching | Doughnut | Counts par statut (matched/partial/conflict) |
| Exceptions par type | Doughnut | Counts par type (unmatched/amount/date/duplicate/orphan/conflict) |
| Volume par source | Bar | Counts par source (ALPHA/BNA/WEB/SMT) |
| Tendance 30 jours | Line | Transactions par jour |

### Modales

Le composant `x-modal` est utilisé pour :
- Confirmation de suppression
- Sélection format d'export

### Formulaires

Structure standard des formulaires :

```
┌─────────────────────────────────────────────┐
│ [Label]                                     │
│ ┌─────────────────────────────────────────┐ │
│ │ [Input text / select / file]            │ │
│ └─────────────────────────────────────────┘ │
│ [Error message if validation fails]         │
└─────────────────────────────────────────────┘
```

## Icônes

Les icônes proviennent de **Bootstrap Icons** (`bi-*`).

| Icône | Usage |
|-------|-------|
| `bi-speedometer2` | Dashboard |
| `bi-search` | Recherche |
| `bi-upload` | Imports |
| `bi-signpost-split` | Règles de rapprochement |
| `bi-link-45deg` | Résultats de rapprochement |
| `bi-hand-index-thumb` | Rapprochement manuel |
| `bi-exclamation-triangle` | Exceptions |
| `bi-bank` | Banques |
| `bi-diagram-3` | Sources |
| `bi-cash-coin` | Devises |
| `bi-calendar-event` | Jours fériés |
| `bi-sliders` | Paramètres |
| `bi-people` | Utilisateurs |
| `bi-shield-lock` | Rôles & Permissions |
| `bi-journal-text` | Journal d'audit |
| `bi-bell` | Notifications |
| `bi-moon` / `bi-sun` | Toggle thème |
| `bi-person` | Profil utilisateur |
| `bi-three-dots` | Menu actions |

## Thème sombre

**Fichier :** `resources/views/components/admin/topbar.blade.php`

Le thème est géré via l'attribut `data-bs-theme` sur `<html>` :

```javascript
// Toggle theme
document.documentElement.setAttribute('data-bs-theme', newTheme);
localStorage.setItem('theme', newTheme);
```

Le thème est persisté en `localStorage` et appliqué au chargement de la page.

## Responsive design

| Breakpoint | Usage |
|------------|-------|
| `< 576px` (xs) | Non testé explicitement |
| `>= 576px` (sm) | Sidebar masquée (à vérifier) |
| `>= 768px` (md) | Layout standard |
| `>= 992px` (lg) | Sidebar visible 260px |
| `>= 1200px` (xl) | Layout large |

## Typographie

| Élément | Style |
|---------|-------|
| Base | Bootstrap 5 default (system font stack) |
| Titres | `h1` à `h6` Bootstrap |
| Tableaux | DataTables default |
| Badges | Bootstrap badges |

## Spacing et grilles

L'application utilise le système de grille Bootstrap 12 colonnes :
- Container : `.container` ou `.container-fluid`
- Row : `.row`
- Cols : `.col-md-*`, `.col-lg-*`, etc.
- Gutters : `.g-*`
- Margins/Paddings : `.m-*`, `.p-*`

## Composants Bootstrap utilisés

| Composant | Usage |
|-----------|-------|
| **Navbar** | Topbar admin |
| **Nav + Sidebar** | Navigation latérale |
| **Cards** | KPI dashboard, formulaires |
| **Tables** | Listes CRUD |
| **Modals** | Confirmations, exports |
| **Forms** | Tous les formulaires |
| **Buttons** | Actions |
| **Badges** | Statuts |
| **Alerts** | Messages flash |
| **Dropdowns** | Menus utilisateur, notifications |
| **Tooltips** | Aide contextuelle |
| **Pagination** | Listes paginées |

## Accessibilité

| Aspect | État |
|--------|------|
| Attributs ARIA | Non systématiques |
| Contraste | Dépend du thème Bootstrap |
| Navigation clavier | Supportée par Bootstrap |
| Screen readers | Non testé explicitement |
| Labels de formulaire | Présents via `<x-input-label>` |
