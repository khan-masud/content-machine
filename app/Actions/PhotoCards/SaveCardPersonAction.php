<?php

namespace App\Actions\PhotoCards;

use App\Actions\Scratchpad\Concerns\ResolvesMediaAsset;
use App\Data\PhotoCards\CardPersonData;
use App\Models\CardPerson;
use App\Models\User;
use App\Models\Workspace;

/**
 * Adds a person to the card photo library. Saving a name that is already
 * there replaces that person's photo and aliases.
 */
class SaveCardPersonAction
{
    use ResolvesMediaAsset;

    public function handle(Workspace $workspace, ?User $savedBy, CardPersonData $data): CardPerson
    {
        $media = $this->resolveMediaAsset($workspace, $savedBy, $data->photo, 'image');

        return CardPerson::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'name' => $data->name],
            ['aliases' => $data->aliases, 'media_asset_id' => $media->id],
        );
    }
}
