<?php

namespace App\Actions\Telegram;

use App\Jobs\GenerateTelegramPostJob;
use App\Jobs\ResolveScratchpadLinkJob;
use App\Jobs\TranscribeVoiceNoteJob;
use App\Models\ScratchpadEntry;
use App\Models\TelegramBotConfig;
use App\Models\TelegramBotLink;
use App\Models\TelegramPostRequest;
use App\Models\Transcription;
use App\Models\Workspace;
use App\Support\Posts\PostCategories;
use App\Support\Telegram\TelegramUpdateKey;
use Illuminate\Support\Facades\DB;

/**
 * Starts a post request from a command, a pending /post prompt, or a media
 * caption. Capture still lands in Scratch Pad first, preserving the original
 * source even when generation later fails. A leading category word
 * ("/post sarcasm https://...") is taken off the instruction and kept on the
 * request for generation. /card starts the same flow in card mode, where the
 * person's text becomes the card headline as written.
 */
class StartTelegramPostAction
{
    /** Words that turn /card into /card avatar. */
    private const AVATAR_WORDS = ['avatar', 'অ্যাভাটার', 'অবতার'];

    public function __construct(
        private readonly CaptureTelegramMessageAction $captureTelegramMessageAction,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(
        TelegramBotConfig $config,
        TelegramBotLink $link,
        int $chatId,
        int $telegramUserId,
        array $update,
        ?string $instruction = null,
        ?TelegramPostRequest $pendingRequest = null,
        string $mode = TelegramPostRequest::MODE_POST,
    ): TelegramPostRequest {
        $message = $update['message'] ?? null;
        if (! is_array($message)
            || ! is_array($message['chat'] ?? null)
            || ($message['chat']['type'] ?? null) !== 'private'
        ) {
            throw new \RuntimeException('Telegram post commands are available in private chats only.');
        }

        if ($link->telegram_bot_config_id !== $config->id || $link->telegram_user_id !== $telegramUserId) {
            throw new \RuntimeException('This Telegram account is not linked to this bot.');
        }

        [$category, $instruction] = PostCategories::splitLeading($instruction);

        // "/card avatar <headline>" (the keyword before or after a category).
        if ($mode === TelegramPostRequest::MODE_CARD && $this->startsWithAvatar($instruction)) {
            $mode = TelegramPostRequest::MODE_CARD_AVATAR;
            $instruction = $this->withoutFirstWord($instruction);
            if ($category === null) {
                [$category, $instruction] = PostCategories::splitLeading($instruction);
            }
        }

        return DB::transaction(function () use (
            $config,
            $link,
            $chatId,
            $telegramUserId,
            $update,
            $instruction,
            $category,
            $pendingRequest,
            $mode,
        ): TelegramPostRequest {
            // Serialize one Telegram bot's post prompts. This prevents two
            // concurrent bare /post commands from stranding prompts or
            // capturing a second message before the first request is linked.
            Workspace::query()
                ->whereKey($config->workspace_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedConfig = TelegramBotConfig::query()
                ->whereKey($config->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($link->telegram_bot_config_id !== $lockedConfig->id
                || $link->telegram_user_id !== $telegramUserId
            ) {
                throw new \RuntimeException('This Telegram account is not linked to this bot.');
            }

            $telegramUpdateKey = TelegramUpdateKey::from($lockedConfig, $update);
            if ($telegramUpdateKey !== null) {
                $existingRequest = TelegramPostRequest::query()
                    ->where('telegram_update_key', $telegramUpdateKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingRequest !== null) {
                    if ($existingRequest->workspace_id !== $lockedConfig->workspace_id
                        || $existingRequest->telegram_bot_config_id !== $lockedConfig->id
                        || $existingRequest->telegram_user_id !== $telegramUserId
                        || $existingRequest->telegram_chat_id !== $chatId
                    ) {
                        throw new \RuntimeException('This Telegram post request belongs to another conversation.');
                    }

                    return $existingRequest;
                }
            }

            $request = null;

            if ($pendingRequest !== null) {
                $request = TelegramPostRequest::query()
                    ->whereKey($pendingRequest->id)
                    ->lockForUpdate()
                    ->first();

                if ($request === null
                    || $request->state !== TelegramPostRequest::AWAITING_INPUT
                    || $request->workspace_id !== $lockedConfig->workspace_id
                    || $request->telegram_bot_config_id !== $lockedConfig->id
                    || $request->telegram_user_id !== $telegramUserId
                    || $request->telegram_chat_id !== $chatId
                    || ($request->webhook_generation !== null
                        && $request->webhook_generation !== $lockedConfig->webhook_generation)
                ) {
                    throw new \RuntimeException('This Telegram post request is no longer awaiting input.');
                }

                if ($request->webhook_generation === null && $lockedConfig->webhook_generation !== null) {
                    $request->forceFill([
                        'webhook_generation' => $lockedConfig->webhook_generation,
                    ])->save();
                }
            } else {
                TelegramPostRequest::query()
                    ->forTelegram($lockedConfig, $telegramUserId, $chatId)
                    ->where('state', TelegramPostRequest::AWAITING_INPUT)
                    ->update([
                        'state' => TelegramPostRequest::CANCELLED,
                        'cancelled_at' => now(),
                    ]);
            }

            $sourceUpdate = $instruction === null || $instruction === ''
                ? $this->withoutBarePostCommand($update)
                : $this->withInstruction($update, $instruction);

            // Enrichment is queued below, after the request points at the
            // capture. A link/transcription worker can therefore never finish
            // before it has a request to advance or fail.
            $entry = $this->captureTelegramMessageAction->handle(
                $lockedConfig,
                $sourceUpdate,
                false,
                false,
                $telegramUpdateKey,
            );

            // A bare /post prompt keeps the prompt update's key, while the
            // follow-up source has its own key. On a replay after the request
            // update committed, the pending lookup above no longer finds it;
            // the captured source is still the durable idempotency bridge.
            if ($request === null && $entry !== null) {
                $existingRequest = TelegramPostRequest::query()
                    ->where('source_scratchpad_entry_id', $entry->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingRequest !== null) {
                    if ($existingRequest->workspace_id !== $lockedConfig->workspace_id
                        || $existingRequest->telegram_bot_config_id !== $lockedConfig->id
                        || $existingRequest->telegram_user_id !== $telegramUserId
                        || $existingRequest->telegram_chat_id !== $chatId
                    ) {
                        throw new \RuntimeException('This Telegram post request belongs to another conversation.');
                    }

                    return $existingRequest;
                }
            }

            $request ??= TelegramPostRequest::create([
                'workspace_id' => $lockedConfig->workspace_id,
                'telegram_bot_config_id' => $lockedConfig->id,
                'source_scratchpad_entry_id' => null,
                'telegram_user_id' => $telegramUserId,
                'telegram_chat_id' => $chatId,
                'telegram_update_key' => $telegramUpdateKey,
                'webhook_generation' => $lockedConfig->webhook_generation,
                'state' => TelegramPostRequest::AWAITING_INPUT,
                'instruction' => null,
                'category' => $category,
                'mode' => $mode,
            ]);

            if ($entry === null) {
                return $request;
            }

            // A follow-up source to a bare "/post sarcasm" carries no category
            // of its own, so it keeps the one the prompt asked for.
            $updated = TelegramPostRequest::query()
                ->whereKey($request->id)
                ->where('state', TelegramPostRequest::AWAITING_INPUT)
                ->update(array_merge([
                    'source_scratchpad_entry_id' => $entry->id,
                    'state' => TelegramPostRequest::GENERATING,
                    'instruction' => $instruction !== null && $instruction !== '' ? $instruction : null,
                    'webhook_generation' => $lockedConfig->webhook_generation,
                    'error_message' => null,
                    'cancelled_at' => null,
                ], $category !== null ? ['category' => $category] : []));

            if ($updated === 0) {
                return $request->fresh() ?? $request;
            }

            $request->refresh();
            $this->queueSourceWork($entry, $request);

            return $request;
        });
    }

    private function startsWithAvatar(?string $instruction): bool
    {
        $first = mb_strtolower(preg_split('/\s+/u', trim((string) $instruction))[0] ?? '');

        return in_array($first, self::AVATAR_WORDS, true);
    }

    private function withoutFirstWord(?string $instruction): string
    {
        $parts = preg_split('/\s+/u', trim((string) $instruction), 2);

        return trim($parts[1] ?? '');
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    private function withInstruction(array $update, string $instruction): array
    {
        $message = $update['message'] ?? [];
        if (! is_array($message)) {
            return $update;
        }

        if (isset($message['photo'], $message['caption']) || isset($message['photo'])) {
            $message['caption'] = $instruction;
        } elseif (isset($message['voice'], $message['caption']) || isset($message['voice']) || isset($message['audio'])) {
            $message['caption'] = $instruction;
        } else {
            $message['text'] = $instruction;
        }

        $update['message'] = $message;

        return $update;
    }

    /**
     * Remove the command itself before passing a bare `/post` or `/card` (optionally
     * followed by just a category word) to the normal capture action. Media
     * messages keep their photo/audio payload while the command caption is
     * removed; a text-only command becomes an empty update and therefore
     * creates an awaiting-input request.
     *
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    private function withoutBarePostCommand(array $update): array
    {
        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return $update;
        }

        $commandText = $message['text'] ?? $message['caption'] ?? null;

        if (! is_string($commandText)
            || preg_match('/^\/(?:post|card)(?:@[A-Za-z0-9_]+)?((?:\s+\S+){0,2})$/iu', trim($commandText), $matches) !== 1
        ) {
            return $update;
        }

        // Only a category word and/or the avatar keyword may follow it.
        foreach (preg_split('/\s+/u', trim($matches[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (PostCategories::fromAlias($word) === null && ! in_array(mb_strtolower($word), self::AVATAR_WORDS, true)) {
                return $update;
            }
        }

        if (isset($message['photo']) || isset($message['voice']) || isset($message['audio'])) {
            unset($message['caption']);
        } else {
            unset($message['text']);
        }

        $update['message'] = $message;

        return $update;
    }

    private function canGenerateNow(ScratchpadEntry $entry): bool
    {
        $entry = $entry->fresh() ?? $entry;

        if ($entry->kind === 'voice') {
            return $entry->transcriptions()->where('status', 'done')->exists();
        }

        if ($entry->kind === 'link') {
            return ($entry->meta['resolved_kind'] ?? null) !== null
                && ($entry->meta['resolved_kind'] ?? null) !== 'unresolved';
        }

        return true;
    }

    private function queueSourceWork(ScratchpadEntry $entry, TelegramPostRequest $request): void
    {
        $leaseId = (new ClaimTelegramPostWorkAction)->claim($request->id);

        if ($leaseId === null) {
            return;
        }

        if ($entry->kind === 'link') {
            ResolveScratchpadLinkJob::dispatch($entry, $request->id, $leaseId)->afterCommit();

            return;
        }

        if ($entry->kind === 'voice') {
            $transcription = $entry->transcriptions()->first();

            if (! $transcription instanceof Transcription) {
                throw new \RuntimeException('The audio transcription record is missing.');
            }

            TranscribeVoiceNoteJob::dispatch($transcription, $request->id, $leaseId)->afterCommit();

            return;
        }

        if ($this->canGenerateNow($entry)) {
            GenerateTelegramPostJob::dispatch($request->id, $leaseId)->afterCommit();
        } else {
            (new ClaimTelegramPostWorkAction)->release($request->id, $leaseId);
        }
    }
}
