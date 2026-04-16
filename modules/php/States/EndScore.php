<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\Renartefact\Game;

const ST_END_GAME = 99;

class EndScore extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 98, type: StateType::GAME);
    }

    public function onEnteringState()
    {
        return ST_END_GAME;
    }
}
