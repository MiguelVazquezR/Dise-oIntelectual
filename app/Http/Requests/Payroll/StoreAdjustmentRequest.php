<?php

namespace App\Http\Requests\Payroll;

use App\Models\Employee;
use App\Models\PayrollConcept;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de un ajuste (bono o penalización) de pre-nómina (§7.4).
 *
 * Autorización en servidor:
 * - requiere `adjustments.manage`;
 * - el colaborador debe pertenecer a una sucursal visible (`BranchAccess`);
 * - los conceptos marcados `is_sensitive` (penalizaciones administrativas)
 *   solo los puede registrar quien tiene `adjustments.approve`.
 */
class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->hasPermission('adjustments.manage')) {
            return false;
        }

        $employeeId = $this->integer('employee_id');

        if ($employeeId > 0) {
            $branchId = Employee::query()->whereKey($employeeId)->value('branch_id');

            if ($branchId !== null && ! BranchAccess::allows($user, (int) $branchId)) {
                return false;
            }
        }

        $conceptId = $this->integer('payroll_concept_id');

        if ($conceptId > 0) {
            $concept = PayrollConcept::query()->find($conceptId);

            if ($concept instanceof PayrollConcept && $concept->is_sensitive && ! $user->hasPermission('adjustments.approve')) {
                return false;
            }
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');
        $periodId = $this->input('payroll_period_id');
        $routePeriod = $this->route('period');

        if ($routePeriod instanceof PayrollPeriod) {
            $periodId = $routePeriod->id;
        } elseif (is_numeric($routePeriod)) {
            // La ruta `payroll/periods/{period}/adjustments` llega como id crudo.
            $periodId = (int) $routePeriod;
        }

        $this->merge([
            'reason' => is_string($reason) ? trim($reason) : $reason,
            'payroll_period_id' => $periodId === '' ? null : $periodId,
            'quantity' => $this->input('quantity') ?? '1.00',
            'is_sensitive' => $this->boolean('is_sensitive'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'payroll_concept_id' => ['required', 'integer', Rule::exists('payroll_concepts', 'id')->where('is_active', true)],
            'payroll_period_id' => ['nullable', 'integer', Rule::exists('payroll_periods', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99', 'decimal:0,2'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999.99', 'decimal:0,2'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Selecciona al colaborador.',
            'employee_id.exists' => 'El colaborador indicado no existe.',
            'payroll_concept_id.required' => 'Selecciona el concepto del ajuste.',
            'payroll_concept_id.exists' => 'El concepto no existe o está inactivo.',
            'payroll_period_id.exists' => 'El periodo indicado no existe.',
            'amount.required' => 'Captura el importe del ajuste.',
            'amount.numeric' => 'El importe debe ser numérico.',
            'amount.min' => 'El importe debe ser mayor que cero.',
            'amount.max' => 'El importe excede el máximo permitido.',
            'amount.decimal' => 'El importe admite máximo dos decimales.',
            'quantity.required' => 'Captura la cantidad.',
            'quantity.numeric' => 'La cantidad debe ser numérica.',
            'quantity.min' => 'La cantidad debe ser mayor que cero.',
            'quantity.max' => 'La cantidad excede el máximo permitido.',
            'quantity.decimal' => 'La cantidad admite máximo dos decimales.',
            'reason.required' => 'Captura el motivo del ajuste.',
            'reason.max' => 'El motivo no puede exceder 255 caracteres.',
        ];
    }
}
