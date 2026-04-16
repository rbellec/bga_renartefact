<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\UserException;
use Bga\Games\Renartefact\Game;

class PickFromDiscard extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 30, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(): array
    {
        $byType = [];
        foreach ($this->game->cards->getCardsInLocation(Game::LOC_DISCARD) as $c) {
            $byType[$c['type']][] = (int)$c['id'];
        }
        return ['discardByType' => $byType];
    }

    #[PossibleAction]
    public function actPickTwoFromDiscard(#[IntArrayParam] array $cardIds): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        if (count($cardIds) !== 2 || count(array_unique($cardIds)) !== 2) {
            throw new UserException("Sélectionnez 2 cartes différentes");
        }
        $cards = $this->game->cards->getCardsFromLocation($cardIds, Game::LOC_DISCARD);
        if (count($cards) !== 2) {
            throw new UserException("Certaines cartes ne sont pas dans la défausse");
        }
        $types = array_values(array_unique(array_column($cards, 'type')));
        if (count($types) !== 2) {
            throw new UserException("Les 2 cartes doivent être de types différents");
        }
        foreach ($cardIds as $cid) {
            $this->game->cards->moveCard((int)$cid, Game::LOC_HAND, $playerId);
        }
        $this->game->bga->notify->all('cardsRecovered', clienttranslate('${player_name} recovers 2 cards from the discard'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'types' => $types,
            'discardCount' => $this->game->cards->countCardInLocation(Game::LOC_DISCARD),
        ]);
        $this->game->notifyHandUpdate($playerId);
        return PlayerTurn::class;
    }
}
