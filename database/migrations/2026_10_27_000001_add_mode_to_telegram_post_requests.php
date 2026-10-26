<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a Telegram request makes: "post" (/post, the AI writes everything) or
 * "card" (/card, the person's text is the card headline as written).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_post_requests', function (Blueprint $table): void {
            $table->string('mode', 16)->default('post')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_post_requests', function (Blueprint $table): void {
            $table->dropColumn('mode');
        });
    }
};
