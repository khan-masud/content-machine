<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesWorkspaceAdmin;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardPersonRequest extends FormRequest
{
    use AuthorizesWorkspaceAdmin;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'aliases' => ['nullable', 'string', 'max:600'],
            'photo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ];
    }
}
