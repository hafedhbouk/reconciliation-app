# 21 — Suivi réel des opérations longues

Phase 1 des évolutions (octobre 2026). Les traitements longs affichent leur
avancement **réel**, mis à jour sans recharger la page.

## Opérations suivies

| Type (`operations.type`) | Job | Total utilisé pour le pourcentage | Avancement |
|---|---|---|---|
| `import` | `ProcessImportJob` | lignes de données du fichier (`ImportRowReader::countRows`) | lignes enregistrées, acceptées, rejetées (après chaque paquet de 500) |
| `matching` | `RunAdHocMatchingJob`, `RunMatchingRuleJob` (dont « Lancer tout ») | lignes des deux fichiers, ou nombre de clés communes (règle globale) | lignes ou clés traitées |
| `comparison` | `ComputeUnmatchedJob` (« Écarts par source ») | lignes des deux fichiers | lignes classées |
| `export` | `GenerateMatchingExportJob` (résultats, écarts) | lignes à exporter | lignes écrites, puis étape « Écriture du fichier » sans pourcentage |
| `search_export` | `GenerateSearchExportJob` | lignes à exporter | lignes écrites |

La recherche à l'écran n'est pas suivie : elle est paginée côté serveur et
répond immédiatement. Son export, lui, l'est.

## États

`pending` (En attente) → `running` (En cours) → `completed` (Terminé) ou
`failed` (Échec). L'annulation n'est pas proposée : aucun job ne sait
s'interrompre proprement.

- L'opération est créée « En attente » **au clic**, dans le contrôleur, puis
  son identifiant est transmis au job (`$operationId`, dernier argument).
  Un job lancé sans écran (chaîne « Lancer tout », commande, test) crée sa propre opération.
- Une opération en échec n'est jamais repassée en « Terminé ».
- Sans nouvelle d'un job en cours depuis 5 minutes, l'écran l'indique :
  le worker est peut-être arrêté. Aucun échec n'est inventé.

## Valeurs réelles uniquement

- Pourcentage = `processed / total`, plafonné à 99 % tant que ce n'est pas terminé.
- Total inconnu ou étape non mesurable (`indeterminate`) : **aucun pourcentage**.
  L'écran affiche le nombre traité et un indicateur d'activité.
- Pour une comparaison, la préparation des clés (table temporaire SQL)
  précède la première ligne traitée : l'opération reste à 0 % pendant ces
  quelques secondes, ce qui reflète le travail réellement fait.

## Architecture

- **Table `operations`** (migration additive `2026_10_23_000001_create_operations_table`).
- **`App\Services\OperationTracker`** (singleton) : `create`, `resolve`, `start`,
  `stage`, `advance`, `counting`, `finish`, `fail`, `failById`.
  - `advance()` n'écrit qu'environ une fois par seconde.
  - Les écritures passent par une **connexion séparée** (`operations_tracking`,
    copie de la connexion principale). Le rapprochement et la comparaison
    tournent dans une seule transaction SQL ; sans elle, l'avancement serait
    invisible jusqu'à la fin. Avec SQLite (tests), la connexion principale est conservée.
- **`RuleMatcher::trackWith($operation)`** : le moteur fixe le vrai total et
  fait avancer le compteur.
- **`App\Policies\OperationPolicy`** : chacun voit ses opérations ; le rôle
  `admin` voit tout (le `super-admin` passe par `Gate::before`).
- **Routes** :
  - `GET /admin/operations` : page « Mes opérations » (`?all=1` pour un administrateur) ;
  - `GET /admin/operations/data` : liste en JSON ;
  - `GET /admin/operations/{uuid}` : une opération en JSON.
- **Frontend** : `resources/js/operations.js`. Polling toutes les 2 s, seulement
  tant qu'une opération est active, et suspendu quand l'onglet est masqué.
  - Composant Blade `<x-operation-progress :operation="$operation" :reload="true" />`.
  - Intégré à la fiche d'un import, à « Écarts par source », aux deux pages
    d'exports et à la barre du haut (pastille).

**Choix du polling** plutôt que SSE ou WebSockets : l'interface est en Blade,
la queue est en base de données et le serveur est un serveur PHP simple. SSE
bloquerait un processus PHP par onglet ; Reverb ajouterait un démon pour un
gain négligeable avec un rafraîchissement de 2 secondes.

## Exploitation

```bash
php artisan migrate                 # crée la table operations
npm run build                       # recompile le JavaScript
php artisan queue:work --memory=1024
```

- Après chaque déploiement, **redémarrer le worker** : il garde en mémoire
  le code chargé à son démarrage.
- Avec un seul worker, les opérations s'exécutent l'une après l'autre (les
  suivantes restent « En attente »). Pour en traiter plusieurs en parallèle,
  lancer plusieurs `queue:work`. Deux comparaisons sur un même fichier restent
  sérialisées par un verrou, pour la cohérence des résultats.
- Les heures sont affichées à l'heure de Tunis, alors que la base reste en UTC
  (voir [22-langue-et-dates.md](22-langue-et-dates.md)).

## Tests

`tests/Feature/OperationTrackingTest.php` couvre :
- le calcul du pourcentage ;
- le cycle de vie et l'échec qui n'est jamais transformé en succès ;
- la détection d'absence de nouvelles et le choix de la connexion séparée ;
- l'import (succès, colonnes manquantes), le rapprochement, la comparaison
  (succès, échec), l'export (succès, échec) ;
- la création « En attente » au clic ;
- les droits (propriétaire, autre utilisateur, administrateur) ;
- les opérations simultanées et la page après rechargement.

Vérifié aussi sur MySQL avec les fichiers de juillet 2026 : comparaison
ALPHA ↔ BNA de 160 073 lignes, avancement relu depuis une autre connexion
pendant la transaction (0 → 21 047 → 67 961 → … → 160 073), terminée en 57 s.
