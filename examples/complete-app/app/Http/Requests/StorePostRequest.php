<?php

declare(strict_types=1);

namespace Example\CompleteApp\Http\Requests;

use SedoPHP\Http\FormRequest;

final class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|min:3|max:160',
            'body' => 'required|string|min:10',
        ];
    }
}
