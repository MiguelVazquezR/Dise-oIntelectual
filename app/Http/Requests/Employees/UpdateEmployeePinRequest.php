<?php

namespace App\Http\Requests\Employees;

use App\Models\Employee;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeePinRequest extends FormRequest
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

        return $employee instanceof Employee
            && BranchAccess::allows($user, $employee->branch_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,6}$/', 'confirmed'],
            'pin_confirmation' => ['required', 'string', 'regex:/^[0-9]{4,6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pin.required' => 'El PIN es obligatorio.',
            'pin.regex' => 'El PIN debe tener entre 4 y 6 dígitos numéricos.',
            'pin.confirmed' => 'La confirmación del PIN no coincide.',
            'pin_confirmation.required' => 'Confirma el PIN.',
            'pin_confirmation.regex' => 'La confirmación debe tener entre 4 y 6 dígitos numéricos.',
        ];
    }
}
