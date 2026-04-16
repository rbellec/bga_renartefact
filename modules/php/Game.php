<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact;

use Bga\Games\Renartefact\States\TurnStart;

class Game extends \Bga\GameFramework\Table
{
    public const ART_CANNE = 'canne';
    public const ART_SABLIER = 'sablier';
    public const ART_CHAPEAU = 'chapeau';
    public const ART_LOUPE = 'loupe';

    public const ARTEFACTS = [
        self::ART_CANNE,
        self::ART_SABLIER,
        self::ART_CHAPEAU,
        self::ART_LOUPE,
    ];

    public const ROLE_COMPLICE = 'complice';
    public const ROLE_DETECTIVE = 'detective';

    public const LOC_DECK = 'deck';
    public const LOC_DISCARD = 'discard';
    public const LOC_HAND = 'hand';
    public const LOC_INDICE = 'indice';
    public const LOC_STOLEN = 'stolen';
    public const LOC_CACHE_L = 'cache_l';
    public const LOC_CACHE_R = 'cache_r';

    public const OPT_LEVEL = 100;
    public const LEVEL_RENARDEAU = 1;
    public const LEVEL_RENARD = 2;

    public const ROLE_DISTRIBUTION = [
        3 => [self::ROLE_COMPLICE => 1, self::ROLE_DETECTIVE => 2],
        4 => [self::ROLE_COMPLICE => 2, self::ROLE_DETECTIVE => 3],
        5 => [self::ROLE_COMPLICE => 2, self::ROLE_DETECTIVE => 3],
        6 => [self::ROLE_COMPLICE => 3, self::ROLE_DETECTIVE => 4],
    ];

    public \Bga\GameFramework\Components\Deck $cards;

    public function __construct()
    {
        parent::__construct();
        $this->cards = $this->deckFactory->createDeck('card');
    }

    protected function setupNewGame($players, $options = []): mixed
    {
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        $query_values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $query_values[] = vsprintf("(%s, '%s', '%s')", [
                $player_id,
                $color,
                addslashes($player["player_name"]),
            ]);
        }
        $this->DbQuery(
            sprintf(
                "INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s",
                implode(",", $query_values)
            )
        );
        $this->reloadPlayersBasicInfos();

        $nbPlayers = count($players);
        $dist = self::ROLE_DISTRIBUTION[$nbPlayers];
        $roles = [];
        foreach ($dist as $role => $count) {
            for ($i = 0; $i < $count; $i++) {
                $roles[] = $role;
            }
        }
        shuffle($roles);
        $playerIds = array_keys($players);
        foreach ($playerIds as $idx => $pid) {
            $role = $roles[$idx];
            $this->DbQuery("INSERT INTO `rnt_role` (`player_id`, `role`) VALUES ({$pid}, '{$role}')");
        }

        $cards = [];
        foreach (self::ARTEFACTS as $type) {
            $cards[] = ['type' => $type, 'type_arg' => 0, 'nbr' => 13];
        }
        $this->cards->createCards($cards, self::LOC_DECK);
        $this->cards->shuffle(self::LOC_DECK);

        foreach (self::ARTEFACTS as $type) {
            $pool = $this->cards->getCardsOfTypeInLocation($type, null, self::LOC_DECK);
            $firstId = (int)array_keys($pool)[0];
            $this->cards->moveCard($firstId, self::LOC_INDICE);
        }
        $indiceIds = array_keys($this->cards->getCardsInLocation(self::LOC_INDICE));
        shuffle($indiceIds);
        $stolenId = (int)array_shift($indiceIds);
        $this->cards->moveCard($stolenId, self::LOC_STOLEN);
        foreach (array_values($indiceIds) as $i => $cardId) {
            $this->cards->moveCard((int)$cardId, self::LOC_INDICE, $i);
        }

        foreach ($playerIds as $pid) {
            $this->cards->pickCards(5, self::LOC_DECK, $pid);
        }

        $shuffleStart = $nbPlayers >= 5 ? 2 : 1;
        $this->bga->globals->set('shuffle_counter', $shuffleStart);
        $this->bga->globals->set('reshuffles_done', 0);
        $this->bga->globals->set('turn_number', 0);
        $this->bga->globals->set('combo_played_this_turn', false);

        $this->bga->tableStats->init('turns_total', 0);
        $this->bga->tableStats->init('reshuffles', 0);
        $this->bga->tableStats->init('winning_side', 0);
        $this->bga->playerStats->init('combos_played', 0);
        $this->bga->playerStats->init('cards_stolen', 0);
        $this->bga->playerStats->init('indices_seen', 0);
        $this->bga->playerStats->init('roles_seen', 0);

        $firstPlayerId = (int)$playerIds[array_rand($playerIds)];
        $this->gamestate->changeActivePlayer($firstPlayerId);

        return TurnStart::class;
    }

    public function getAllDatas(int $currentPlayerId): array
    {
        $players = $this->getCollectionFromDb(
            "SELECT player_id AS id, player_score AS score, player_color, player_name AS name FROM player"
        );

        $hand = array_values($this->cards->getPlayerHand($currentPlayerId));

        $indiceCards = $this->cards->getCardsInLocation(self::LOC_INDICE, null, 'card_location_arg');
        $seenByMe = $this->getCollectionFromDb(
            "SELECT card_id FROM rnt_seen_indice WHERE player_id = {$currentPlayerId}"
        );
        $indices = [];
        foreach ($indiceCards as $c) {
            $cid = (int)$c['id'];
            $indices[] = [
                'id' => $cid,
                'pos' => (int)$c['location_arg'],
                'type' => isset($seenByMe[$cid]) ? $c['type'] : null,
                'seen' => isset($seenByMe[$cid]),
            ];
        }

        $myRoleRow = $this->getObjectFromDB(
            "SELECT role FROM rnt_role WHERE player_id = {$currentPlayerId}"
        );
        $myRole = $myRoleRow ? $myRoleRow['role'] : null;

        $seenRoles = $this->getCollectionFromDb(
            "SELECT target_id, (SELECT role FROM rnt_role WHERE player_id = target_id) AS role
             FROM rnt_seen_role WHERE viewer_id = {$currentPlayerId}"
        );

        $playersState = [];
        foreach (array_keys($players) as $pid) {
            $pid = (int)$pid;
            $handCount = count($this->cards->getCardsInLocation(self::LOC_HAND, $pid));
            $cacheL = $this->cards->getCardsInLocation(self::LOC_CACHE_L, $pid);
            $cacheR = $this->cards->getCardsInLocation(self::LOC_CACHE_R, $pid);
            $role = null;
            if ($pid === $currentPlayerId) {
                $role = $myRole;
            } elseif (isset($seenRoles[$pid])) {
                $role = $seenRoles[$pid]['role'];
            }
            $playersState[$pid] = [
                'id' => $pid,
                'hand_count' => $handCount,
                'cache_l' => !empty($cacheL),
                'cache_r' => !empty($cacheR),
                'role' => $role,
            ];
        }

        return [
            'players' => $players,
            'playersState' => $playersState,
            'hand' => $hand,
            'indices' => $indices,
            'myRole' => $myRole,
            'deckCount' => $this->cards->countCardInLocation(self::LOC_DECK),
            'discardCount' => $this->cards->countCardInLocation(self::LOC_DISCARD),
            'discardTop' => $this->cards->getCardOnTop(self::LOC_DISCARD),
            'discardAll' => array_values($this->cards->getCardsInLocation(self::LOC_DISCARD)),
            'shuffleCounter' => (int)$this->bga->globals->get('shuffle_counter'),
            'level' => $this->getLevel(),
            'artefacts' => self::ARTEFACTS,
        ];
    }

    public function getGameProgression(): int
    {
        $total = 52 - 4;
        $remaining = $this->cards->countCardInLocation(self::LOC_DECK)
                   + $this->cards->countCardInLocation(self::LOC_DISCARD);
        if ($total <= 0) {
            return 0;
        }
        $progress = (int)round((1 - $remaining / $total) * 100);
        if ($progress < 0) {
            return 0;
        }
        if ($progress > 100) {
            return 100;
        }
        return $progress;
    }

    public function getPlayerRole(int $playerId): ?string
    {
        $row = $this->getObjectFromDB(
            "SELECT role FROM rnt_role WHERE player_id = {$playerId}"
        );
        return $row ? $row['role'] : null;
    }

    public function getStolenType(): ?string
    {
        $stolen = $this->cards->getCardsInLocation(self::LOC_STOLEN);
        if (empty($stolen)) {
            return null;
        }
        $card = reset($stolen);
        return $card['type'];
    }

    public function getLevel(): int
    {
        $v = $this->bga->tableOptions->get(self::OPT_LEVEL);
        return $v === null ? self::LEVEL_RENARDEAU : (int)$v;
    }

    public function drawIntoHand(int $playerId, int $nbr): array
    {
        $drawn = [];
        for ($i = 0; $i < $nbr; $i++) {
            if ($this->cards->countCardInLocation(self::LOC_DECK) === 0) {
                if (!$this->tryReshuffle($playerId)) {
                    return $drawn;
                }
            }
            $card = $this->cards->pickCardForLocation(self::LOC_DECK, self::LOC_HAND, $playerId);
            if ($card === null) {
                return $drawn;
            }
            $drawn[] = $card;
        }
        return $drawn;
    }

    public function drawUpTo(int $playerId, int $target): array
    {
        $have = count($this->cards->getCardsInLocation(self::LOC_HAND, $playerId));
        if ($have >= $target) {
            return [];
        }
        return $this->drawIntoHand($playerId, $target - $have);
    }

    public function tryReshuffle(int $byPlayerId): bool
    {
        $counter = (int)$this->bga->globals->get('shuffle_counter');
        if ($counter <= 0) {
            $this->bga->globals->set('complices_win_deck', true);
            return false;
        }
        $this->cards->moveAllCardsInLocation(self::LOC_DISCARD, self::LOC_DECK);
        $this->cards->shuffle(self::LOC_DECK);
        $newCounter = $counter - 1;
        $this->bga->globals->set('shuffle_counter', $newCounter);
        $this->bga->globals->set('reshuffles_done', (int)$this->bga->globals->get('reshuffles_done') + 1);
        $this->bga->tableStats->inc('reshuffles', 1);
        $this->bga->notify->all('reshuffle', clienttranslate('${player_name} exhausts the deck; discard is reshuffled into a new deck (counter now ${counter})'), [
            'player_id' => $byPlayerId,
            'player_name' => $this->getPlayerNameById($byPlayerId),
            'counter' => $newCounter,
            'deckCount' => $this->cards->countCardInLocation(self::LOC_DECK),
            'discardCount' => 0,
        ]);
        return true;
    }

    public function notifyHandUpdate(int $playerId): void
    {
        $hand = array_values($this->cards->getPlayerHand($playerId));
        $this->bga->notify->player($playerId, 'handUpdate', '', [
            'hand' => $hand,
        ]);
        $this->bga->notify->all('handCount', '', [
            'player_id' => $playerId,
            'hand_count' => count($hand),
        ]);
        $this->notifyCounts();
    }

    public function notifyCounts(): void
    {
        $this->bga->notify->all('countsUpdate', '', [
            'deckCount' => $this->cards->countCardInLocation(self::LOC_DECK),
            'discardCount' => $this->cards->countCardInLocation(self::LOC_DISCARD),
            'discardTop' => $this->cards->getCardOnTop(self::LOC_DISCARD),
            'shuffleCounter' => (int)$this->bga->globals->get('shuffle_counter'),
        ]);
    }

    public function doRecoverCache(int $playerId, string $side): void
    {
        $loc = $side === 'r' ? self::LOC_CACHE_R : self::LOC_CACHE_L;
        $cards = $this->cards->getCardsInLocation($loc, $playerId);
        if (empty($cards)) {
            throw new \Bga\GameFramework\UserException("Cette cachette est vide");
        }
        $card = reset($cards);
        $cardId = (int)$card['id'];
        $this->cards->moveCard($cardId, self::LOC_HAND, $playerId);
        $this->bga->notify->all('cacheRecovered', clienttranslate('${player_name} recovers an artefact from the ${side_label} cache'), [
            'player_id' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'side' => $side,
            'side_label' => $side === 'r' ? clienttranslate('right') : clienttranslate('left'),
            'i18n' => ['side_label'],
        ]);
        $this->notifyHandUpdate($playerId);
    }

    public function discardCards(array $cardIds): void
    {
        foreach ($cardIds as $cid) {
            $this->cards->moveCard((int)$cid, self::LOC_DISCARD);
        }
    }

    public function validateSimilarCards(int $playerId, array $cardIds, int $expected, ?string $requiredType = null): array
    {
        if (count($cardIds) !== $expected) {
            throw new \Bga\GameFramework\UserException("Nombre de cartes incorrect");
        }
        $unique = array_unique($cardIds);
        if (count($unique) !== $expected) {
            throw new \Bga\GameFramework\UserException("Cartes dupliquées");
        }
        $cards = array_values($this->cards->getCardsFromLocation($cardIds, self::LOC_HAND, $playerId));
        if (count($cards) !== $expected) {
            throw new \Bga\GameFramework\UserException("Certaines cartes ne sont pas dans votre main");
        }
        $types = array_column($cards, 'type');
        if (count(array_unique($types)) !== 1) {
            throw new \Bga\GameFramework\UserException("Les cartes doivent être du même type");
        }
        $type = $types[0];
        if ($requiredType !== null && $type !== $requiredType) {
            throw new \Bga\GameFramework\UserException("Mauvais type d'artéfact");
        }
        if (!in_array($type, self::ARTEFACTS, true)) {
            throw new \Bga\GameFramework\UserException("Type inconnu");
        }
        return $cards;
    }

    public function validateDifferentCards(int $playerId, array $cardIds, int $expected): array
    {
        if (count($cardIds) !== $expected) {
            throw new \Bga\GameFramework\UserException("Nombre de cartes incorrect");
        }
        $unique = array_unique($cardIds);
        if (count($unique) !== $expected) {
            throw new \Bga\GameFramework\UserException("Cartes dupliquées");
        }
        $cards = array_values($this->cards->getCardsFromLocation($cardIds, self::LOC_HAND, $playerId));
        if (count($cards) !== $expected) {
            throw new \Bga\GameFramework\UserException("Certaines cartes ne sont pas dans votre main");
        }
        $types = array_column($cards, 'type');
        if (count(array_unique($types)) !== $expected) {
            throw new \Bga\GameFramework\UserException("Les cartes doivent être toutes différentes");
        }
        return $cards;
    }

    public function hasUnseenIndice(int $playerId): bool
    {
        $seen = $this->getCollectionFromDb(
            "SELECT card_id FROM rnt_seen_indice WHERE player_id = {$playerId}"
        );
        $all = $this->cards->getCardsInLocation(self::LOC_INDICE);
        foreach ($all as $cid => $c) {
            if (!isset($seen[(int)$cid])) {
                return true;
            }
        }
        return false;
    }

    public function hasUnseenRoleTarget(int $playerId): bool
    {
        $seen = $this->getCollectionFromDb(
            "SELECT target_id FROM rnt_seen_role WHERE viewer_id = {$playerId}"
        );
        foreach ($this->loadPlayersBasicInfos() as $pid => $_info) {
            if ((int)$pid === $playerId) {
                continue;
            }
            if (!isset($seen[(int)$pid])) {
                return true;
            }
        }
        return false;
    }

    public function discardHasTwoTypes(): bool
    {
        $cards = $this->cards->getCardsInLocation(self::LOC_DISCARD);
        $types = [];
        foreach ($cards as $c) {
            $types[$c['type']] = true;
            if (count($types) >= 2) {
                return true;
            }
        }
        return false;
    }

    public function anyOtherPlayerHasCards(int $playerId): bool
    {
        foreach ($this->loadPlayersBasicInfos() as $pid => $_info) {
            if ((int)$pid === $playerId) {
                continue;
            }
            if (count($this->cards->getCardsInLocation(self::LOC_HAND, (int)$pid)) > 0) {
                return true;
            }
        }
        return false;
    }

    public function endGameWithWinner(string $side, string $reason): void
    {
        $winningSide = $side === 'detective' ? 1 : 0;
        $this->bga->tableStats->set('winning_side', $winningSide);
        foreach ($this->loadPlayersBasicInfos() as $pid => $_info) {
            $pid = (int)$pid;
            $role = $this->getPlayerRole($pid);
            $won = ($role === $side);
            $this->bga->playerScore->set($pid, $won ? 1 : 0);
        }
        $this->bga->globals->set('end_reason', $reason);
        $this->bga->globals->set('winning_side', $side);
        $this->revealAllRoles();
    }

    public function revealAllRoles(): void
    {
        $rolesByPlayer = [];
        foreach ($this->loadPlayersBasicInfos() as $pid => $_info) {
            $rolesByPlayer[(int)$pid] = $this->getPlayerRole((int)$pid);
        }
        $stolen = $this->getStolenType();
        $this->bga->notify->all('gameReveal', clienttranslate('Revealing all roles and stolen artefact'), [
            'roles' => $rolesByPlayer,
            'stolen' => $stolen,
        ]);
    }
}
