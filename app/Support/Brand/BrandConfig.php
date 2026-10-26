<?php

namespace App\Support\Brand;

use App\Models\Workspace;
use App\Support\Posts\PostCategories;

/**
 * A workspace's brand for photo cards, stored under workspaces.settings.brand.
 * Every getter falls back to config/photo_cards.php and
 * config/post_categories.php, so an unconfigured workspace still renders.
 */
final class BrandConfig
{
    public const THEMES = ['light', 'dark'];

    /** @param array<string, mixed> $data */
    private function __construct(
        private readonly array $data,
        private readonly string $workspaceName,
    ) {}

    public static function fromWorkspace(Workspace $workspace): self
    {
        $settings = $workspace->settings ?? [];
        $brand = $settings['brand'] ?? [];

        return new self(is_array($brand) ? $brand : [], (string) $workspace->name);
    }

    public function name(): string
    {
        return $this->string('name') ?? $this->workspaceName;
    }

    public function website(): ?string
    {
        return $this->string('website');
    }

    public function logoMediaAssetId(): ?int
    {
        return $this->int('logo_media_asset_id');
    }

    public function darkLogoMediaAssetId(): ?int
    {
        return $this->int('logo_dark_media_asset_id');
    }

    public function primaryColor(): string
    {
        return self::color($this->data['primary_color'] ?? null)
            ?? (string) config('photo_cards.brand_defaults.primary_color');
    }

    public function font(): string
    {
        $font = $this->string('font');

        return $font !== null && array_key_exists($font, (array) config('photo_cards.fonts'))
            ? $font
            : (string) config('photo_cards.brand_defaults.font');
    }

    public function imageLabel(): string
    {
        return $this->string('image_label') ?? (string) config('photo_cards.brand_defaults.image_label');
    }

    /**
     * Card theme and accent for a category: the workspace override, else the
     * category default, else a light card in the brand colour.
     *
     * @return array{theme: string, accent: string}
     */
    public function categoryStyle(?string $category): array
    {
        $style = ['theme' => 'light', 'accent' => $this->primaryColor()];

        if ($category === null || ($default = PostCategories::style($category)) === null) {
            return $style;
        }

        $override = $this->data['categories'][$category] ?? [];
        $override = is_array($override) ? $override : [];
        $theme = $override['theme'] ?? null;

        return [
            'theme' => in_array($theme, self::THEMES, true) ? $theme : $default['theme'],
            'accent' => self::color($override['accent'] ?? null) ?? $default['accent'],
        ];
    }

    /**
     * @param  array<string, mixed>  $brand
     */
    public static function write(Workspace $workspace, array $brand): void
    {
        $settings = $workspace->settings ?? [];
        $settings['brand'] = $brand;
        $workspace->settings = $settings;
        $workspace->save();
    }

    /**
     * The stored array, for an action that changes some fields and keeps the rest.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public static function color(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
            ? strtoupper($value)
            : null;
    }

    private function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function int(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
