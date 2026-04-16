<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\Renartefact\Game;

class TurnStart extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 15, type: StateType::GAME);
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        $this->game->bga->globals->set('needs_draw_at_turn_start', false);
        $this->game->bga->globals->set('combo_played_this_turn', false);
        $this->game->bga->globals->set('triple_played_this_turn', false);
        $this->game->bga->globals->set('different_played_this_turn', false);
        $this->game->bga->tableStats->inc('turns_total', 1);

        $have = count($this->game->cards->getCardsInLocation(Game::LOC_HAND, $activePlayerId));
        if ($have < 5) {
            $this->game->drawUpTo($activePlayerId, 5);
            $this->game->notifyHandUpdate($activePlayerId);
        }
        if ($this->game->isGameEnded()) {
            return EndScore::class;
        }
        return PlayerTurn::class;
    }
}
