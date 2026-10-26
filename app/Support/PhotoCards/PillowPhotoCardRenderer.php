<?php

namespace App\Support\PhotoCards;

use App\Contracts\PhotoCardRenderer;
use App\Data\PhotoCards\PhotoCardData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JsonException;

/**
 * Renders cards with resources/photocards/render.py (Pillow + libraqm). The
 * process runs only for the render itself, so it costs no memory between
 * cards.
 */
class PillowPhotoCardRenderer implements PhotoCardRenderer
{
    public function render(PhotoCardData $card): string
    {
        $directory = sys_get_temp_dir().'/cm-photocard-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new PhotoCardRenderException('Could not create a temporary directory for the card.');
        }

        $specPath = $directory.'/spec.json';
        $outPath = $directory.'/card.png';

        try {
            try {
                file_put_contents($specPath, json_encode($card->toRenderSpec(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            } catch (JsonException) {
                throw new PhotoCardRenderException('The card text could not be encoded.');
            }

            $result = Process::timeout((int) config('photo_cards.timeout'))->run([
                (string) config('photo_cards.python'),
                (string) config('photo_cards.script'),
                $specPath,
                $outPath,
            ]);

            if (! $result->successful() || ! is_file($outPath)) {
                Log::warning('Photo card render failed.', [
                    'exit_code' => $result->exitCode(),
                    'error' => mb_substr($result->errorOutput(), 0, 2000),
                ]);

                throw new PhotoCardRenderException($result->exitCode() === 3
                    ? 'Pillow on this server has no libraqm, so Bangla cannot be drawn. Install libraqm0.'
                    : 'The card renderer failed. Check that python3 and Pillow are installed.');
            }

            $png = file_get_contents($outPath);

            if ($png === false || $png === '') {
                throw new PhotoCardRenderException('The card renderer produced an empty image.');
            }

            return $png;
        } finally {
            @unlink($specPath);
            @unlink($outPath);
            @rmdir($directory);
        }
    }
}
