<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Route middleware (`can:PERMISSION:WRITE`) is the actual authorization
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
            'resource' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions')
                    ->where('action', $this->input('action'))
                    ->ignore($this->route('permission')),
            ],
            'action' => ['required', 'string', 'max:255'],
        ];
    }
}
