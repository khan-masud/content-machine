<?php

namespace App\Support\Telegram;

/**
 * Sends one image to a chat. Kept apart from TelegramClientContract so the
 * text-only clients and test doubles need not implement it.
 */
interface TelegramPhotoSenderContract
{
    public function sendPhoto(
        string $botToken,
        int $chatId,
        string $photoContents,
        string $filename,
        ?string $caption = null,
    ): TelegramApiResult;
}
