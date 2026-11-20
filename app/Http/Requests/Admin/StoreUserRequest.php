<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') === true;
    }

    /**
     * Normaliza cadenas y correo.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'max:255'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'id')],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => $this->branchRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del usuario es obligatorio.',
            'name.max' => 'El nombre no puede exceder 120 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ya existe un usuario con ese correo electrónico.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'role_ids.required' => 'Asigna al menos un rol.',
            'role_ids.min' => 'Asigna al menos un rol.',
            'role_ids.*.exists' => 'Uno de los roles seleccionados no existe.',
            'role_ids.*.distinct' => 'No repitas roles.',
            'branch_ids.array' => 'Las sucursales seleccionadas no son válidas.',
            'branch_ids.*.exists' => 'Una de las sucursales seleccionadas no existe.',
            'branch_ids.*.in' => 'No puedes asignar una sucursal fuera de tu alcance.',
            'branch_ids.*.distinct' => 'No repitas sucursales.',
        ];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function branchRules(): array
    {
        $rules = ['integer', 'distinct', Rule::exists('branches', 'id')];
        $allowed = $this->user()?->allowedBranchIds();

        if ($allowed !== null) {
            $rules[] = Rule::in($allowed);
        }

        return $rules;
    }
}
