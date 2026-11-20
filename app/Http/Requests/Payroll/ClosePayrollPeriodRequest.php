<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmación del cierre del periodo (§7.4): `{confirm: true}`.
 */
class ClosePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payroll.close') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['confirm' => $this->boolean('confirm')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirm' => ['accepted'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Confirma el cierre del periodo: los importes quedarán congelados.',
            'note.max' => 'La nota no puede exceder 500 caracteres.',
        ];
    }
}
