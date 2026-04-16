<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\Renartefact\Game;

class ChooseCacheCard extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 40, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(): array
    {
        $activePlayerId = (int)$this->game->getActivePlayerId();
        $side = $this->game->bga->globals->get('pending_cache_side') ?: 'l';
        return [
            'side' => $side,
            'myHand' => array_values($this->game->cards->getPlayerHand($activePlayerId)),
        ];
    }

    #[PossibleAction]
    public function actPlaceCache(int $cardId): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        $side = $this->game->bga->globals->get('pending_cache_side') ?: 'l';
        $loc = $side === 'r' ? Game::LOC_CACHE_R : Game::LOC_CACHE_L;

        $existing = $this->game->cards->getCardsInLocation($loc, $playerId);
        if (!empty($existing)) {
            throw new UserException("Cette cachette est déjà occupée");
        }
        $cards = $this->game->cards->getCardsFromLocation([$cardId], Game::LOC_HAND, $playerId);
        if (empty($cards)) {
            throw new UserException("Cette carte n'est pas dans votre main");
        }
        $this->game->cards->moveCard($cardId, $loc, $playerId);

        $this->game->bga->notify->all('cacheHidden', clienttranslate('${player_name} hides an artefact in the ${side_label} cache'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'side' => $side,
            'side_label' => $side === 'r' ? clienttranslate('right') : clienttranslate('left'),
            'card_id' => $cardId,
            'i18n' => ['side_label'],
        ]);
        $this->game->notifyHandUpdate($playerId);
        $this->game->bga->globals->set('pending_cache_side', '');
        return PlayerTurn::class;
    }

    function zombie(int $playerId)
    {
        $this->game->bga->globals->set('pending_cache_side', '');
        return NextPlayer::class;
    }
}
