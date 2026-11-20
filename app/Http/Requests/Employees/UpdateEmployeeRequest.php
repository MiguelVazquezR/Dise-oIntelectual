<?php

namespace App\Http\Requests\Employees;

use App\Enums\PayFrequency;
use App\Models\Employee;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends StoreEmployeeRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $employee = $this->route('employee');

        if (! $user instanceof User || ! $user->hasPermission('employees.manage')) {
            return false;
        }

        if ($employee instanceof Employee && ! BranchAccess::allows($user, $employee->branch_id)) {
            return false;
        }

        $branchId = $this->integer('branch_id');

        if ($branchId > 0 && ! BranchAccess::allows($user, $branchId)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $employee = $this->route('employee');

        $rules['employee_number'] = [
            'required',
            'string',
            'max:30',
            Rule::unique('employees', 'employee_number')->ignore($employee),
        ];

        // El correo también debe excluir al propio colaborador: sin `ignore()`
        // cualquier edición que conserve el correo fallaba como duplicado.
        $rules['email'] = [
            'nullable',
            'email',
            'max:255',
            Rule::unique('employees', 'email')->ignore($employee),
        ];

        $rules['compensation'] = ['nullable', 'array'];
        $rules['compensation.base_salary'] = ['required_with:compensation', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'];
        $rules['compensation.pay_frequency'] = ['required_with:compensation', 'string', Rule::in(PayFrequency::values())];
        $rules['compensation.effective_from'] = ['nullable', 'date'];

        return $rules;
    }
}
