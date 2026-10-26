<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Brand\BrandConfig;
use App\Support\Posts\PostCategories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $workspace = Workspace::current();
        if (! $user instanceof User || $workspace === null) {
            return false;
        }

        $member = $workspace->team->members()->whereKey($user->id)->first();

        return in_array($member?->pivot->role, ['owner', 'admin'], true);
    }

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
