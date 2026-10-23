# 23 — Tableau de bord analytique

Phase 3 bis des évolutions (octobre 2026).

## Ce qui a changé sur le tableau de bord

- La carte « Taux de rapprochement » a été retirée : le taux est désormais
  calculé avec les filtres choisis, dans la section « Analyse du rapprochement ».
- Nouvelle section **Analyse du rapprochement**, visible avec la permission
  `matching-results.viewAny` :
  - **filtres** : année, période (du… au…), sources, statuts ;
  - **indicateurs** : lignes, montant total, lignes rapprochées (rapprochées
    + partielles), non rapprochées, conflits, taux de rapprochement, taux
    d'anomalie (conflits / lignes) ;
  - **graphiques** : évolution par statut (par jour si la période fait
    3 mois au plus, sinon par mois), répartition par statut, statuts par source ;
  - **tableau croisé dynamique** : regroupement des lignes et des colonnes au
    choix (année, mois, jour, source, statut, canal, fichier importé, ou
    « Aucune » colonne), mesure « nombre de lignes » ou « montant (TND) »,
    affichage en valeurs, en % du total général ou en % de la ligne, avec
    totaux de lignes et de colonnes.
- Nouvelle section **Opérations** (Phase 1) : nombre d'opérations, en cours,
  terminées, échecs, taux de réussite, détail par type avec durée moyenne,
  et les 5 derniers échecs avec leur message.
  - Un administrateur voit toutes les opérations et peut filtrer par
    utilisateur ;
  - les autres utilisateurs ne voient que **leurs propres** opérations
    (le filtre utilisateur est ignoré côté serveur).
- Sans la permission d'analyse, la page garde les graphiques d'origine
  « Volume par source » et « Tendance » et n'affiche que « Mes opérations ».

## Fonctionnement

| Élément | Fichier |
|---|---|
| Calculs du rapprochement | `app/Services/Analytics/ReconciliationAnalytics.php` |
| Statistiques des opérations | `app/Services/Analytics/OperationAnalytics.php` |
| Point d'accès JSON | `GET /dashboard/analytics` (`dashboard.analytics`), `DashboardController@analytics` |
| Page | `resources/views/dashboard.blade.php` |
| Interface (filtres, graphiques, tableau croisé) | `resources/js/dashboard.js` |

### Données utilisées

- Uniquement les lignes réellement importées (`normalized_transactions`),
  des imports **non supprimés**. Aucune valeur n'est estimée ni simulée.
- Date utilisée : la date d'opération (`normalized_date`), date calendaire
  non décalée par le fuseau horaire (voir doc 22).
- Les dates des opérations, elles, sont des instants : la période choisie est
  comprise en heure de Tunis, puis convertie en UTC pour la requête.

### Performance : le « cube » d'agrégats

Relire les 320 000 lignes à chaque changement de filtre prenait environ
22 secondes (jointure avec `transactions`, dont la colonne JSON
`raw_payload` est volumineuse). Le service fait donc **une seule**
agrégation SQL par jour × source × statut × canal × fichier (environ
380 lignes sur les données de juillet 2026), puis applique tous les filtres
et regroupements en PHP sur ces agrégats.

- Les sommes restent exactes : les agrégats sont additionnés, jamais
  moyennés ni arrondis.
- Le cube est mis en cache. La clé contient une **empreinte des données**
  (nombre, dernier id et dernière modification des lignes ; nombre de lignes
  par statut ; nombre et dernières modifications/suppressions des imports) :
  un import, une suppression ou un rapprochement change l'empreinte et les
  chiffres sont recalculés à la requête suivante.
- Durée maximale du cache : 10 minutes. Le bouton **Actualiser** force le
  recalcul immédiat.
- Temps mesurés sur les données réelles : environ 8 s pour le premier calcul,
  0,3 s ensuite.

### Règles de calcul

- Un pourcentage dont le total de référence vaut 0 est `null`, affiché « — »
  (pas de division par zéro, pas de « 0 % » trompeur).
- Arrondi à 1 décimale, sauf un taux non nul inférieur à 1 % : 3 décimales
  (9 conflits sur 318 890 lignes donnent « 0,003 % » et non « 0 % »).
- « % du total » : cellule / total général du tableau ; « % de la ligne » :
  cellule / total de sa ligne.
- Statuts présentés dans l'ordre de l'application (non rapproché,
  rapproché, partiel, conflit, ignoré) ; les autres valeurs sont triées.
- Au-delà de 200 valeurs en lignes ou en colonnes, le tableau est limité
  et l'interface le signale (« affinez les filtres »).
- Un fichier importé plusieurs fois est distingué par son numéro d'import.

### Sécurité

- Le point d'accès exige d'être connecté avec une adresse e-mail vérifiée.
- Les données de rapprochement ne sont renvoyées (`rapprochement`) qu'avec la
  permission `matching-results.viewAny` ; sinon la valeur est `null`.
- Les dimensions, mesures, sources, statuts et types d'opération sont validés
  par liste blanche : aucune valeur reçue n'est insérée dans le SQL.
- Les erreurs de saisie (date de fin avant la date de début, même
  regroupement en lignes et en colonnes) sont renvoyées en français (HTTP 422).

### Paramètres de `GET /dashboard/analytics`

| Paramètre | Valeurs |
|---|---|
| `annee` | 2000 à 2100 |
| `date_debut`, `date_fin` | `AAAA-MM-JJ`, fin ≥ début |
| `sources[]` | codes de sources existants |
| `statuts[]` | `unmatched`, `matched`, `partial`, `conflict`, `ignored` |
| `types[]` | types d'opérations (import, rapprochement, export…) |
| `utilisateur` | id d'utilisateur (administrateurs seulement) |
| `dim_lignes` | `annee`, `mois`, `jour`, `source`, `statut`, `canal`, `fichier` (défaut `source`) |
| `dim_colonnes` | idem, ou `aucune` (défaut `statut`) ; différent de `dim_lignes` |
| `mesure` | `lignes` (défaut) ou `montant` |
| `actualiser` | `1` pour forcer le recalcul |

## Vérification sur les données réelles

Comparaison avec des requêtes SQL directes sur la base (juillet 2026) :

| Contrôle | Tableau de bord | SQL |
|---|---|---|
| Total des lignes | 318 890 | 318 890 |
| ALPHA du 01 au 15/07 : lignes / rapprochées | 38 853 / 38 757 | identique |
| Montants par canal (6 canaux, en millimes) | identiques | identiques |
| Taux de rapprochement global | 99,7 % | 317 843 / 318 890 |

## Guide utilisateur

1. Ouvrir le **Tableau de bord**.
2. Dans « Analyse du rapprochement », choisir les filtres : les chiffres se
   mettent à jour automatiquement (pas de bouton à cliquer). Les filtres sont
   conservés dans l'adresse de la page : on peut la recharger ou la mettre en
   favori.
3. **Réinitialiser** remet tous les filtres à zéro ; **Actualiser** recalcule
   immédiatement (utile juste après un import).
4. Tableau croisé : choisir ce qui va en lignes et en colonnes, puis la mesure.
   Les boutons **Valeurs / % du total / % de la ligne** changent l'affichage
   sans relancer de calcul.
5. Section « Opérations » : suivre les imports, rapprochements et exports, et
   lire le message des derniers échecs.

## Tests

`tests/Feature/DashboardAnalyticsTest.php` (10 tests) :
- indicateurs calculés à partir de lignes créées pour le test ;
- combinaison des filtres (source, période, statut, année) ;
- tableau croisé : valeurs, totaux, % du total, % de la ligne, mesure montant,
  sans colonne ;
- pourcentage `null` quand le total vaut 0, et 3 décimales pour un très
  faible taux ;
- évolution par jour puis par mois ;
- chiffres mis à jour dès qu'une ligne est rapprochée (invalidation du cache) ;
- utilisateur sans permission : pas de données de rapprochement, seulement
  ses opérations ; un administrateur voit tout et peut filtrer ;
- messages d'erreur en français ;
- affichage de la page selon la permission.
