<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\Renartefact\Game;

class NextPlayer extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 90, type: StateType::GAME, updateGameProgression: true);
    }

    public function onEnteringState(int $activePlayerId): mixed
    {
        $this->game->giveExtraTime($activePlayerId);
        $this->game->activeNextPlayer();
        $this->game->bga->globals->set('needs_draw_at_turn_start', true);
        $this->game->bga->globals->set('combo_played_this_turn', false);
        $this->game->bga->globals->set('triple_played_this_turn', false);
        $this->game->bga->globals->set('different_played_this_turn', false);
        return TurnStart::class;
    }
}
