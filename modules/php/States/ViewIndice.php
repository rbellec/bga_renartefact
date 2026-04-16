<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\Renartefact\Game;

class ViewIndice extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 50, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(): array
    {
        $activePlayerId = (int)$this->game->getActivePlayerId();
        $seen = $this->game->getCollectionFromDb(
            "SELECT card_id FROM rnt_seen_indice WHERE player_id = {$activePlayerId}"
        );
        $available = [];
        foreach ($this->game->cards->getCardsInLocation(Game::LOC_INDICE) as $c) {
            $cid = (int)$c['id'];
            if (!isset($seen[$cid])) {
                $available[] = ['id' => $cid, 'pos' => (int)$c['location_arg']];
            }
        }
        return ['availableIndices' => $available];
    }

    #[PossibleAction]
    public function actViewIndice(int $indiceId): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        $card = $this->game->cards->getCard($indiceId);
        if (!$card || $card['location'] !== Game::LOC_INDICE) {
            throw new UserException("Cet indice n'existe pas");
        }
        $already = $this->game->getObjectFromDB(
            "SELECT 1 AS x FROM rnt_seen_indice WHERE player_id = {$playerId} AND card_id = {$indiceId}"
        );
        if ($already) {
            throw new UserException("Vous avez déjà vu cet indice");
        }
        $this->game->DbQuery(
            "INSERT INTO rnt_seen_indice (player_id, card_id) VALUES ({$playerId}, {$indiceId})"
        );
        $this->game->bga->playerStats->inc('indices_seen', 1, $playerId);

        $this->game->bga->notify->player($playerId, 'indiceRevealed', clienttranslate('You peek at an indice: ${type_label}'), [
            'card_id' => $indiceId,
            'pos' => (int)$card['location_arg'],
            'type' => $card['type'],
            'type_label' => $card['type'],
            'i18n' => ['type_label'],
        ]);
        $this->game->bga->notify->all('indicePeeked', clienttranslate('${player_name} peeks at an indice'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'pos' => (int)$card['location_arg'],
        ]);
        return PlayerTurn::class;
    }

    function zombie(int $playerId)
    {
        return NextPlayer::class;
    }
}
