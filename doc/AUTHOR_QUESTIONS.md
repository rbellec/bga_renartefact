# Questions pour l'auteur

Les questions sont catégorisées par **statut** et par **type**. Une hypothèse
`[Hx]` référence `doc/ASSUMPTIONS.md`.

**Statuts** :
- 🟠 **OPEN** — nécessite une réponse avant implémentation
- 🟡 **ASSUMED** — hypothèse posée, utilisable en l'état ; confirmation souhaitée
- 🟢 **CLOSED** — résolu

**Types** :
- RULES-AMBIGUOUS : règle ambiguë
- RULES-MISSING : cas non couvert
- RULES-IMPLICIT : règle évidente pour un joueur humain mais à expliciter
- FEEDBACK : suggestion/amélioration de design
- BGA : contrainte technique spécifique à la plateforme

---

## Q1 — Nombre de cartes rôles distribuées à 4 joueurs 🟡 RULES-AMBIGUOUS `[H2]`

Le tableau indique pour **4 et 5 joueurs** : 2 complices + 3 détectives = 5
cartes rôle. À 4 joueurs, seules 4 cartes sont distribuées. Que fait-on de
la 5ᵉ ?

- **Option A** (assumée) : on prépare les 5 cartes, on les mélange, on en
  distribue 4, la 5ᵉ reste inconnue → incertitude sur qui est complice.
- **Option B** : à 4 joueurs on utilise 2 complices + 2 détectives = 4
  cartes exactement (mais contredit le tableau).
- **Option C** : à 4 joueurs, 1 complice + 3 détectives = 4 cartes (simple,
  mais contredit le tableau).

> Actuellement **Option A** implémentée `[H2]`.

## Q2 — Canne 2 : visibilité de la main cible 🟡 RULES-AMBIGUOUS `[H3]`

« Prendre une carte **au choix** dans la main d'un autre joueur » : le
voleur voit-il la main cible pour choisir ? La règle « interdit de montrer
sa main » suggère non → choix par position aveugle.

> Actuellement : **position aveugle** `[H3]`.

## Q3 — Récupération d'un artéfact caché : action vraiment libre ? 🟡 RULES-IMPLICIT `[H4]`

« Vous pourrez le récupérer quand bon vous semble, même pendant le tour
d'un autre joueur ». Cela compte-t-il comme **action** ? Empêche-t-il la
règle « défausse obligatoire si aucune carte jouée » pendant son propre
tour ?

> Assumé : **action gratuite et réactive**, ne compte pas comme action jouée.

## Q4 — Niveau « Renardeau » : le coup de résolution 4 cartes est-il autorisé ? 🟡 RULES-AMBIGUOUS `[H5]`

« Utilisez seulement le premier effet de chaque combinaison » — sans la
résolution 4 cartes, les détectives ne peuvent plus gagner. Est-elle
considérée comme un coup de résolution hors combinaison classique ?

> Assumé **oui** `[H5]`.

## Q5 — Niveau « Renardeau » : le « 3 différents » compte-t-il ? 🟡 RULES-AMBIGUOUS `[H5]`

« 3 artéfacts différents » active une combinaison à 2 — est-ce considéré
comme un « premier effet » autorisé en Renardeau, ou une combinaison
« avancée » uniquement en Renard ?

> Assumé **oui** (puisque l'effet résultant est celui d'une paire, qui est
le premier effet).

## Q6 — Complices et victoire par temps : rôle révélé ? 🟠 RULES-MISSING

Quand la pioche s'épuise sans carte compteur → complices gagnent. À ce
moment, révèle-t-on tous les rôles ? L'artéfact volé ?

> Assumé : **tous les rôles sont révélés** + artéfact révélé, pour fermer
la partie proprement en BGA (statistiques, classement).

## Q7 — Défausse vide lors d'une récupération (canne 3) 🟠 RULES-MISSING

Si la défausse ne contient pas 2 types différents, la combinaison **2 cannes + 1 carte
différente** reste-t-elle jouable ? On suppose : effet non applicable → combo
interdite (cf. H7).

> Assumé : **combo interdite si effet impossible** `[H7]`.

## Q8 — Cacher plusieurs fois le même artéfact 🟠 RULES-AMBIGUOUS

Un joueur peut-il cacher 2 artéfacts identiques (1 dans cachette gauche, 1
dans cachette droite) ? La règle « un seul par cachette » autorise cela ;
est-ce voulu ?

> Assumé **oui**.

## Q9 — Limite sur le nombre de combinaisons par tour 🟡 RULES-IMPLICIT `[H6]`

Peut-on jouer, par exemple, 4 fois « 2 sabliers » en un seul tour (en
réalimentant la main grâce aux effets) ? Jusqu'où peut-on chaîner ?

> Assumé : **oui**, autant que souhaité tant que l'effet est applicable
(une seule fois par tour pour « 3 différents » et « 3 similaires »).

## Q10 — Carte du compteur de mélange : visible ? 🟡 RULES-IMPLICIT `[H20]`

La carte est posée sous la pioche (puis sur la table) : son état
(« 1 » ou « 2 ») est-il publiquement visible ? Traditionnellement oui.

> Assumé **oui**.

## Q11 — Cacher : action limitée par combinaison ? 🟠 RULES-AMBIGUOUS

Quand je joue **2 chapeaux**, je cache **1 artéfact** (donc je joue 3
cartes : 2 chapeaux + 1 artéfact). Confirmation ?
Et pour **3 chapeaux** : 4 cartes jouées (3 chapeaux + 1 artéfact) ?

> Assumé **oui** — la carte cachée est prélevée de la main en plus des
chapeaux joués.

## Q12 — Artéfact caché volable par canne 2 ? 🟠 RULES-AMBIGUOUS

Quand un voleur utilise **2 cannes**, peut-il cibler une cachette (artéfact
caché) ou uniquement la main ? La règle dit « dans la main d'un autre
joueur » → non, cachettes protégées. C'est d'ailleurs l'intérêt du chapeau.

> Assumé : **cachettes intouchables** par canne 2.

## Q13 — Canne 3 : cartes artéfacts différents — de l'un l'autre, ou de ceux déjà en main ? 🟠 RULES-AMBIGUOUS

« Récupérer deux cartes d'artéfacts **différents** dans la défausse » — les
deux cartes prises doivent être de 2 types différents entre elles (ex. 1
canne + 1 loupe). C'est une condition sur les 2 cartes prises, pas sur la
main.

> Assumé **oui** : les 2 cartes récupérées sont de 2 types différents l'une de
l'autre.

## Q14 — BGA : joueur 2 non supporté 🟡 BGA `[H1]`

À 2 joueurs, les règles ne définissent pas de répartition. On désactive le
mode 2 joueurs par défaut (players = 3..6).

## Q15 — Noms définitifs 🟠 FEEDBACK

Le projet a eu plusieurs noms (Renardeau, Renartefact). Le nom technique
BGA est verrouillé à `renartefact`. Le nom commercial affiché est-il bien
**« Renartefact »** ?

> Actuellement : **Renartefact** comme nom affiché.

## Q16 — Fin anticipée / abandon 🟠 BGA

En ligne, un joueur peut abandonner en cours de partie → zombie turn. Que
fait le zombie ? Options :
- Défausse automatique d'une carte aléatoire.
- Joue un 2-combo aléatoire si applicable, sinon défausse.

> Assumé : **défausse aléatoire** (choix simple et neutre).
