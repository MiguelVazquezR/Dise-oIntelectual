<?php

namespace App\Http\Requests\Payroll;

use App\Enums\AdjustmentStatus;
use App\Enums\PayrollConceptType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de ajustes de pre-nómina (§7.4).
 */
class IndexAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('adjustments.view') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period_id' => ['nullable', 'integer', Rule::exists('payroll_periods', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['nullable', 'string', Rule::in(AdjustmentStatus::values())],
            'type' => ['nullable', 'string', Rule::in(PayrollConceptType::values())],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_id.exists' => 'El periodo indicado no existe.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'employee_id.exists' => 'El colaborador indicado no existe.',
            'status.in' => 'El estatus del ajuste no es válido.',
            'type.in' => 'El tipo de concepto no es válido.',
            'per_page.in' => 'El tamaño de página debe ser 10, 25, 50 o 100.',
        ];
    }
}
