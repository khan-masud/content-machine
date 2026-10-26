<?php

namespace App\Http\Requests\Posts;

use App\Data\PhotoCards\PostPhotoCardTextData;
use Illuminate\Foundation\Http\FormRequest;

class RenderPostPhotoCardRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'headline' => ['nullable', 'string', 'max:'.PostPhotoCardTextData::HEADLINE_MAX],
            'source' => ['nullable', 'string', 'max:'.PostPhotoCardTextData::SOURCE_MAX],
        ];
    }
}
