<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Route middleware (`can:CATEGORY:WRITE`) is the actual authorization
     * boundary for this request - no permission check is re-implemented
     * here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'category_name')->ignore($this->route('category')),
            ],
        ];
    }
}
