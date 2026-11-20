<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload del cálculo de pre-nómina (§7.4).
 *
 * `force` es obligatorio cuando el periodo ya está `reviewed`: confirma que se
 * reemplazarán los importes y deja nota en la bitácora.
 */
class CalculatePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payroll.calculate') === true;
    }

    protected function prepareForValidation(): void
    {
        $note = $this->input('note');

        if (is_string($note) && trim($note) === '') {
            $this->merge(['note' => null]);
        }

        $this->merge(['force' => $this->boolean('force')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'force' => ['boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'force.boolean' => 'La confirmación de recálculo no es válida.',
            'note.max' => 'La nota no puede exceder 500 caracteres.',
        ];
    }
}
