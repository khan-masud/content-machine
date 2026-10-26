<?php

namespace App\Data\Brand;

use App\Http\Requests\Settings\UpdateBrandSettingsRequest;
use App\Support\Brand\BrandConfig;
use App\Support\Posts\PostCategories;
use Illuminate\Http\UploadedFile;

/**
 * Brand settings as submitted from Settings → Brand. Logos are optional
 * uploads; `removeLogo` / `removeDarkLogo` clear a stored one.
 */
final readonly class BrandSettingsData
{
    /**
     * @param  array<string, array{theme: string, accent: string}>  $categoryStyles
     */
    public function __construct(
        public ?string $name,
        public ?string $website,
        public string $primaryColor,
        public string $font,
        public ?string $imageLabel,
        public array $categoryStyles,
        public ?UploadedFile $logo = null,
        public ?UploadedFile $darkLogo = null,
        public bool $removeLogo = false,
        public bool $removeDarkLogo = false,
    ) {}

    public static function fromRequest(UpdateBrandSettingsRequest $request): self
    {
        $styles = [];
        $submitted = $request->input('categories', []);

        foreach (PostCategories::keys() as $key) {
            $style = is_array($submitted) && is_array($submitted[$key] ?? null) ? $submitted[$key] : [];
            $theme = $style['theme'] ?? null;
            $accent = BrandConfig::color($style['accent'] ?? null);

            if (in_array($theme, BrandConfig::THEMES, true) && $accent !== null) {
                $styles[$key] = ['theme' => $theme, 'accent' => $accent];
            }
        }

        $logo = $request->file('logo');
        $darkLogo = $request->file('logo_dark');

        return new self(
            name: $request->filled('name') ? trim($request->string('name')->toString()) : null,
            website: $request->filled('website') ? trim($request->string('website')->toString()) : null,
            primaryColor: (string) BrandConfig::color($request->string('primary_color')->toString()),
            font: $request->string('font')->toString(),
            imageLabel: $request->filled('image_label') ? trim($request->string('image_label')->toString()) : null,
            categoryStyles: $styles,
            logo: $logo instanceof UploadedFile ? $logo : null,
            darkLogo: $darkLogo instanceof UploadedFile ? $darkLogo : null,
            removeLogo: $request->boolean('remove_logo'),
            removeDarkLogo: $request->boolean('remove_logo_dark'),
        );
    }
}
