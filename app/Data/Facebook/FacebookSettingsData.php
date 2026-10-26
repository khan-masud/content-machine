<?php

namespace App\Data\Facebook;

use App\Http\Requests\Settings\UpdateFacebookSettingsRequest;

/**
 * Settings → Facebook as submitted. A null token keeps the stored one.
 */
final readonly class FacebookSettingsData
{
    public function __construct(
        public string $pageId,
        public ?string $pageAccessToken,
        public bool $publishEnabled,
        public string $captionLanguage,
    ) {}

    public static function fromRequest(UpdateFacebookSettingsRequest $request): self
    {
        return new self(
            pageId: trim($request->string('page_id')->toString()),
            pageAccessToken: $request->filled('page_access_token')
                ? trim($request->string('page_access_token')->toString())
                : null,
            publishEnabled: $request->boolean('publish_enabled'),
            captionLanguage: $request->string('caption_language')->toString(),
        );
    }
}
