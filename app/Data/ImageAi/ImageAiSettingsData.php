<?php

namespace App\Data\ImageAi;

use App\Http\Requests\Settings\UpdateImageAiSettingsRequest;

/**
 * Settings → Image AI as submitted. A null token keeps the stored one; a
 * null account id turns Cloudflare off.
 */
final readonly class ImageAiSettingsData
{
    public function __construct(
        public ?string $cloudflareAccountId,
        public ?string $cloudflareApiToken,
        public bool $pollinationsEnabled,
    ) {}

    public static function fromRequest(UpdateImageAiSettingsRequest $request): self
    {
        return new self(
            cloudflareAccountId: $request->filled('cloudflare_account_id')
                ? trim($request->string('cloudflare_account_id')->toString())
                : null,
            cloudflareApiToken: $request->filled('cloudflare_api_token')
                ? trim($request->string('cloudflare_api_token')->toString())
                : null,
            pollinationsEnabled: $request->boolean('pollinations_enabled'),
        );
    }
}
