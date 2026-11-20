<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PayrollPeriodStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de periodos de pre-nómina (§7.4).
 */
class IndexPayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payroll.view') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'schedule_id' => ['nullable', 'integer', Rule::exists('payroll_schedules', 'id')],
            'status' => ['nullable', 'string', Rule::in(PayrollPeriodStatus::values())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'schedule_id.exists' => 'El esquema de pago indicado no existe.',
            'status.in' => 'El estatus del periodo no es válido.',
            'from.date' => 'La fecha inicial no es válida.',
            'to.date' => 'La fecha final no es válida.',
            'to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'per_page.in' => 'El tamaño de página debe ser 10, 25, 50 o 100.',
        ];
    }
}
