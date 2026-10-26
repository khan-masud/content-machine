<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesWorkspaceAdmin;
use App\Support\Brand\BrandConfig;
use App\Support\Posts\PostCategories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandSettingsRequest extends FormRequest
{
    use AuthorizesWorkspaceAdmin;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $hex = 'regex:/^#[0-9A-Fa-f]{6}$/';

        return [
            'name' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['required', 'string', $hex],
            'font' => ['required', 'string', Rule::in(array_keys((array) config('photo_cards.fonts')))],
            'image_label' => ['nullable', 'string', 'max:60'],
            'card_language' => ['nullable', Rule::in(BrandConfig::CARD_LANGUAGES)],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'logo_dark' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_logo_dark' => ['sometimes', 'boolean'],
            'categories' => ['sometimes', 'array:'.implode(',', PostCategories::keys())],
            'categories.*' => ['array'],
            'categories.*.theme' => ['required', Rule::in(BrandConfig::THEMES)],
            'categories.*.accent' => ['required', 'string', $hex],
        ];
    }
}
