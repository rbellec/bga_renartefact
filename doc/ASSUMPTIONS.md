# Hypothèses d'implémentation

Chaque hypothèse est référencée par un ID `[Hx]` dans le code et dans
`AUTHOR_QUESTIONS.md`.

| ID  | Hypothèse | Raison |
|-----|-----------|--------|
| H1  | Nombre de joueurs supporté : **3 à 6**. Le tableau de mise en place commence à 3. 2 joueurs non supportés dans v1. | Table des rôles ne couvre pas 2. |
| H2  | Pour **4 joueurs** : on prépare 2 complices + 3 détectives (5 cartes rôle), on en distribue 4 au hasard → **1 carte rôle reste inconnue** au tirage. _(cf. Q1, à confirmer)_ | Cohérent avec le texte du tableau : « 4 et 5 → 2 complices, 3 détectives ». |
| H3  | **Canne 2** : le joueur ciblé ne peut pas montrer sa main ; le voleur choisit **une position aveugle** (pas le contenu). La carte rendue (facultative) est aussi rendue face cachée. | Compatible avec la règle « interdit de montrer sa main ». |
| H4  | **Récupération d'un artéfact caché** (chapeau) : action **réactive et gratuite**, possible même pendant le tour d'un autre joueur. N'empêche pas les autres actions. La carte revient dans la main, face cachée. | Formulation explicite : « quand bon vous semble ». |
| H5  | **Niveau Renardeau** : seuls les effets « 2 cartes similaires » et le « 3 différents » (qui active un effet à 2) s'appliquent. Le coup de résolution « détective 4 cartes similaires » reste disponible, sinon les détectives ne peuvent jamais gagner. | Sans cela, la partie ne peut pas se terminer par une victoire détectives. |
| H6  | **Ordre d'action libre** : un joueur peut alterner les combinaisons (2 sabliers, puis 2 cannes, puis revenir à 2 sabliers), tant qu'il a la main et que l'effet s'applique. La règle « 3 différents » est limitée à **une fois par tour**. | « Autant de fois que souhaité » ne s'applique qu'aux combinaisons 2 similaires. |
| H7  | **Une action doit pouvoir être appliquée** pour être jouée : ex. impossible de jouer 2 sabliers si on a déjà 5 cartes (pour la v2 où 2 sabliers pioche 1). Pour 3 sabliers, impossible si main déjà à 5. Loupe 2 : impossible si tous les indices ont déjà été vus par le joueur. Canne 3 : nécessite ≥ 2 types différents dans la défausse. Chapeau 2/3 : nécessite la cachette correspondante vide. | « Vous ne pouvez jouer des cartes qu'en appliquant l'effet ». |
| H8  | **Cartes jouées** (même en tentative de résolution ratée) : toutes vont à la défausse. | « Toutes les cartes jouées doivent être mises à la défausse. » |
| H9  | **Seuls les détectives peuvent tenter la résolution** (4 cartes similaires). Si un complice essaie, la règle l'interdit. | « Pour récupérer l'artéfact volé un des détectives devra jouer… » |
| H10 | **Défausse de fin de tour** : seulement si le joueur n'a joué aucune combinaison durant son tour (pas même 2 similaires). | « Si le joueur n'a pas joué de cartes durant son tour, il doit défausser une carte de son choix. » |
| H11 | **Limite de main** : pas de limite supérieure. Seule la pioche en début de tour vise 5 ; les combos comme 2 sabliers peuvent pousser au-dessus. | Aucune limite mentionnée. |
| H12 | **Pioche en début de tour** = pioche obligatoire jusqu'à 5, avant toute action. | Texte explicite. |
| H13 | Premier joueur : choisi **aléatoirement** par le framework BGA (remplace « le mieux habillé »). | Impossible en ligne. |
| H14 | **Indices vus** et **rôles vus** sont mémorisés **par joueur** côté serveur (table custom) ; un joueur ne revoit pas automatiquement un indice déjà consulté, mais peut rejouer la combinaison si l'effet est applicable (indice encore non vu). | Fidélité : la « carte mémo » remplit ce rôle en physique. |
| H15 | **Fin par pioche épuisée** : vérifiée au moment où un joueur doit piocher (début de tour ou sablier). Le recyclage via la carte compteur se fait automatiquement. Quand la pioche est vide **et** qu'il n'y a plus de carte compteur, victoire immédiate des complices. | Règle explicite. |
| H16 | **Victoire** : la partie se termine immédiatement dès qu'une condition de victoire est remplie (bonne ou mauvaise résolution détective, ou pioche épuisée sans compteur). | Règle classique des jeux à victoire par objectif. |
| H17 | **Scoring BGA** : joueurs du camp vainqueur = score 1, perdants = score 0. Partie coopérative asymétrique, donc `losers_not_ranked=true`. | Cohérent avec les règles : tous les vainqueurs du camp gagnent ensemble. |
| H18 | **Canne 2 — donner en retour** : optionnel, le joueur voleur choisit s'il rend ou pas ; s'il rend, il choisit la carte. La carte rendue reste cachée au voleur (… mais comme il a choisi dans sa main, il sait laquelle). La cible voit la carte reçue (privé). | Cohérent avec « vous pouvez donner une autre carte en retour ». |
| H19 | **Combinaison 3 différents** : les 3 types posés doivent être tous différents (ex. 1 canne + 1 sablier + 1 chapeau). L'effet activé est la combinaison « 2 similaires » du 4e type (celui absent). Si ce 4e effet ne peut pas s'appliquer (ex. loupe 2 mais tous les indices vus), la combinaison ne peut pas être jouée. | Fidélité à H7. |
| H20 | **Compteur de mélange** : face visible sur la pioche. Les joueurs voient l'état courant ; c'est une info publique. | Implicite (posé « au milieu de la table »). |
| H21 | **Cartes cachées (chapeau)** : l'existence d'un artéfact dans une cachette est publique (on voit la carte face cachée devant le joueur), mais pas son identité. | Cohérent avec l'usage physique. |
