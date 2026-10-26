<?php

use App\Actions\Telegram\SyncTelegramBotCommandsAction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The /post menu description now mentions the optional category, and
     * /recard is new. Re-sync already-connected bots so their menu shows it.
     */
    public function up(): void
    {
        app(SyncTelegramBotCommandsAction::class)->handle();
    }

    public function down(): void
    {
        // The external Telegram command menu has no transactional rollback.
    }
};
