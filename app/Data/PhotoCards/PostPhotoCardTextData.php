<?php

namespace App\Data\PhotoCards;

use App\Http\Requests\Posts\RenderPostPhotoCardRequest;

/**
 * The words on a post's photo card. `headline` marks accent words with
 * [[...]]; null fields fall back to the post title / no source line.
 * `imagePrompt` (English) describes an AI background; `imageQuery` is a few
 * stock-photo search words.
 */
final readonly class PostPhotoCardTextData
{
    public const HEADLINE_MAX = 200;

    public const SOURCE_MAX = 80;

    public const IMAGE_PROMPT_MAX = 500;

    public const IMAGE_QUERY_MAX = 80;

    public function __construct(
        public ?string $headline,
        public ?string $source,
        public ?string $imagePrompt = null,
        public ?string $imageQuery = null,
    ) {}

    public static function fromRequest(RenderPostPhotoCardRequest $request): self
    {
        return self::fromArray([
            'headline' => $request->input('headline'),
            'source' => $request->input('source'),
            'image_prompt' => $request->input('image_prompt'),
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
            self::clean($data['image_prompt'] ?? null, self::IMAGE_PROMPT_MAX),
            self::clean($data['image_query'] ?? null, self::IMAGE_QUERY_MAX),
        );
    }

    /**
     * The photo_card fields this text sets. An empty image query is left out
     * so editing the card text never drops the stock-photo keywords.
     *
     * @return array<string, string|null>
     */
    public function toCardFields(): array
    {
        return array_merge([
            'headline' => $this->headline,
            'source' => $this->source,
            'image_prompt' => $this->imagePrompt,
        ], $this->imageQuery !== null ? ['image_query' => $this->imageQuery] : []);
    }

    /**
     * The same card with the person's own headline: kept as the model wrote
     * it when that is the same text with accent brackets added, else replaced
     * by the text as written.
     */
    public function withOwnHeadline(string $headline): self
    {
        $own = self::clean($headline, self::HEADLINE_MAX);
        $plain = self::clean(str_replace(['[[', ']]'], '', (string) $this->headline), self::HEADLINE_MAX);

        return new self(
            $own !== null && $plain === $own ? $this->headline : $own,
            $this->source,
            $this->imagePrompt,
            $this->imageQuery,
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
