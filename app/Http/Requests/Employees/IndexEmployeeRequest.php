<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmployeeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.view') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', 'string', Rule::in(EmployeeStatus::values())],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'La búsqueda no puede exceder 120 caracteres.',
            'branch_id.integer' => 'La sucursal indicada no es válida.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'status.in' => 'El estatus del colaborador no es válido.',
            'per_page.in' => 'El tamaño de página debe ser 10, 25, 50 o 100.',
        ];
    }
}
