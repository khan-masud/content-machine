<?php

namespace App\Data\PhotoCards;

use App\Http\Requests\Posts\RenderPostPhotoCardRequest;

/**
 * The words on a post's photo card. `headline` marks accent words with
 * [[...]]; null fields fall back to the post title / no source line.
 */
final readonly class PostPhotoCardTextData
{
    public const HEADLINE_MAX = 200;

    public const SOURCE_MAX = 80;

    public function __construct(
        public ?string $headline,
        public ?string $source,
    ) {}

    public static function fromRequest(RenderPostPhotoCardRequest $request): self
    {
        return self::fromArray([
            'headline' => $request->input('headline'),
            'source' => $request->input('source'),
        ]);
    }

    /**
     * Lenient: anything that is not a usable string becomes null, and long
     * text is cut to the limits. Used for AI output as well as requests.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::clean($data['headline'] ?? null, self::HEADLINE_MAX),
            self::clean($data['source'] ?? null, self::SOURCE_MAX),
        );
    }

    private static function clean(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
