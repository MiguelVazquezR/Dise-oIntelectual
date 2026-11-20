<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmployeeStatus;
use App\Enums\PayFrequency;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->hasPermission('employees.manage')) {
            return false;
        }

        $branchId = $this->integer('branch_id');

        return $branchId <= 0 || BranchAccess::allows($user, $branchId);
    }

    /**
     * Normaliza cadenas y vacíos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'employee_number' => is_string($this->input('employee_number'))
                ? mb_strtoupper(trim($this->input('employee_number')))
                : $this->input('employee_number'),
            'first_name' => $this->normalizeString('first_name'),
            'last_name' => $this->normalizeString('last_name'),
            'second_last_name' => $this->normalizeNullable('second_last_name'),
            'position' => $this->normalizeString('position'),
            'department' => $this->normalizeNullable('department'),
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
            'phone' => $this->normalizeNullable('phone'),
            'termination_date' => $this->normalizeNullable('termination_date'),
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
            'employee_number' => ['required', 'string', 'max:30', Rule::unique('employees', 'employee_number')],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'second_last_name' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'position' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'hire_date' => ['required', 'date'],
            'status' => ['sometimes', 'string', Rule::in(EmployeeStatus::values())],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'compensation' => ['required', 'array'],
            'compensation.base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'compensation.pay_frequency' => ['required', 'string', Rule::in(PayFrequency::values())],
            'compensation.effective_from' => ['nullable', 'date'],
            'compensation.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_number.required' => 'El número de empleado es obligatorio.',
            'employee_number.max' => 'El número de empleado no puede exceder 30 caracteres.',
            'employee_number.unique' => 'Ya existe un colaborador con ese número de empleado.',
            'first_name.required' => 'El nombre es obligatorio.',
            'first_name.max' => 'El nombre no puede exceder 120 caracteres.',
            'last_name.required' => 'El apellido paterno es obligatorio.',
            'last_name.max' => 'El apellido paterno no puede exceder 120 caracteres.',
            'second_last_name.max' => 'El apellido materno no puede exceder 120 caracteres.',
            'branch_id.required' => 'La sucursal es obligatoria.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'position.required' => 'El puesto es obligatorio.',
            'position.max' => 'El puesto no puede exceder 120 caracteres.',
            'department.max' => 'El departamento no puede exceder 120 caracteres.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ya existe un colaborador con ese correo electrónico.',
            'phone.max' => 'El teléfono no puede exceder 30 caracteres.',
            'hire_date.required' => 'La fecha de ingreso es obligatoria.',
            'hire_date.date' => 'La fecha de ingreso no es válida.',
            'status.in' => 'El estatus del colaborador no es válido.',
            'termination_date.date' => 'La fecha de baja no es válida.',
            'termination_date.after_or_equal' => 'La fecha de baja no puede ser anterior a la de ingreso.',
            'compensation.required' => 'Captura la compensación inicial del colaborador.',
            'compensation.base_salary.required' => 'El salario base es obligatorio.',
            'compensation.base_salary.numeric' => 'El salario base debe ser un número.',
            'compensation.base_salary.min' => 'El salario base no puede ser negativo.',
            'compensation.base_salary.max' => 'El salario base excede el máximo permitido.',
            'compensation.base_salary.decimal' => 'El salario base admite máximo dos decimales.',
            'compensation.pay_frequency.required' => 'La frecuencia de pago es obligatoria.',
            'compensation.pay_frequency.in' => 'La frecuencia de pago no es válida.',
            'compensation.effective_from.date' => 'La fecha de vigencia de la compensación no es válida.',
            'compensation.notes.max' => 'Las notas de la compensación no pueden exceder 255 caracteres.',
        ];
    }

    private function normalizeString(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeNullable(string $key): mixed
    {
        $value = $this->input($key);

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }
}
