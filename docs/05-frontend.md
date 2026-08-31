# 5. Documentation frontend

## Caractéristiques générales

L'application utilise un **rendu serveur Blade** avec des **interactions côté client** via jQuery, Bootstrap 5, DataTables et Chart.js. Il ne s'agit **pas** d'une SPA.

| Caractéristique | Détail |
|-----------------|--------|
| Moteur de template | Blade (Laravel 12) |
| Framework CSS | Bootstrap 5.3 (SCSS) |
| Icônes | Bootstrap Icons 1.13 |
| Bibliothèque JS | jQuery 4.0 |
| Tableaux interactifs | DataTables (serveur) |
| Graphiques | Chart.js 4.5 |
| Client HTTP | Axios (via bootstrap.js) |
| Bundler | Vite 7 |

## Point d'entrée

| Fichier | Rôle |
|---------|------|
| `public/index.php` | Point d'entrée HTTP Laravel |
| `bootstrap/app.php` | Configuration application Laravel 12 |
| `resources/js/app.js` | Bundle JS principal |
| `resources/css/app.scss` | Bundle CSS principal |
| `vite.config.js` | Configuration build Vite |

## Initialisation JavaScript

**Fichier :** `resources/js/app.js`

```javascript
import './bootstrap';           // Axios + CSRF
import * as bootstrap from 'bootstrap';  // Bootstrap JS
window.bootstrap = bootstrap;

import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

import DataTable from 'datatables.net-bs5';

import { Chart, ... } from 'chart.js';
window.Chart = Chart;
Chart.register(...registerables);

// Initialisation tooltips Bootstrap
document.addEventListener('DOMContentLoaded', () => {
    [...document.querySelectorAll('[data-bs-toggle="tooltip"]')]
        .map(el => new bootstrap.Tooltip(el));
});
```

**Fichier :** `resources/js/bootstrap.js`

```javascript
import axios from 'axios';
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XHR';
```

## Layouts

### Layout invité

**Fichier :** `resources/views/layouts/guest.blade.php`

Utilisé pour les pages d'authentification (login, register, forgot-password).

### Layout principal (authentifié)

**Fichier :** `resources/views/layouts/app.blade.php`

Structure :
```
┌─────────────────────────────────────────────────────────────────┐
│                        [Topbar Notifications + User]             │
├──────────┬──────────────────────────────────────────────────────┤
│          │                                                      │
│ Sidebar  │                    Contenu principal                 │
│ (admin)  │                    (yield content)                  │
│          │                                                      │
└──────────┴──────────────────────────────────────────────────────┘
```

## Composants Blade principaux

### Composants globaux

| Composant | Fichier | Usage |
|-----------|---------|-------|
| `x-app-layout` | `components/app.blade.php` | Layout principal avec sidebar + topbar |
| `x-guest-layout` | `components/guest-layout.blade.php` | Layout invité centré |
| `x-text-input` | `components/text-input.blade.php` | Champ texte stylisé |
| `x-input-label` | `components/input-label.blade.php` | Label de formulaire |
| `x-input-error` | `components/input-error.blade.php` | Message d'erreur validation |
| `x-primary-button` | `components/primary-button.blade.php` | Bouton principal |
| `x-secondary-button` | `components/secondary-button.blade.php` | Bouton secondaire |
| `x-danger-button` | `components/danger-button.blade.php` | Bouton danger |
| `x-modal` | `components/modal.blade.php` | Modale Bootstrap |
| `x-auth-session-status` | `components/auth-session-status.blade.php` | Statut de session flash |

### Composants admin

| Composant | Fichier | Usage |
|-----------|---------|-------|
| `x-admin.sidebar` | `components/admin/sidebar.blade.php` | Barre latérale admin |
| `x-admin.topbar` | `components/admin/topbar.blade.php` | Barre supérieure |
| `x-admin.breadcrumb` | `components/admin/breadcrumb.blade.php` | Fil d'Ariane |
| `x-admin.users.form` | `components/admin/users/form.blade.php` | Formulaire utilisateur |
| `x-admin.banks.form` | `components/admin/banks/form.blade.php` | Formulaire banque |
| `x-admin.sources.form` | `components/admin/sources/form.blade.php` | Formulaire source |
| `x-admin.roles.form` | `components/admin/roles/form.blade.php` | Formulaire rôle |
| `x-admin.matching-rules.form` | `components/admin/matching-rules/form.blade.php` | Formulaire règle matching |

## Gestion de l'état frontend

L'application **ne utilise pas de store centralisé** (Vuex, Redux, Pinia, etc.). L'état est géré de plusieurs manières :

1. **État serveur** : Les données sont passées directement aux vues Blade via `compact()` ou `with()` depuis les contrôleurs
2. **État DataTables** : Chaque tableau DataTables gère son propre état côté client via le plugin jQuery
3. **État localStorage** : Le thème (dark/light) est persisté en localStorage via JS dans `components/admin/topbar.blade.php`
4. **Flash sessions** : Les messages flash (succès, erreur) utilisent `session()->flash()` Laravel
5. **Query parameters** : Les filtres sont passés en query string et lus côté serveur

## Appels API / AJAX

### DataTables serveur

Les tableaux DataTables communiquent avec le backend via des endpoints JSON spécifiques :

| Page | Route de données | Contrôleur |
|------|------------------|------------|
| Imports | `GET /admin/imports/data` | `ImportController@data` |
| Matching Rules | `GET /admin/matching-rules/data` | `MatchingRuleController@data` |
| Matching Results | `GET /admin/matching-results/data` | `MatchingResultController@data` |
| Exceptions | `GET /admin/exceptions/data` | `ExceptionController@data` |
| Search | `GET /admin/search/data` | `SearchController@data` |
| Audit Logs | `GET /admin/audit-logs/data` | `AuditLogController@data` |
| Users | `GET /admin/users/data` | `UserController@data` |

### AJAX custom

**Fichier :** `admin/reconciliation/index.blade.php`

Le rapprochement manuel utilise des appels AJAX personnalisés :
- `GET /admin/reconciliation/search` — recherche de transactions non appariées

## Gestion des permissions frontend

Les directives Blade de Laravel contrôlent l'affichage :

```blade
@can('imports.create')
    <a href="{{ route('admin.imports.create') }}">Importer</a>
@endcan

@canany(['banks.create', 'banks.edit'])
    <!-- Contenu visible si l'une des permissions -->
@endcanany

@cannot('users.delete')
    <!-- Contenu masqué -->
@endcannot
```

## Gestion des notifications frontend

**Fichier :** `resources/views/components/admin/topbar.blade.php`

- Compteur de notifications non lues
- Menu déroulant avec les 8 dernières notifications
- Bouton "Marquer tout comme lu" → `POST /notifications/read-all`
- Bouton "Voir tout" → `GET /notifications`

## Gestion des fichiers frontend

### Upload

Les formulaires d'upload utilisent :
- `enctype="multipart/form-data"`
- Input `<input type="file">`
- Validation côté serveur via `StoreImportRequest`

### Téléchargement

Les liens de téléchargement sont générés via :
- `route('admin.exceptions.attachments.download', [...])`
- `route('admin.matching-results.exports.download', [...])`

## Internationalisation

| Aspect | Détail |
|--------|--------|
| Langue interface | Français (libellés en clair dans les vues) |
| Configuration Laravel | `APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en` |
| Traduction | Utilisation de `__()` dans certaines vues |
| Direction | LTR (pas de support RTL) |

## Thème et mode sombre

**Fichier :** `components/admin/topbar.blade.php`

```javascript
// Toggle dark/light mode
const toggle = document.getElementById('theme-toggle');
toggle.addEventListener('click', () => {
    const theme = document.documentElement.getAttribute('data-bs-theme');
    const newTheme = theme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('theme', newTheme);
});
```

## Responsive design

L'application utilise le système de grille Bootstrap 5 :
- Breakpoints : `xs`, `sm`, `md`, `lg`, `xl`, `xxl`
- Sidebar : Fixe 260px sur desktop, non masquée sur mobile (à vérifier)
- Tableaux : DataTables responsive avec pagination

## Gestion des erreurs frontend

Les erreurs sont affichées via :
- **Erreurs de validation** : Composant `<x-input-error>` affichant les erreurs Laravel
- **Messages flash** : Composant `<x-auth-session-status>` pour `status` et `error`
- **Modales de confirmation** : Pour les actions destructives (suppression)
- **Alertes inline** : Pour les erreurs d'import, jobs échoués, etc.

## Performance frontend

| Aspect | Implémentation |
|--------|----------------|
| Bundling | Vite (ESM) |
| CSS | SCSS compilé, Bootstrap 5 (tree-shakeable) |
| JS | Bundle unique avec jQuery, DataTables, Chart.js |
| Images | Logo STEG statique |
| Caching | Cache HTTP navigateur (non configuré explicitement) |
| Chargement | DataTables en serveur (pas de tout charger côté client) |
