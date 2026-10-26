<?php

namespace App\Actions\PhotoCards;

use App\Models\CardPerson;
use App\Models\Workspace;

/**
 * Removes a person from the card photo library. Cards already rendered keep
 * their image; the next render of a card naming them has no avatar.
 */
class DeleteCardPersonAction
{
    public function handle(Workspace $workspace, int $personId): void
    {
        CardPerson::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($personId)
            ->delete();
    }
}
