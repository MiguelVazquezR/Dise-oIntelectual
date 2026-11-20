<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PayrollConceptCalculation;
use App\Enums\PayrollConceptType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de concepto de nómina. `code` es único (contrato §7.4).
 */
class StorePayrollConceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('concepts.manage') === true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $notes = $this->input('notes');

        $this->merge([
            'code' => is_string($code) ? mb_strtoupper(trim($code)) : $code,
            'notes' => is_string($notes) && trim($notes) === '' ? null : $notes,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_]+$/', Rule::unique('payroll_concepts', 'code')],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(PayrollConceptType::values())],
            'calculation' => ['required', 'string', Rule::in(PayrollConceptCalculation::values())],
            'default_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'decimal:0,2'],
            'is_sensitive' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Captura el código del concepto.',
            'code.max' => 'El código no puede exceder 30 caracteres.',
            'code.regex' => 'El código solo admite mayúsculas, números y guion bajo.',
            'code.unique' => 'Ya existe un concepto con ese código.',
            'name.required' => 'Captura el nombre del concepto.',
            'name.max' => 'El nombre no puede exceder 120 caracteres.',
            'type.required' => 'Selecciona el tipo de concepto.',
            'type.in' => 'El tipo de concepto no es válido.',
            'calculation.required' => 'Selecciona la forma de cálculo.',
            'calculation.in' => 'La forma de cálculo no es válida.',
            'default_amount.numeric' => 'El monto por defecto debe ser numérico.',
            'default_amount.min' => 'El monto por defecto no puede ser negativo.',
            'default_amount.max' => 'El monto por defecto excede el máximo permitido.',
            'default_amount.decimal' => 'El monto por defecto admite máximo dos decimales.',
            'notes.max' => 'Las notas no pueden exceder 500 caracteres.',
        ];
    }
}
