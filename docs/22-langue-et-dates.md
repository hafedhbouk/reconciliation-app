# 22 — Interface en français et affichage des dates

Phase 2 des évolutions (octobre 2026).

## Langue

- Locale `fr` (`APP_LOCALE=fr`, valeur par défaut dans `config/app.php`) ;
  repli sur l'anglais (`APP_FALLBACK_LOCALE=en`) pour une clé manquante.
- Les écrans métier étaient déjà rédigés en français dans `__('…')` : ils
  n'ont pas été modifiés.
- Traductions ajoutées (système de traduction de Laravel, sans texte
  français dispersé dans les vues) :

| Fichier | Contenu |
|---|---|
| `lang/fr/validation.php` | toutes les règles de validation de Laravel 12, et les noms des champs (« fichier A », « adresse e-mail »…) |
| `lang/fr/auth.php`, `lang/fr/passwords.php`, `lang/fr/pagination.php` | connexion, mot de passe oublié, pagination |
| `lang/fr.json` | textes anglais des écrans Breeze (connexion, profil), des e-mails Laravel (réinitialisation, vérification), des pages d'erreur et de la pagination |

- **DataTables** : textes français définis une seule fois pour tous les
  tableaux (`resources/js/app.js`, `$.fn.dataTable.defaults`).
- Carbon en français (« il y a 5 minutes ») : `AppServiceProvider`.

## Dates et heures

- **Stockage inchangé** : la base reste en UTC (`app.timezone`). Aucune
  donnée n'a été convertie et les dates restent comparables en SQL.
- **Affichage** dans le fuseau `app.display_timezone`
  (`APP_DISPLAY_TIMEZONE`, par défaut `Africa/Tunis`, UTC+1 sans heure d'été).
  La conversion se fait à un seul endroit : `App\Support\DateDisplay`.

| Méthode | Pour | Exemple |
|---|---|---|
| `DateDisplay::dateTime()` / `@datetime(...)` | un instant (`created_at`, `matched_at`…) | `2026-10-22T19:50:08Z` → « 22/10/2026 à 20:50:08 » |
| `DateDisplay::dateOf()` | le jour d'un instant | `2026-06-30 23:30 UTC` → « 01/07/2026 » |
| `DateDisplay::date()` | une date calendaire (date d'opération) : **jamais décalée** | `2026-06-30` → « 30/06/2026 » |
| `DateDisplay::relative()` | une durée écoulée | « il y a 5 minutes » |

- Écrans et données concernés :
  - 16 dates dans les vues, la cloche et la page des notifications ;
  - les tableaux DataTables des imports, exceptions et résultats, qui
    affichaient la date ISO brute (`2026-10-22T19:50:08.000000Z`) ;
  - la date d'opération de la recherche, auparavant affichée en `Y-m-d` ;
  - le journal d'audit, les exports, le suivi des opérations et la
    notification d'export prêt.
- Les dates d'opération bancaires (`normalized_date`, `transaction_date`)
  restent des dates calendaires, sans conversion.

## Points d'exploitation

- Un terminal ouvert avant le changement peut encore exporter l'ancienne
  valeur `APP_LOCALE=en` (le profil du shell exporte les variables du `.env`) :
  rouvrir le terminal avant de relancer `php artisan serve` et `php artisan queue:work`.
- Recompiler le JavaScript (`npm run build`) pour les textes français de DataTables.
- `phpunit.xml` fixe `APP_LOCALE`, `APP_DISPLAY_TIMEZONE` et `SESSION_PATH`
  pour que les tests ne dépendent pas des variables du terminal (Git Bash
  transformait `SESSION_PATH=/` en chemin Windows).

## Tests

`tests/Feature/FrenchLocalizationTest.php` couvre :
- la conversion UTC → Tunis et le respect du fuseau configuré ;
- les dates calendaires non décalées ;
- le stockage resté en UTC et comparable ;
- la directive `@datetime` et les dates relatives ;
- l'écran de connexion et les messages de validation et d'authentification en français ;
- les dates DataTables formatées au lieu du format ISO.
