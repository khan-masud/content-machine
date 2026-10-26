<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A workspace's photo library of people for card avatars. When a card
 * headline names one of them (the name or an alias), their photo is drawn as
 * a round avatar on the card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->jsonb('aliases')->default('[]');
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_people');
    }
};
