<?php

namespace App\Http\Requests\Posts;

use Illuminate\Foundation\Http\FormRequest;

class PublishPostToFacebookRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            // datetime-local in the workspace timezone; empty publishes now.
            'when' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ];
    }
}
