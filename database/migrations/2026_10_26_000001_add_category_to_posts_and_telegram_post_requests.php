<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial category (recent, politics, analysis, ...; see
 * config/post_categories.php) on posts, and the category a Telegram /post
 * command asked for, kept on the request until the draft is generated.
 * Both are nullable: existing posts have no category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('category', 32)->nullable()->after('template');
            $table->index(['workspace_id', 'category']);
        });

        Schema::table('telegram_post_requests', function (Blueprint $table): void {
            $table->string('category', 32)->nullable()->after('instruction');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_post_requests', function (Blueprint $table): void {
            $table->dropColumn('category');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
