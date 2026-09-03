<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
            'category_name' => ['required', 'string', 'max:255', 'unique:categories,category_name'],
        ];
    }
}
