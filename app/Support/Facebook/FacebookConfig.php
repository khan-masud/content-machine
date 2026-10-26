<?php

namespace App\Support\Facebook;

use App\Models\Workspace;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The workspace's Facebook Page connection, stored under
 * workspaces.settings.facebook (page access token encrypted).
 */
final class FacebookConfig
{
    public const CAPTION_LANGUAGES = ['bn', 'en'];

    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data) {}

    public static function fromWorkspace(Workspace $workspace): self
    {
        $settings = $workspace->settings ?? [];
        $facebook = $settings['facebook'] ?? [];

        return new self(is_array($facebook) ? $facebook : []);
    }

    public function pageId(): ?string
    {
        $value = $this->data['page_id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function pageName(): ?string
    {
        $value = $this->data['page_name'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function pageAccessToken(): ?string
    {
        $encrypted = $this->data['page_access_token'] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    public function publishEnabled(): bool
    {
        return ($this->data['publish_enabled'] ?? false) === true;
    }

    public function captionLanguage(): string
    {
        $language = $this->data['caption_language'] ?? null;

        return in_array($language, self::CAPTION_LANGUAGES, true) ? $language : 'bn';
    }

    public function isConnected(): bool
    {
        return $this->pageId() !== null && $this->pageAccessToken() !== null;
    }

    /** Connected and switched on: posts may go to the Page. */
    public function isReady(): bool
    {
        return $this->isConnected() && $this->publishEnabled();
    }

    /**
     * A null token keeps the stored one.
     */
    public static function write(
        Workspace $workspace,
        string $pageId,
        ?string $pageName,
        ?string $pageAccessToken,
        bool $publishEnabled,
        string $captionLanguage,
    ): void {
        $settings = $workspace->settings ?? [];
        $current = is_array($settings['facebook'] ?? null) ? $settings['facebook'] : [];

        $settings['facebook'] = [
            'page_id' => $pageId,
            'page_name' => $pageName,
            'page_access_token' => $pageAccessToken !== null
                ? Crypt::encryptString($pageAccessToken)
                : ($current['page_access_token'] ?? null),
            'publish_enabled' => $publishEnabled,
            'caption_language' => $captionLanguage,
        ];
        $workspace->settings = $settings;
        $workspace->save();
    }

    public static function disconnect(Workspace $workspace): void
    {
        $settings = $workspace->settings ?? [];
        unset($settings['facebook']);
        $workspace->settings = $settings;
        $workspace->save();
    }
}
