<?php

namespace App\Http\Requests\Vacations;

use App\Models\Employee;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVacationRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * La solicitud se captura para un colaborador de una sucursal visible.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->hasPermission('vacations.manage')) {
            return false;
        }

        $employeeId = $this->integer('employee_id');

        if ($employeeId <= 0) {
            return true;
        }

        $branchId = Employee::query()->whereKey($employeeId)->value('branch_id');

        if ($branchId === null) {
            return true;
        }

        return BranchAccess::allows($user, (int) $branchId);
    }

    /**
     * Normaliza cadenas y vacíos.
     */
    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        $this->merge([
            'notes' => is_string($notes) && trim($notes) === '' ? null : $notes,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Selecciona al colaborador que solicita las vacaciones.',
            'employee_id.exists' => 'El colaborador indicado no existe.',
            'start_date.required' => 'La fecha inicial es obligatoria.',
            'start_date.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'end_date.required' => 'La fecha final es obligatoria.',
            'end_date.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'end_date.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
            'notes.max' => 'Las notas no pueden exceder 500 caracteres.',
        ];
    }
}
