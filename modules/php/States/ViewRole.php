<?php
declare(strict_types=1);

namespace Bga\Games\Renartefact\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\Renartefact\Game;

class ViewRole extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game, id: 60, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(): array
    {
        $activePlayerId = (int)$this->game->getActivePlayerId();
        $seen = $this->game->getCollectionFromDb(
            "SELECT target_id FROM rnt_seen_role WHERE viewer_id = {$activePlayerId}"
        );
        $available = [];
        foreach ($this->game->loadPlayersBasicInfos() as $pid => $_info) {
            $pid = (int)$pid;
            if ($pid === $activePlayerId) {
                continue;
            }
            if (!isset($seen[$pid])) {
                $available[] = $pid;
            }
        }
        return ['availablePlayers' => $available];
    }

    #[PossibleAction]
    public function actViewRole(int $targetPlayerId): string
    {
        $playerId = (int)$this->game->getActivePlayerId();
        if ($targetPlayerId === $playerId) {
            throw new UserException("Vous ne pouvez pas vous regarder vous-même");
        }
        $role = $this->game->getPlayerRole($targetPlayerId);
        if ($role === null) {
            throw new UserException("Joueur introuvable");
        }
        $already = $this->game->getObjectFromDB(
            "SELECT 1 AS x FROM rnt_seen_role WHERE viewer_id = {$playerId} AND target_id = {$targetPlayerId}"
        );
        if (!$already) {
            $this->game->DbQuery(
                "INSERT INTO rnt_seen_role (viewer_id, target_id) VALUES ({$playerId}, {$targetPlayerId})"
            );
            $this->game->bga->playerStats->inc('roles_seen', 1, $playerId);
        }

        $this->game->bga->notify->player($playerId, 'roleRevealed', clienttranslate('You peek at ${target_name}\'s role: ${role_label}'), [
            'target_id' => $targetPlayerId,
            'target_name' => $this->game->getPlayerNameById($targetPlayerId),
            'role' => $role,
            'role_label' => $role,
            'i18n' => ['role_label'],
        ]);
        $this->game->bga->notify->all('rolePeeked', clienttranslate('${player_name} peeks at ${target_name}\'s role'), [
            'player_id' => $playerId,
            'player_name' => $this->game->getPlayerNameById($playerId),
            'target_id' => $targetPlayerId,
            'target_name' => $this->game->getPlayerNameById($targetPlayerId),
        ]);
        return PlayerTurn::class;
    }
}
