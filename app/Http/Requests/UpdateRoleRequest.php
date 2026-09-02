<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Route middleware (`can:ROLE:WRITE`) is the actual authorization
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
            'role_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'role_name')->ignore($this->route('role')),
            ],
        ];
    }
}
