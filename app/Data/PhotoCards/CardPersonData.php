<?php

namespace App\Data\PhotoCards;

use App\Http\Requests\Settings\StoreCardPersonRequest;
use Illuminate\Http\UploadedFile;

/**
 * A person for the card photo library, as submitted from Settings → Card
 * people. Aliases arrive comma-separated ("Porimoni, পরীমনি").
 */
final readonly class CardPersonData
{
    public const MAX_ALIASES = 10;

    /**
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $name,
        public array $aliases,
        public UploadedFile $photo,
    ) {}

    public static function fromRequest(StoreCardPersonRequest $request): self
    {
        /** @var UploadedFile $photo */
        $photo = $request->file('photo');

        return new self(
            trim($request->string('name')->toString()),
            self::aliases($request->string('aliases')->toString()),
            $photo,
        );
    }

    /**
     * @return list<string>
     */
    public static function aliases(string $value): array
    {
        $aliases = array_map(
            static fn (string $alias): string => mb_substr(trim(preg_replace('/\s+/u', ' ', $alias) ?? ''), 0, 80),
            preg_split('/[,،]/u', $value) ?: [],
        );

        return array_slice(array_values(array_unique(array_filter($aliases, static fn (string $alias): bool => $alias !== ''))), 0, self::MAX_ALIASES);
    }
}
