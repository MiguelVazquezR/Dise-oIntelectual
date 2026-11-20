<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::exists('permissions', 'slug')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.required' => 'Selecciona los permisos del rol.',
            'permissions.array' => 'Los permisos enviados no son válidos.',
            'permissions.*.exists' => 'Uno de los permisos seleccionados no existe.',
            'permissions.*.distinct' => 'No repitas permisos.',
        ];
    }
}
