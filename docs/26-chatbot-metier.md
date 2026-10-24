# 26 — Chatbot métier (« Assistant »)

Phase 6 des évolutions (octobre 2026).

## Analyse et choix de l'architecture

| Critère | Constat |
|---|---|
| Données accessibles | lignes importées et leur statut (`normalized_transactions`), résultats de rapprochement (`matching_results`), exceptions, imports, suivi des opérations |
| Services existants | `ReconciliationAnalytics` (indicateurs, tableau croisé, Phase 3 bis), `OperationAnalytics` (opérations, Phase 1), modèles Eloquent ; tous déjà testés et vérifiés sur les données réelles |
| Permissions | `matching-results.viewAny`, `exceptions.viewAny`, `imports.viewAny` ; opérations : les siennes, toutes pour un administrateur (`OperationPolicy`) |
| Besoin en IA | les questions attendues sont des questions métier fermées (combien, quel taux, quelle évolution, sur quelle période, pour quelle source) : une reconnaissance par mots-clés et expressions de période suffit |
| Confidentialité | données financières de la STEG : un modèle d'IA externe recevrait au minimum la question, et souvent des données pour formuler la réponse |
| Coût | un fournisseur externe facture chaque question et demande une clé d'API, un contrat et une validation de la sécurité |

**Choix : moteur local d'intentions métier**, sans service externe.
- Aucune donnée ne quitte le serveur ; aucun coût.
- Chaque réponse est calculée par un service métier existant : aucun SQL n'est
  construit à partir du texte de la question.
- Un chiffre absent n'est jamais inventé : la réponse dit que la donnée
  n'existe pas, ou qu'elle ne peut pas être calculée.

Évolution possible, après validation (fournisseur, règles de confidentialité
de la STEG) : utiliser un modèle d'IA seulement pour **reconnaître** des
formulations plus libres, en ne lui envoyant que le texte de la question ;
les chiffres resteraient calculés par les mêmes services.

## Fonctionnement

```
Question ──► QuestionParser ──► Chatbot ──► intention retenue ──► permission ? ──► service métier ──► Answer (JSON)
             (période, sources)   (scores)                          non : refus
```

| Fichier | Rôle |
|---|---|
| `app/Services/Chatbot/QuestionParser.php` | texte normalisé (minuscules, sans accents), période, sources citées |
| `app/Services/Chatbot/Chatbot.php` | choix de l'intention (meilleur score), contrôle des droits, gestion des erreurs |
| `app/Services/Chatbot/Intents/*.php` | une classe par question métier |
| `app/Services/Chatbot/Answer.php` | réponse : statut, texte, détail, méthode de calcul, suggestions |
| `app/Http/Controllers/ChatbotController.php` | `POST /assistant` (`chatbot.ask`) : connexion obligatoire, 500 caractères maximum, 30 questions par minute |
| `resources/views/components/chatbot.blade.php`, `resources/js/chatbot.js` | bouton « Assistant » et fenêtre de discussion sur toutes les pages, pour les utilisateurs connectés |

### Questions prises en charge

| Intention | Exemple | Données | Droit |
|---|---|---|---|
| Rapprochements effectués | Combien de rapprochements ont été effectués cette année ? | résultats de rapprochement, par date du rapprochement ; détail par statut et nombre d'exécutions | `matching-results.viewAny` |
| Taux de rapprochement | Quel est le taux de rapprochement du mois dernier ? | lignes importées, par date d'opération ; détail par source | `matching-results.viewAny` |
| Lignes sans correspondance | Combien de lignes n'ont pas trouvé de correspondance ? | lignes au statut « non rapproché », par source | `matching-results.viewAny` |
| Conflits | Combien de conflits pour BNA en juillet 2026 ? | lignes en conflit, taux d'anomalie | `matching-results.viewAny` |
| Évolution mensuelle | Quelle est l'évolution du nombre de rapprochements par mois ? | rapprochements effectués par mois (24 derniers mois au plus) | `matching-results.viewAny` |
| Traitements échoués | Quels sont les derniers traitements échoués ? | suivi des opérations : nombre d'échecs, 5 derniers avec leur message | ses opérations ; toutes pour un administrateur |
| Exceptions | Combien d'exceptions sont encore ouvertes ? | exceptions par statut, et par type pour celles à traiter | `exceptions.viewAny` |
| Imports | Combien de fichiers ont été importés ce mois-ci ? | imports non supprimés, par statut ; 5 plus récents | `imports.viewAny` |
| Aide | Bonjour / aide / que peux-tu faire ? | liste des questions autorisées pour l'utilisateur | — |

**Périodes comprises** (heure de Tunis) : aujourd'hui, hier, cette semaine, la
semaine dernière, ce mois-ci, le mois dernier, cette année, l'année dernière,
« en 2026 », « en juillet » (le dernier mois de juillet passé), « juillet 2026 »,
« le 15/07/2026 », « du 01/07/2026 au 15/07/2026 ». Sans période : toutes les données.

**Sources** : tout code de source cité (ALPHA, BNA, SMT, WEB…), seul ou à plusieurs.

### Statuts d'une réponse

| Statut | Signification |
|---|---|
| `ok` | chiffres calculés sur les données réelles, avec la méthode de calcul |
| `vide` | question comprise, aucune donnée ne correspond (ex. aucun échec) |
| `non_calculable` | valeur impossible à calculer (ex. taux sans aucune ligne) ; la réponse rappelle les dates couvertes par les données |
| `interdit` | l'utilisateur n'a pas le droit de consulter ces données ; aucun chiffre n'est donné |
| `non_pris_en_charge` | question non reconnue ; la réponse propose les questions autorisées |
| `erreur` | incident technique (détail dans le journal Laravel) |

## Sécurité

- Le droit de chaque intention est vérifié **avant** tout calcul.
- Les traitements échoués d'un utilisateur ordinaire sont limités à **ses**
  opérations : ni libellé, ni message d'erreur, ni nom d'un autre utilisateur.
- Les exemples proposés ne contiennent que les questions autorisées.
- La question n'est ni enregistrée ni transmise à un tiers ; affichage échappé
  (pas d'injection HTML) ; jeton CSRF ; limite de débit.

## Vérification sur les données réelles

| Question | Réponse | Contrôle SQL |
|---|---|---|
| Rapprochements effectués cette année | 256 423, en 4 exécutions (6 conflits) | 256 423 `matched`, 6 `conflict`, 4 lots |
| Rapprochements effectués le 18/10/2026 | 158 393, en 2 exécutions | 158 393 entre 17/10 23:00 et 18/10 22:59:59 UTC |
| Taux de rapprochement du mois dernier (septembre 2026) | non calculable : aucune ligne ; données du 01/07/2026 au 31/07/2026 | aucune ligne en septembre |
| Taux de rapprochement en juillet | 99,7 % (317 843 / 318 890) ; BNA 99,4 % (79 219 / 79 718) | identique au tableau de bord |
| Lignes sans correspondance | 1 038 sur 318 890 (+ 9 en conflit) ; ALPHA 186, BNA 496, SMT 356, WEB 0 | identique |
| Traitements échoués | aucun, sur 5 opérations | aucune opération en échec |
| Évolution par mois | octobre 2026 seulement (256 423) : pas encore d'évolution à comparer | tous les rapprochements datent d'octobre |
| Question hors sujet (« salaire du directeur ») | non prise en charge | — |

Temps de réponse : moins d'une seconde en général. La première question sur
les lignes après une modification des données prend environ 10 secondes, le
temps de recalculer le cube d'agrégats du tableau de bord (doc 23).

## Tests

`tests/Feature/ChatbotTest.php` (18 tests) :
- lecture des périodes et des sources (8 formulations) ;
- taux de rapprochement, lignes sans correspondance, conflits : chiffres et détail par source ;
- taux sans aucune ligne : « non calculable », aucun chiffre inventé ;
- rapprochements effectués par date et évolution mensuelle ; période vide ;
- traitements échoués : seulement ses opérations, aucune fuite des opérations
  d'un autre utilisateur ; tout pour un administrateur ;
- refus pour chaque intention sans le droit ; exemples limités aux questions autorisées ;
- un rôle métier (opérateur) obtient les réponses de ses droits ;
- questions non prises en charge (dont `DROP TABLE users;`) ;
- connexion obligatoire, question vide ou trop longue refusée ;
- fenêtre « Assistant » présente pour un utilisateur connecté, absente de la page de connexion.

## Guide utilisateur

1. Cliquer sur **Assistant** (en bas à droite de chaque page).
2. Écrire la question en français, ou cliquer sur une suggestion.
3. La réponse donne le chiffre, son détail (par source, par statut, par mois)
   et, sous le symbole ⓘ, la façon dont il est calculé.
4. Préciser la période (« en juillet 2026 », « le mois dernier ») ou la source
   (« pour BNA ») pour affiner.
