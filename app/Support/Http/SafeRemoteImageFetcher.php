<?php

namespace App\Support\Http;

use App\Concerns\ReadsBodyWithinLimit;
use App\Support\LinkResolution\PublicUrlGuard;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads an image from an arbitrary public URL (e.g. a news page's
 * og:image). Every hop is checked by PublicUrlGuard and pinned to the
 * checked address, redirects are followed by hand, the body is capped, and
 * only real JPEG/PNG/WebP bytes are accepted.
 */
class SafeRemoteImageFetcher
{
    use ReadsBodyWithinLimit;

    public const MAX_BYTES = 10 * 1024 * 1024;

    private const MAX_REDIRECTS = 3;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly ?PublicUrlGuard $urlGuard = null,
    ) {}

    /**
     * @return array{bytes: string, mime: string}|null
     */
    public function fetch(string $url): ?array
    {
        $guard = $this->urlGuard ?? new PublicUrlGuard;
        $current = $url;

        for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; $redirect++) {
            $curlOptions = $guard->curlResolveOptions($current);
            if ($curlOptions === null) {
                return null;
            }

            try {
                $response = Http::timeout(15)
                    ->withOptions(['allow_redirects' => false, 'stream' => true, ...$curlOptions])
                    ->get($current);
            } catch (Throwable) {
                return null;
            }

            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');
                if ($location === '' || $redirect === self::MAX_REDIRECTS) {
                    return null;
                }

                $current = (string) UriResolver::resolve(new Uri($current), new Uri($location));

                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            $bytes = $this->readBodyWithinLimit($response, self::MAX_BYTES);

            return $bytes === null ? null : self::imageOrNull($bytes);
        }

        return null;
    }

    /**
     * The bytes with their sniffed mime, or null when they are not an image
     * the card renderer accepts.
     *
     * @return array{bytes: string, mime: string}|null
     */
    public static function imageOrNull(string $bytes): ?array
    {
        $info = @getimagesizefromstring($bytes);
        $mime = $info === false ? null : $info['mime'];

        return in_array($mime, self::ALLOWED_MIMES, true) ? ['bytes' => $bytes, 'mime' => (string) $mime] : null;
    }
}
