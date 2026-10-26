<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo card for a post: {headline, source, media_asset_id, rendered_at}.
 * The rendered PNG is a MediaAsset referenced from here rather than an
 * attachment, so the existing PostSyncer media resolution never picks it up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->jsonb('photo_card')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('photo_card');
        });
    }
};
