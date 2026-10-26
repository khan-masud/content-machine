<?php

namespace Database\Factories;

use App\Models\CardPerson;
use App\Models\MediaAsset;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardPerson>
 */
class CardPersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->name(),
            'aliases' => [],
            'media_asset_id' => fn (array $attributes) => MediaAsset::factory()->create(['workspace_id' => $attributes['workspace_id']])->id,
        ];
    }
}
