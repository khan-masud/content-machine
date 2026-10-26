<?php

namespace App\Concerns;

use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Reads a streamed HTTP response body, giving up (null) as soon as it would
 * exceed $maxBytes, so a hostile or huge remote file never fills memory.
 */
trait ReadsBodyWithinLimit
{
    private function readBodyWithinLimit(Response $response, int $maxBytes): ?string
    {
        try {
            $stream = $response->toPsrResponse()->getBody();
            $size = $stream->getSize();

            if ($size !== null && $size > $maxBytes) {
                return null;
            }

            $contents = '';
            $length = 0;

            while (! $stream->eof()) {
                $chunk = $stream->read(min(8192, $maxBytes - $length + 1));

                if ($chunk === '') {
                    break;
                }

                $length += strlen($chunk);
                if ($length > $maxBytes) {
                    return null;
                }

                $contents .= $chunk;
            }

            return $contents;
        } catch (Throwable) {
            return null;
        }
    }
}
