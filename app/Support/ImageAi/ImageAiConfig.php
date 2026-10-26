<?php

namespace App\Support\ImageAi;

use App\Models\Workspace;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Free image-generation settings stored under workspaces.settings.image_ai:
 * Cloudflare Workers AI credentials (token encrypted) and whether the
 * key-less Pollinations fallback may be used.
 */
final class ImageAiConfig
{
    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data) {}

    public static function fromWorkspace(Workspace $workspace): self
    {
        $settings = $workspace->settings ?? [];
        $imageAi = $settings['image_ai'] ?? [];

        return new self(is_array($imageAi) ? $imageAi : []);
    }

    public function cloudflareAccountId(): ?string
    {
        $value = $this->data['cloudflare_account_id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function cloudflareApiToken(): ?string
    {
        $encrypted = $this->data['cloudflare_api_token'] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    public function cloudflareConfigured(): bool
    {
        return $this->cloudflareAccountId() !== null && $this->cloudflareApiToken() !== null;
    }

    public function pollinationsEnabled(): bool
    {
        return ($this->data['pollinations_enabled'] ?? true) === true;
    }

    /**
     * A null token keeps the stored one; an empty account id clears both.
     */
    public static function write(Workspace $workspace, ?string $accountId, ?string $apiToken, bool $pollinationsEnabled): void
    {
        $settings = $workspace->settings ?? [];
        $current = is_array($settings['image_ai'] ?? null) ? $settings['image_ai'] : [];

        $token = $apiToken !== null ? Crypt::encryptString($apiToken) : ($current['cloudflare_api_token'] ?? null);
        if ($accountId === null) {
            $token = null;
        }

        $settings['image_ai'] = [
            'cloudflare_account_id' => $accountId,
            'cloudflare_api_token' => $token,
            'pollinations_enabled' => $pollinationsEnabled,
        ];
        $workspace->settings = $settings;
        $workspace->save();
    }
}
