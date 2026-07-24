# Guide utilisateur

Ce guide explique comment utiliser **Reconciliation App** au quotidien :
se connecter, importer des fichiers, lancer le rapprochement automatique,
traiter les exceptions, effectuer un rapprochement manuel, rechercher des
transactions et exporter des résultats.

Pour l'installation technique, voir [`INSTALLATION.md`](INSTALLATION.md).

## Sommaire

1. [Connexion](#1-connexion)
2. [Rôles et permissions](#2-rôles-et-permissions)
3. [Tableau de bord](#3-tableau-de-bord)
4. [Importer des transactions](#4-importer-des-transactions)
5. [Rapprochement automatique](#5-rapprochement-automatique)
6. [Consulter les résultats de rapprochement](#6-consulter-les-résultats-de-rapprochement)
7. [Traiter les exceptions](#7-traiter-les-exceptions)
8. [Rapprochement manuel](#8-rapprochement-manuel)
9. [Recherche multi-critères et exports](#9-recherche-multi-critères-et-exports)
10. [Notifications](#10-notifications)
11. [Paramétrage (administrateurs)](#11-paramétrage-administrateurs)
12. [Journal d'audit](#12-journal-daudit)
13. [Mon profil](#13-mon-profil)
14. [Questions fréquentes](#14-questions-fréquentes)

## 1. Connexion

Rendez-vous sur l'URL de l'application et connectez-vous avec l'adresse
e-mail et le mot de passe fournis par votre administrateur.

Un compte super-admin est créé à l'installation :
`admin@reconciliation.local` / `password` — à changer immédiatement s'il
est encore utilisé.

Après connexion, vous arrivez sur le **Tableau de bord**. Le menu de
gauche n'affiche que les sections auxquelles votre rôle donne accès.

## 2. Rôles et permissions

L'application distingue trois rôles :

| Rôle | Ce qu'il peut faire |
|---|---|
| **admin** | Accès complet à toutes les fonctionnalités : imports, règles de rapprochement, exceptions, paramétrage, gestion des utilisateurs et des rôles. |
| **auditor** | Accès en lecture seule à toutes les ressources, plus le journal d'audit. Rôle de supervision, aucune action de modification. |
| **operator** | Travail quotidien de rapprochement : créer des imports, modifier le mapping des colonnes d'une source, effectuer des rapprochements manuels, traiter les exceptions, utiliser la recherche. Ne peut ni modifier/lancer les règles de rapprochement automatique, ni gérer les utilisateurs/rôles. |

Si un menu ou un bouton n'apparaît pas, c'est que votre rôle n'y donne pas
accès — contactez un administrateur si vous pensez qu'il s'agit d'une
erreur.

## 3. Tableau de bord

Le tableau de bord (menu **Dashboard**) donne une vue d'ensemble en un
coup d'œil :

- **Transactions totales**
- **Exceptions ouvertes**
- **Imports ce mois-ci**
- **Taux de rapprochement** (part des transactions rapprochées avec succès)
- un graphique de répartition des résultats de rapprochement (rapproché,
  partiel, conflit…)
- un graphique de répartition des exceptions par type
- un graphique de volume de transactions par source
- une courbe de tendance journalière

Les cartes affichées dépendent des permissions de votre rôle.

## 4. Importer des transactions

Menu **Imports**.

### 4.1 Lancer un nouvel import

1. Cliquez sur **Nouvel import**.
2. Choisissez la **Source** concernée (ALPHA, BNA, WEB, SMT…). Le type de
   fichier attendu (CSV, XLSX…) est indiqué entre parenthèses.
3. Sélectionnez le **Fichier** à importer.
4. Cliquez sur **Importer**.

L'application vérifie d'abord que les colonnes du fichier correspondent
au mapping enregistré pour cette source. Si des colonnes obligatoires
sont manquantes, vous êtes redirigé vers l'écran de configuration du
mapping (**Sources → Mappings**) pour corriger la correspondance avant de
réessayer.

Si un fichier identique a déjà été importé, un message d'avertissement
vous informe du doublon ; vous pouvez confirmer l'import quand même.

### 4.2 Suivre le traitement

L'import est traité en arrière-plan (file d'attente) afin de ne pas
bloquer votre navigateur, même sur de gros fichiers. La liste des imports
affiche pour chaque import :

- la **Source** et le **Fichier**
- le **Statut** (en attente, en cours, terminé, terminé partiellement,
  échoué)
- le nombre de **Lignes** traitées avec succès
- le nombre d'**Erreurs** (lignes rejetées, isolées individuellement sans
  bloquer le reste du fichier)
- la **Durée** du traitement
- qui a **Importé** le fichier et à quelle **Date**

Cliquez sur un import pour voir le détail des lignes en erreur et leur
motif de rejet.

Vous recevez une **notification** (cloche en haut de l'écran) dès que le
traitement de votre import est terminé.

## 5. Rapprochement automatique

Menu **Rapprochement → Règles de rapprochement** (accès admin).

### 5.1 Principe

Une règle de rapprochement associe deux sources (Source A / Source B) et
définit :

- la **cardinalité** attendue (1-1, 1-N…),
- la **priorité** d'exécution (les règles s'exécutent dans l'ordre, et
  consomment progressivement le même bassin de transactions non
  rapprochées — l'ordre a donc un impact sur le résultat),
- son **statut** actif/inactif.

Pour chaque groupe de transactions partageant une référence, le moteur
applique une logique de tolérance en trois cas :
- **correspondance exacte ou avec écart montant/date toléré** → un
  résultat de rapprochement (*matched*) est créé ;
- **un seul des deux critères (montant ou date) est dans la tolérance** →
  un résultat en **conflit** est créé, avec une exception associée pour
  investigation ;
- **aucun des deux critères ne correspond** → aucune action (la
  correspondance de référence seule n'est pas jugée fiable à grande
  échelle).

### 5.2 Lancer le rapprochement

Trois actions sont disponibles en haut de la liste des règles :

- **Lancer tout** : exécute toutes les règles actives dans l'ordre de
  priorité, puis la détection des doublons, puis le balayage des
  transactions encore non rapprochées. Une confirmation est demandée
  avant le lancement. C'est l'action à utiliser en routine, après chaque
  vague d'imports.
- **Détecter les doublons** : recherche les transactions en double au
  sein d'une même source.
- **Balayer les non-rapprochés** : marque comme non rapprochées toutes
  les transactions qui n'ont trouvé aucune correspondance, pour qu'elles
  apparaissent dans le suivi.

Vous pouvez aussi lancer une **règle individuelle** depuis la liste
(bouton d'action sur la ligne correspondante).

À la fin d'un lancement (« Lancer tout » comme une règle unique), une
notification récapitulative est envoyée — pas une par règle, un seul
résumé du batch.

> Ces actions sont limitées en fréquence (*rate limiting*) car elles
> peuvent être coûteuses sur un gros volume de données.

## 6. Consulter les résultats de rapprochement

Menu **Rapprochement → Résultats de rapprochement**.

Chaque résultat indique les transactions rapprochées des deux côtés, le
statut (rapproché, partiel, conflit…), la règle qui l'a produit (ou
« manuel » s'il vient d'un rapprochement manuel), et le détail
ligne-à-ligne. Vous pouvez exporter la liste en **CSV**, **Excel** ou
**PDF**.

## 7. Traiter les exceptions

Menu **Rapprochement → Exceptions**.

Une exception est créée automatiquement quand une transaction pose
problème : **non trouvée**, **montant différent**, **date différente**,
**doublon**, **paiement orphelin** ou **conflit**.

### 7.1 Statuts

| Statut | Signification |
|---|---|
| Ouvert | Pas encore traité. |
| En cours de traitement | Quelqu'un y travaille. |
| Résolu | Traité et clos. |
| Ignoré | Volontairement laissé de côté (faux positif, non pertinent…). |

### 7.2 Traiter une exception

1. Ouvrez l'exception depuis la liste pour voir son détail : type,
   transaction concernée (source, référence, montant, date), résultat de
   rapprochement associé le cas échéant.
2. Dans le bloc **Mettre à jour** :
   - changez le **Statut** et/ou le **Type** si nécessaire,
   - **assignez** l'exception à un utilisateur,
   - ajoutez un **commentaire** de résolution,
   - cliquez sur **Enregistrer**.
3. Vous pouvez joindre des **pièces jointes** (justificatifs) à
   l'exception, les télécharger, ou les supprimer.

La liste peut être exportée en CSV/Excel/PDF pour un reporting externe.

## 8. Rapprochement manuel

Menu **Rapprochement → Rapprochement manuel**.

Utilisez cet écran pour ce que les règles automatiques n'ont pas su
résoudre : vous recherchez indépendamment des transactions non
rapprochées des deux côtés (**Côté A** / **Côté B**) et les liez à la
main.

1. Dans chaque panneau, filtrez par **source**, **référence**, **montant
   min/max**, **date** puis cliquez sur **Rechercher**.
2. Cochez, dans chaque panneau, les transactions à associer entre elles.
3. Le bouton **Créer un rapprochement** indique le nombre de lignes
   sélectionnées et ne s'active que lorsqu'au moins une ligne est cochée
   de chaque côté.
4. Cliquez sur **Créer un rapprochement** pour valider.

Le système vérifie qu'aucune ligne sélectionnée n'est déjà rapprochée
ailleurs avant de créer le résultat. Le rapprochement est enregistré avec
votre nom comme auteur (traçabilité).

## 9. Recherche multi-critères et exports

Menu **Recherche**.

Filtrez les transactions par **source**, **référence**, **statut de
rapprochement**, **canal**, **montant min/max** et **plage de dates**,
puis cliquez sur **Rechercher**. Les résultats s'affichent dans un
tableau paginé et triable.

Depuis cet écran, exportez les résultats filtrés en **CSV**, **Excel**
ou **PDF** via les boutons en bas du panneau de filtres — l'export
respecte les mêmes filtres que la recherche affichée.

## 10. Notifications

La cloche en haut de l'application signale les événements qui vous
concernent :

- fin de traitement d'un **import** (nombre de lignes réussies/en erreur),
- fin d'une **action de rapprochement** (batch de règles, détection de
  doublons, balayage…).

Depuis **Notifications**, marquez une notification comme lue individuellement,
ou utilisez **Tout marquer comme lu** pour les traiter en une fois.

## 11. Paramétrage (administrateurs)

Menu **Paramétrage** — réservé aux rôles disposant des permissions
correspondantes (essentiellement `admin`).

- **Banques** : référentiel des banques.
- **Sources** : les systèmes sources de transactions (ALPHA, BNA, WEB,
  SMT, STEG…) — type de fichier attendu, statut actif/inactif.
  Depuis la fiche d'une source, l'écran **Mappings** permet d'associer
  chaque colonne du fichier source à un champ canonique de
  l'application (référence, montant, date…) : c'est ce mapping qui est
  utilisé pour valider et interpréter les imports, sans écrire de code.
- **Devises** : référentiel des devises.
- **Jours fériés** : calendrier utilisé par le moteur de tolérance de
  date lors du rapprochement.
- **Paramètres** : réglages généraux de l'application.

Le menu **Administration** (Utilisateurs, Rôles & Permissions) permet de
créer des comptes, leur assigner un rôle, et ajuster les permissions par
rôle.

## 12. Journal d'audit

Menu **Suivi → Journal d'audit** — accessible aux rôles `admin` et
`auditor`.

Chaque création, modification ou suppression sur les données (ainsi que
les connexions/déconnexions et tentatives de connexion échouées) est
tracée avec l'utilisateur, la date, et le détail des valeurs avant/après.
Utilisez ce journal pour retracer qui a fait quoi.

## 13. Mon profil

Depuis le menu utilisateur (haut de page), accédez à **Profil** pour :

- modifier votre nom et votre adresse e-mail,
- changer votre mot de passe,
- supprimer votre compte.

## 14. Questions fréquentes

**Mon import reste bloqué en « en cours ».**
Le traitement se fait en arrière-plan ; si personne n'a démarré le
worker de file d'attente côté serveur, contactez votre administrateur
système (voir [`INSTALLATION.md`](INSTALLATION.md), section sur le
worker).

**Pourquoi certaines transactions ne se rapprochent jamais
automatiquement ?**
Une correspondance de référence seule n'est pas jugée suffisante : sans
correspondance de montant ou de date dans la tolérance configurée, le
moteur ne crée aucun lien automatique. Utilisez le **rapprochement
manuel** pour ces cas, après vérification.

**Une exception a été créée à tort, que faire ?**
Passez son statut à **Ignoré** avec un commentaire expliquant pourquoi,
plutôt que de la supprimer — l'historique reste ainsi traçable.

**Je ne vois pas certains menus.**
Votre rôle ne dispose pas des permissions correspondantes (voir
[Rôles et permissions](#2-rôles-et-permissions)). Demandez à un
administrateur de vérifier votre rôle si besoin.
