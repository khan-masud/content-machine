<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesWorkspaceAdmin;
use App\Support\Facebook\FacebookConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacebookSettingsRequest extends FormRequest
{
    use AuthorizesWorkspaceAdmin;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'page_id' => ['required', 'string', 'regex:/^[0-9]{5,30}$/'],
            'page_access_token' => ['nullable', 'string', 'max:1000'],
            'publish_enabled' => ['sometimes', 'boolean'],
            'caption_language' => ['required', Rule::in(FacebookConfig::CAPTION_LANGUAGES)],
        ];
    }
}
