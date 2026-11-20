<?php

namespace App\Http\Requests\Payroll;

use App\Enums\AdjustmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aprobación o rechazo de un ajuste (permiso `adjustments.approve`).
 */
class DecideAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('adjustments.approve') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in([AdjustmentStatus::Approved->value, AdjustmentStatus::Rejected->value])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Selecciona la decisión del ajuste.',
            'decision.in' => 'La decisión debe ser aprobado o rechazado.',
            'notes.max' => 'Las notas no pueden exceder 500 caracteres.',
        ];
    }
}
