<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesWorkspaceAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateImageAiSettingsRequest extends FormRequest
{
    use AuthorizesWorkspaceAdmin;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            // Cloudflare account ids are 32 hex characters.
            'cloudflare_account_id' => ['nullable', 'string', 'regex:/^[0-9a-fA-F]{32}$/'],
            'cloudflare_api_token' => ['nullable', 'string', 'max:200'],
            'pollinations_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
