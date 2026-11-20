<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PayrollConceptType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del catálogo de conceptos de nómina (§7.4).
 */
class IndexPayrollConceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('concepts.view') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', Rule::in(PayrollConceptType::values())],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'La búsqueda no puede exceder 120 caracteres.',
            'type.in' => 'El tipo de concepto no es válido.',
            'is_active.boolean' => 'El filtro de vigencia no es válido.',
        ];
    }
}
