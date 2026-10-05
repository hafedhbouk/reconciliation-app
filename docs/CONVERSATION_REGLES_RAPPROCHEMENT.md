# Conversation : règles de rapprochement

Cette transcription dédiée reprend l'échange visible sur la page `http://127.0.0.1:8000/admin/matching-rules`. L'index local ne contenait pas de session distincte sur ce sujet ; le passage correspondant figurait dans la conversation générale.

## Question sur les boutons et les règles

**Utilisateur**

> Dans cette page : http://127.0.0.1:8000/admin/matching-rules en capture. C'est quoi le rôle du bouton Détecter les doublons et le bouton Balayer les non rapprochés. En plus dans le tableau en dessous c'est quoi la différence entre Alpha ↔ WEB / STEG et Fichiers : ALPHA ↔ WEB et de même pour les autres combinaisons pourquoi il y a des répitions règles sans le préfixe Fichier et sans le préfixe Fichier. et puis pour les status c'est quoi la différence entre Actif et Inactif pour chaque règle.

**Assistant**

- **Détecter les doublons** recherche les transactions répétées dans une même source et crée des exceptions pour les occurrences supplémentaires. Il ne supprime ni ne fusionne les transactions.
- **Balayer les non-rapprochés** crée une exception pour les transactions encore sans correspondance, sauf si elles ont déjà une exception ouverte ou en cours d'examen.
- **Lancer tout** exécute les règles actives par priorité, puis détecte les doublons et balaie les non-rapprochés.

Les règles comme **ALPHA ↔ WEB / STEG** sont des règles de rapprochement réutilisables entre sources. Les règles **Fichiers : ALPHA ↔ WEB** sont créées pour comparer deux fichiers précis ; elles conservent le résultat de cette comparaison, mais ne sont pas relancées par « Lancer tout ».

Une règle **Active** est incluse dans « Lancer tout ». Une règle **Inactive** en est exclue, mais peut être lancée individuellement avec son bouton de lecture.

## Question sur l'utilité

**Utilisateur**

> D'après vous est ce que c'est utile d'utiliser cette méthode, si oui donner les avantages, sinon, donner les inconvinients et proposer des améliorations.

**Assistant**

Oui, le workflow « règles → détection de doublons → balayage des non-rapprochés » est utile comme contrôle après un rapprochement complet.

**Avantages** : il signale des doublons potentiels sans supprimer de données et rend visibles les transactions restantes sans correspondance. Comme le balayage vient après les règles, il ne signale que les lignes que celles-ci n'ont pas rapprochées.

**Limites** : le balayage peut produire beaucoup d'exceptions, certaines transactions sans correspondance étant légitimes ou en attente d'un autre fichier. Un doublon détecté automatiquement doit aussi rester un candidat à vérifier.

**Améliorations proposées** : prévisualiser les volumes avant exécution, filtrer par fichier ou période, distinguer les cas attendus des anomalies, rattacher les exceptions à leur exécution et clôturer les exceptions de non-rapproché lorsque la transaction est rapprochée.

## Mise en œuvre

**Utilisateur**

> Pouvez vous exécuter les améliorations conseillées.

**Assistant**

Les améliorations ont été mises en place :

- Une page de contrôle permet de filtrer par source, fichier et dates d'opération, puis de prévisualiser les nombres de doublons candidats et de non-rapprochés avant de lancer chaque action séparément.
- Les doublons sont qualifiés de **potentiels** et nécessitent une vérification manuelle ; aucune transaction n'est supprimée.
- Une exception de non-rapproché peut être qualifiée d'**attendue**. Le balayage ne recrée pas une exception déjà résolue.
- Une exception ouverte de non-rapproché est automatiquement clôturée si la transaction est ensuite rapprochée. Les exceptions déjà en revue ne sont pas modifiées.
- Les exceptions portent un identifiant de lot visible dans leur fiche et leur liste.

La migration additive nécessaire a été appliquée à la base locale. Les tests de régression ciblés de cette mise en œuvre ont passé : **102 tests et 444 assertions**.