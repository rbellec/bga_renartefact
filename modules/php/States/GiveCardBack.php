<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\Renartefact\Game;

class GiveCardBack extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 21, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(): array
    {
        $activePlayerId = (int)$this->game->getActivePlayerId();
        $target = (int)$this->game->bga->globals->get('canne2_target');
        return [
            'target' => $target,
            'myHand' => array_values($this->game->cards->getPlayerHand($activePlayerId)),
        ];
    }

    #[PossibleAction]
    public function actGiveBack(int $cardId): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        $target = (int)$this->game->bga->globals->get('canne2_target');
        if ($target <= 0) {
            throw new UserException("Cible introuvable");
        }
        $cards = $this->game->cards->getCardsFromLocation([$cardId], Game::LOC_HAND, $playerId);
        if (empty($cards)) {
            throw new UserException("Cette carte n'est pas dans votre main");
        }
        $this->game->cards->moveCard($cardId, Game::LOC_HAND, $target);
        $this->game->bga->notify->all('cardGivenBack', clienttranslate('${player_name} gives a card back to ${target_name}'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'target_id' => $target,
            'target_name' => $this->game->getPlayerNameById($target),
        ]);
        $this->game->notifyHandUpdate($playerId);
        $this->game->notifyHandUpdate($target);
        $this->game->bga->globals->set('canne2_target', 0);
        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actSkipGiveBack(): string
    {
        $this->game->bga->globals->set('canne2_target', 0);
        return PlayerTurn::class;
    }

    function zombie(int $playerId)
    {
        $this->game->bga->globals->set('canne2_target', 0);
        return NextPlayer::class;
    }
}
