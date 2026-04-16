<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\Actions\Types\StringParam;
use Bga\GameFramework\UserException;
use Bga\Games\Renartefact\Game;

class PlayerTurn extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 10, type: StateType::ACTIVE_PLAYER);
    }

    public function onEnteringState(int $activePlayerId): void
    {
    }

    public function getArgs(): array
    {
        $activePlayerId = (int)$this->game->getActivePlayerId();
        return [
            'level' => $this->game->getLevel(),
            'comboPlayedThisTurn' => (bool)$this->game->bga->globals->get('combo_played_this_turn'),
            'myRole' => $this->game->getPlayerRole($activePlayerId),
        ];
    }

    #[PossibleAction]
    public function actPlayPair(#[IntArrayParam] array $cardIds): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        $cards = $this->game->validateSimilarCards($playerId, $cardIds, 2);
        $type = $cards[0]['type'];

        $this->checkPairApplicable($playerId, $type);

        $this->game->discardCards($cardIds);
        $this->game->bga->playerStats->inc('combos_played', 1, $playerId);
        $this->game->bga->globals->set('combo_played_this_turn', true);

        $this->game->bga->notify->all('comboPlayed', clienttranslate('${player_name} plays 2 × ${type_label}'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'card_ids' => array_map(fn($c) => (int)$c['id'], $cards),
            'count' => 2,
            'i18n' => ['type_label'],
        ]);
        $this->game->notifyHandUpdate($playerId);

        return $this->dispatchPairEffect($type);
    }

    #[PossibleAction]
    public function actPlayTriple(#[IntArrayParam] array $cardIds): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        if ($this->game->getLevel() !== Game::LEVEL_RENARD) {
            throw new UserException("Les combinaisons à 3 cartes similaires sont réservées au niveau Renard");
        }
        if ($this->game->bga->globals->get('three_card_played_this_turn')) {
            throw new UserException("Une seule combinaison à 3 cartes par tour");
        }
        $cards = $this->game->validateSimilarCards($playerId, $cardIds, 3);
        $type = $cards[0]['type'];

        $this->checkTripleApplicable($playerId, $type);

        $this->game->discardCards($cardIds);
        $this->game->bga->playerStats->inc('combos_played', 1, $playerId);
        $this->game->bga->globals->set('combo_played_this_turn', true);
        $this->game->bga->globals->set('three_card_played_this_turn', true);

        $this->game->bga->notify->all('comboPlayed', clienttranslate('${player_name} plays 3 × ${type_label}'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'card_ids' => array_map(fn($c) => (int)$c['id'], $cards),
            'count' => 3,
            'i18n' => ['type_label'],
        ]);
        $this->game->notifyHandUpdate($playerId);

        return $this->dispatchTripleEffect($type);
    }

    #[PossibleAction]
    public function actPlayDifferent(#[IntArrayParam] array $cardIds): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        if ($this->game->bga->globals->get('three_card_played_this_turn')) {
            throw new UserException("Une seule combinaison à 3 cartes par tour");
        }
        $cards = $this->game->validateDifferentCards($playerId, $cardIds, 3);
        $types = array_column($cards, 'type');
        $missing = null;
        foreach (Game::ARTEFACTS as $t) {
            if (!in_array($t, $types, true)) {
                $missing = $t;
                break;
            }
        }
        if ($missing === null) {
            throw new UserException("Type manquant introuvable");
        }

        $this->checkPairApplicable($playerId, $missing);

        $this->game->discardCards($cardIds);
        $this->game->bga->playerStats->inc('combos_played', 1, $playerId);
        $this->game->bga->globals->set('combo_played_this_turn', true);
        $this->game->bga->globals->set('three_card_played_this_turn', true);

        $this->game->bga->notify->all('comboPlayed', clienttranslate('${player_name} plays 3 different artefacts (missing: ${type_label}) — triggers the pair effect'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'type' => $missing,
            'type_label' => $this->typeLabel($missing),
            'card_ids' => array_map(fn($c) => (int)$c['id'], $cards),
            'count' => 3,
            'different' => true,
            'i18n' => ['type_label'],
        ]);
        $this->game->notifyHandUpdate($playerId);

        return $this->dispatchPairEffect($missing);
    }

    #[PossibleAction]
    public function actPlayResolution(#[IntArrayParam] array $cardIds): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        if ($this->game->getPlayerRole($playerId) !== Game::ROLE_DETECTIVE) {
            throw new UserException("Seuls les détectives peuvent tenter la résolution");
        }
        $cards = $this->game->validateSimilarCards($playerId, $cardIds, 4);
        $type = $cards[0]['type'];

        $this->game->discardCards($cardIds);
        $this->game->notifyHandUpdate($playerId);

        $stolen = $this->game->getStolenType();
        $correct = ($type === $stolen);
        $this->game->bga->notify->all('resolutionAttempt', clienttranslate('${player_name} plays 4 × ${type_label} to guess the stolen artefact!'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'correct' => $correct,
            'i18n' => ['type_label'],
        ]);

        if ($correct) {
            $this->game->bga->notify->all('resolutionWin', clienttranslate('Correct! The detectives win — the stolen artefact was ${type_label}'), [
                'type' => $type,
                'type_label' => $this->typeLabel($type),
                'i18n' => ['type_label'],
            ]);
            $this->game->endGameWithWinner('detective', 'detective_correct');
        } else {
            $this->game->bga->notify->all('resolutionLose', clienttranslate('Wrong! The complices win — the stolen artefact was not ${type_label}'), [
                'type' => $type,
                'type_label' => $this->typeLabel($type),
                'i18n' => ['type_label'],
            ]);
            $this->game->endGameWithWinner('complice', 'detective_wrong');
        }
        return EndScore::class;
    }

    #[PossibleAction]
    public function actRecoverCache(#[StringParam(enum: ['l', 'r'])] string $side): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        $this->game->doRecoverCache($playerId, $side);
        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actEndTurn(): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        if (!$this->game->bga->globals->get('combo_played_this_turn')) {
            $hand = array_values($this->game->cards->getPlayerHand($playerId));
            if (!empty($hand)) {
                throw new UserException("Vous devez jouer une combinaison ou défausser une carte");
            }
        }
        return NextPlayer::class;
    }

    #[PossibleAction]
    public function actDiscardOne(int $cardId): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        if ($this->game->bga->globals->get('combo_played_this_turn')) {
            throw new UserException("Vous avez déjà joué une combinaison — fin de tour.");
        }
        $cards = $this->game->cards->getCardsFromLocation([$cardId], Game::LOC_HAND, $playerId);
        if (empty($cards)) {
            throw new UserException("Cette carte n'est pas dans votre main");
        }
        $card = reset($cards);
        $this->game->cards->moveCard($cardId, Game::LOC_DISCARD);
        $this->game->bga->notify->all('mandatoryDiscard', clienttranslate('${player_name} discards one card (no combination played)'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'card_type' => $card['type'],
            'card_id' => $cardId,
            'discardCount' => $this->game->cards->countCardInLocation(Game::LOC_DISCARD),
        ]);
        $this->game->notifyHandUpdate($playerId);
        return NextPlayer::class;
    }

    function zombie(int $playerId)
    {
        $hand = array_values($this->game->cards->getPlayerHand($playerId));
        if (!empty($hand)) {
            $pick = $hand[array_rand($hand)];
            $this->game->cards->moveCard((int)$pick['id'], Game::LOC_DISCARD);
            $this->game->notifyHandUpdate($playerId);
        }
        return NextPlayer::class;
    }

    // TODO remove before release — debug actions for test scenarios
    #[PossibleAction]
    public function actDebugSetHand(#[StringParam(enum: ['canne', 'sablier', 'chapeau', 'loupe'])] string $type): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        $hand = array_values($this->game->cards->getPlayerHand($playerId));
        foreach ($hand as $c) {
            $this->game->cards->moveCard((int)$c['id'], Game::LOC_DISCARD);
        }
        $fromDeck = $this->game->cards->getCardsOfTypeInLocation($type, null, Game::LOC_DECK);
        $fromDiscard = $this->game->cards->getCardsOfTypeInLocation($type, null, Game::LOC_DISCARD);
        $pool = array_merge(array_values($fromDeck), array_values($fromDiscard));
        $count = 0;
        foreach ($pool as $c) {
            if ($count >= 4) break;
            $this->game->cards->moveCard((int)$c['id'], Game::LOC_HAND, $playerId);
            $count++;
        }
        $this->game->notifyHandUpdate($playerId);
        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actDebugExhaustDeck(): string
    {
        $cards = $this->game->cards->getCardsInLocation(Game::LOC_DECK);
        foreach ($cards as $c) {
            $this->game->cards->moveCard((int)$c['id'], Game::LOC_DISCARD);
        }
        $this->game->notifyCounts();
        return PlayerTurn::class;
    }

    private function checkPairApplicable(int $playerId, string $type): void
    {
        switch ($type) {
            case Game::ART_LOUPE:
                if (!$this->game->hasUnseenIndice($playerId)) {
                    throw new UserException("Vous avez déjà vu tous les indices");
                }
                break;
            case Game::ART_SABLIER:
                if ((int)$this->game->cards->countCardInLocation(Game::LOC_DECK) <= 0
                    && (int)$this->game->bga->globals->get('shuffle_counter') <= 0
                    && (int)$this->game->cards->countCardInLocation(Game::LOC_DISCARD) <= 0) {
                    throw new UserException("Plus de cartes à piocher");
                }
                break;
            case Game::ART_CANNE:
                if (!$this->game->anyOtherPlayerHasCards($playerId)) {
                    throw new UserException("Aucun autre joueur n'a de cartes en main");
                }
                break;
            case Game::ART_CHAPEAU:
                $leftUsed = count($this->game->cards->getCardsInLocation(Game::LOC_CACHE_L, $playerId)) > 0;
                if ($leftUsed) {
                    throw new UserException("La cachette gauche est déjà occupée");
                }
                $handCount = count($this->game->cards->getCardsInLocation(Game::LOC_HAND, $playerId));
                if ($handCount < 3) {
                    throw new UserException("Il vous faut au moins 1 autre carte à cacher");
                }
                break;
        }
    }

    private function checkTripleApplicable(int $playerId, string $type): void
    {
        switch ($type) {
            case Game::ART_LOUPE:
                if (!$this->game->hasUnseenRoleTarget($playerId)) {
                    throw new UserException("Tous les rôles adverses sont déjà connus");
                }
                break;
            case Game::ART_SABLIER:
                $hand = count($this->game->cards->getCardsInLocation(Game::LOC_HAND, $playerId));
                if ($hand > 5) {
                    throw new UserException("Vous avez déjà plus de 5 cartes");
                }
                break;
            case Game::ART_CANNE:
                if (!$this->game->discardHasTwoTypes()) {
                    throw new UserException("La défausse ne contient pas 2 types différents");
                }
                break;
            case Game::ART_CHAPEAU:
                $rightUsed = count($this->game->cards->getCardsInLocation(Game::LOC_CACHE_R, $playerId)) > 0;
                if ($rightUsed) {
                    throw new UserException("La cachette droite est déjà occupée");
                }
                $handCount = count($this->game->cards->getCardsInLocation(Game::LOC_HAND, $playerId));
                if ($handCount < 4) {
                    throw new UserException("Il vous faut au moins 1 autre carte à cacher");
                }
                break;
        }
    }

    private function dispatchPairEffect(string $type): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        switch ($type) {
            case Game::ART_SABLIER:
                $this->game->drawIntoHand($playerId, 1);
                $this->game->notifyHandUpdate($playerId);
                if ($this->game->isGameEnded()) return EndScore::class;
                return PlayerTurn::class;
            case Game::ART_LOUPE:
                $this->game->bga->globals->set('pending_effect', 'view_indice');
                return ViewIndice::class;
            case Game::ART_CANNE:
                $this->game->bga->globals->set('pending_effect', 'pick_from_player');
                return PickCardFromPlayer::class;
            case Game::ART_CHAPEAU:
                $this->game->bga->globals->set('pending_cache_side', 'l');
                return ChooseCacheCard::class;
        }
        return PlayerTurn::class;
    }

    private function dispatchTripleEffect(string $type): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        switch ($type) {
            case Game::ART_SABLIER:
                $this->game->drawUpTo($playerId, 5);
                $this->game->notifyHandUpdate($playerId);
                if ($this->game->isGameEnded()) return EndScore::class;
                return PlayerTurn::class;
            case Game::ART_LOUPE:
                return ViewRole::class;
            case Game::ART_CANNE:
                return PickFromDiscard::class;
            case Game::ART_CHAPEAU:
                $this->game->bga->globals->set('pending_cache_side', 'r');
                return ChooseCacheCard::class;
        }
        return PlayerTurn::class;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            Game::ART_CANNE => clienttranslate('canne'),
            Game::ART_SABLIER => clienttranslate('sablier'),
            Game::ART_CHAPEAU => clienttranslate('chapeau'),
            Game::ART_LOUPE => clienttranslate('loupe'),
            default => $type,
        };
    }
}
