<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\Renartefact\Game;

class PickCardFromPlayer extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 20, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(): array
    {
        $activePlayerId = (int)$this->game->getActivePlayerId();
        $targets = [];
        foreach ($this->game->loadPlayersBasicInfos() as $pid => $_info) {
            $pid = (int)$pid;
            if ($pid === $activePlayerId) {
                continue;
            }
            $count = count($this->game->cards->getCardsInLocation(Game::LOC_HAND, $pid));
            if ($count > 0) {
                $targets[] = ['id' => $pid, 'hand_count' => $count];
            }
        }
        return ['targets' => $targets];
    }

    #[PossibleAction]
    public function actPickBlind(int $targetPlayerId, int $position): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        if ($targetPlayerId === $playerId) {
            throw new UserException("Vous ne pouvez pas vous voler vous-même");
        }
        $hand = array_values($this->game->cards->getCardsInLocation(Game::LOC_HAND, $targetPlayerId));
        if (empty($hand)) {
            throw new UserException("La main cible est vide");
        }
        if ($position < 0 || $position >= count($hand)) {
            $position = 0;
        }
        $card = $hand[$position];
        $cardId = (int)$card['id'];
        $this->game->cards->moveCard($cardId, Game::LOC_HAND, $playerId);
        $this->game->bga->playerStats->inc('cards_stolen', 1, $playerId);

        $this->game->bga->notify->all('cardStolen', clienttranslate('${player_name} steals a card from ${target_name}'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'target_id' => $targetPlayerId,
            'target_name' => $this->game->getPlayerNameById($targetPlayerId),
        ]);
        $this->game->notifyHandUpdate($playerId);
        $this->game->notifyHandUpdate($targetPlayerId);

        $this->game->bga->globals->set('canne2_target', $targetPlayerId);
        return GiveCardBack::class;
    }
}
