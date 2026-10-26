<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesWorkspaceAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePexelsSettingsRequest extends FormRequest
{
    use AuthorizesWorkspaceAdmin;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['api_key' => ['required', 'string', 'min:1', 'max:500']];
    }
}
