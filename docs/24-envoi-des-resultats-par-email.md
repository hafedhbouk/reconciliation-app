# 24 — Envoi des résultats de rapprochement par e-mail

Phase 4 des évolutions (octobre 2026).

## Fonctionnalités

- **Au lancement** d'une comparaison de fichiers (page « Règles de
  rapprochement », bloc « Lancer un rapprochement personnalisé ») : case
  « Envoyer les résultats par e-mail à la fin du rapprochement », choix des
  destinataires (l'utilisateur est présélectionné) et, si l'utilisateur a
  accès aux exports, « Joindre le fichier des résultats ». L'e-mail part
  automatiquement quand le rapprochement se termine avec succès.
  Le bouton « Lancer » d'une règle accepte les mêmes paramètres
  (`email_envoyer`, `email_destinataires[]`, `email_piece_jointe`).
- **Après coup**, pour tout rapprochement terminé (comparaison de fichiers,
  règle lancée seule ou dans « Lancer tout ») : bouton **E-mail** dans
  « Mes opérations ». La page affiche le contenu de l'e-mail, le choix des
  destinataires et l'**historique des envois** (statut, erreur, pièce jointe,
  demandeur, date), avec **Renvoyer** pour un envoi en échec.

## Contenu de l'e-mail

En français (langue fixée sur le message, même si le worker tourne dans une
autre langue). Sujet : « Résultats du rapprochement — *nom de l'opération* ».

| Information | Origine |
|---|---|
| Nom de l'opération | libellé de l'opération (« Rapprochement Alpha_07_2026.xlsx ↔ BNA-07-2026.xlsx ») |
| Date et heure | fin du rapprochement, heure de Tunis |
| Statut final | « Terminé » |
| Lignes traitées | lignes des deux fichiers (comparaison) ; clés examinées (règle) |
| Correspondances trouvées | rapprochements |
| Correspondances manquantes | comparaison : lignes présentes d'un seul côté (détail fichier A / fichier B) ; règle : lignes des deux sources encore non rapprochées après la règle |
| Erreurs | conflits (même clé, montant ou date différent) |
| Lien | page des résultats dans l'application (`APP_URL` + adresse) : **connexion obligatoire** |
| Pièce jointe | facultative, voir ci-dessous |

Tous ces chiffres sont ceux enregistrés à la fin du rapprochement (les mêmes
que dans « Mes opérations »). **Aucune ligne de données** n'apparaît dans le
corps du message (confidentialité).

### Pièce jointe

- Produite par l'export existant (`GenerateMatchingExportJob`) :
  - comparaison de fichiers : export des écarts (comme « Écarts par source ») ;
  - règle : export des résultats du lot.
- Format : Excel jusqu'à 1 000 lignes, CSV au-delà (même limite que les
  exports Excel existants).
- L'export apparaît aussi dans la page « Exports » du demandeur.
- Au-delà de **10 Mo**, le fichier n'est pas joint : l'e-mail indique qu'il
  est disponible dans la page « Exports ».
- Seuls les destinataires ayant accès aux exports (`matching-exports.viewAny`)
  le reçoivent ; les autres reçoivent l'e-mail sans fichier, avec une mention.

## Sécurité et destinataires

- Destinataires possibles : **comptes actifs** ayant le droit de consulter les
  résultats (`matching-results.viewAny`). Toute autre valeur est refusée par
  la validation (message en français).
- Le droit est vérifié **à la demande et au moment de l'envoi** : un compte
  désactivé entre-temps ne reçoit rien (envoi marqué en échec avec la raison).
- Demander l'envoi après coup : propriétaire du rapprochement ou
  administrateur (`OperationPolicy`), avec le droit de consulter les résultats.
- La pièce jointe ne peut être demandée qu'avec l'accès aux exports.

## Fiabilité

| Exigence | Solution |
|---|---|
| Mécanismes Laravel | Mailable `App\Mail\MatchingResultsMail` (gabarit Markdown `emails/matching-results`), job `SendResultEmailsJob` sur la file d'attente |
| Échec d'envoi ≠ échec du rapprochement | l'envoi est un job séparé, lancé **après** la fin du rapprochement ; toutes ses erreurs sont attrapées et notées sur la ligne d'envoi ; le statut du rapprochement n'est jamais modifié |
| Rapprochement en échec | les envois en attente sont **annulés** (« Rapprochement en échec : aucun résultat à envoyer ») |
| Pas d'envoi répété | une ligne `result_emails` par (rapprochement, adresse) avec **contrainte d'unicité** ; une nouvelle demande pour la même personne est refusée (« Déjà envoyé ») ; chaque ligne est réservée (`queued` → `sending`) par une seule mise à jour avant l'envoi ; le job ne fait **qu'une tentative** (une relance automatique pourrait envoyer deux fois) |
| Relance | bouton « Renvoyer », seulement pour un envoi en échec |
| Traçabilité | table `result_emails` (statut, tentatives, erreur, remarque, pièce jointe, demandeur, date d'envoi), page « Historique des envois », journal Laravel (`Échec de l'envoi des résultats par e-mail`) |

Statuts d'un envoi : `waiting` (attend la fin du rapprochement), `queued`,
`sending`, `sent`, `failed`, `cancelled`.

## Base de données

Migration `2026_10_24_000001_create_result_emails_table`, **uniquement
additive** :
- `operations.context` (JSON, facultatif) : de quoi retrouver les résultats
  (type `comparison` ou `rule`, fichiers comparés, règle, lot) ;
- nouvelle table `result_emails`.

Les rapprochements terminés **avant** cette migration n'ont pas de contexte :
le bouton « E-mail » n'apparaît pas pour eux.

## Configuration

`.env` (voir les commentaires de `.env.example`) :

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.exemple.tn        # serveur de messagerie
MAIL_PORT=587                    # 465 avec MAIL_SCHEME=smtps
MAIL_SCHEME=null
MAIL_USERNAME=compte@exemple.tn
MAIL_PASSWORD=********
MAIL_FROM_ADDRESS="rapprochement@exemple.tn"
MAIL_FROM_NAME="${APP_NAME}"
APP_URL=https://adresse-reelle-de-l-application
```

Puis : `php artisan config:clear`, et redémarrer le worker
(`php artisan queue:work`), qui envoie les e-mails.

En développement, `MAIL_MAILER=log` écrit les e-mails complets (avec pièce
jointe) dans `storage/logs/laravel.log` sans rien envoyer.

## Vérification sur les données réelles

Comparaison Alpha_07_2026.xlsx ↔ BNA-07-2026.xlsx du 18/10, rejouée avec son
lot (le résultat enregistré est réutilisé : aucune donnée recalculée), e-mail
écrit dans le journal (`MAIL_MAILER=log`) :

| Contrôle | E-mail | Base |
|---|---|---|
| Lignes traitées | 160 073 | 79 718 (BNA) + 80 355 (ALPHA) |
| Correspondances trouvées | 79 213 | `comparison_runs.summary.matched` |
| Correspondances manquantes | 1 631 (1 134 + 497) | `unmatchedA` + `unmatchedB` |
| Erreurs | 3 | `conflicts` |
| Pièce jointe | CSV de 1 631 lignes (> 1 000 lignes) | 1 631 lignes d'écarts |

Correction faite pendant cette vérification : un lot rejoué affichait
« 0 ligne traitée » ; l'avancement indique maintenant les lignes des deux fichiers.

## Tests

`tests/Feature/ResultEmailsTest.php` (12 tests) :
- envoi demandé au lancement : chiffres réels, lien, aucune ligne de données dans le message ;
- pièce jointe produite par l'export existant, seulement pour les destinataires ayant accès aux exports ;
- destinataires sans droit ou désactivés refusés, destinataire obligatoire ;
- pas de double envoi (nouvelle demande, job rejoué) ;
- **échec réel de connexion SMTP** (port fermé) : envoi en échec et tracé,
  rapprochement toujours « Terminé », relance réussie puis refusée une fois envoyé ;
- rapprochement en échec : envois annulés, rien n'est envoyé ;
- destinataire désactivé avant la fin : rien n'est envoyé ;
- chiffres d'une règle (lignes encore non rapprochées) ;
- droits : propriétaire et administrateur seulement, bouton « E-mail » seulement pour un rapprochement terminé ;
- lot rejoué : lignes traitées correctes ;
- e-mail en français même si le worker est en anglais ;
- formulaire de comparaison : seuls les destinataires autorisés sont proposés.

**Non testé** : un envoi réel vers un vrai serveur de messagerie (paramètres
SMTP de la STEG non disponibles). Le chemin d'échec est testé avec une vraie
connexion SMTP refusée, et le message complet a été vérifié dans le journal.
