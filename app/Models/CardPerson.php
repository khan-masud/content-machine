<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CardPersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person in a workspace's card photo library. Queried explicitly by
 * workspace_id: cards render in queue jobs, outside a current workspace.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property list<string> $aliases
 * @property int $media_asset_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read MediaAsset|null $mediaAsset
 */
class CardPerson extends Model
{
    /** @use HasFactory<CardPersonFactory> */
    use HasFactory;

    protected $table = 'card_people';

    protected $fillable = ['workspace_id', 'name', 'aliases', 'media_asset_id'];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
