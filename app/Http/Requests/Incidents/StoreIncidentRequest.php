<?php

namespace App\Http\Requests\Incidents;

use App\Enums\IncidentType;
use App\Models\Employee;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de incidencia. La sucursal se deriva del colaborador, nunca del cliente.
 */
class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->hasPermission('incidents.manage')) {
            return false;
        }

        $employee = Employee::query()->find($this->integer('employee_id'));

        if (! $employee instanceof Employee) {
            return true;
        }

        return BranchAccess::allows($user, $employee->branch_id);
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        if (is_string($reason)) {
            $reason = trim($reason);
            $this->merge(['reason' => $reason === '' ? null : $reason]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'type' => ['required', 'string', Rule::in(IncidentType::values())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999.99', 'decimal:0,2'],
            'reason' => ['nullable', 'string', 'max:255'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
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
            'type.required' => 'Selecciona el tipo de incidencia.',
            'type.in' => 'El tipo de incidencia no es válido.',
            'start_date.required' => 'La fecha inicial es obligatoria.',
            'start_date.date' => 'La fecha inicial no es válida.',
            'end_date.required' => 'La fecha final es obligatoria.',
            'end_date.date' => 'La fecha final no es válida.',
            'end_date.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'quantity.required' => 'Captura la cantidad (días u horas).',
            'quantity.numeric' => 'La cantidad debe ser numérica.',
            'quantity.min' => 'La cantidad debe ser mayor que cero.',
            'quantity.max' => 'La cantidad excede el máximo permitido.',
            'quantity.decimal' => 'La cantidad admite máximo dos decimales.',
            'reason.max' => 'El motivo no puede exceder 255 caracteres.',
            'evidence.mimes' => 'La evidencia debe ser PDF, JPG o PNG.',
            'evidence.max' => 'La evidencia no puede exceder 5 MB.',
        ];
    }
}
