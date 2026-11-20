<?php

namespace App\Http\Requests\Kiosk;

use App\Enums\AttendanceEventType;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marcaje por PIN del checador.
 *
 * El PIN nunca se devuelve, se persiste ni se registra en logs: solo se usa
 * para `Hash::check` contra `employees.pin_hash`.
 */
class PinClockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->hasPermission('attendance.record')) {
            return false;
        }

        $branchId = $this->integer('branch_id');

        return $branchId > 0 && BranchAccess::allows($user, $branchId);
    }

    protected function prepareForValidation(): void
    {
        $employeeNumber = $this->input('employee_number');

        if (is_string($employeeNumber)) {
            $this->merge(['employee_number' => mb_strtoupper(trim($employeeNumber))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_number' => ['required', 'string', 'max:30'],
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,6}$/'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'event_type' => ['required', 'string', Rule::in(AttendanceEventType::values())],
            'gps' => ['nullable', 'array'],
            'gps.latitude' => ['required_with:gps', 'numeric', 'between:-90,90'],
            'gps.longitude' => ['required_with:gps', 'numeric', 'between:-180,180'],
            'gps.accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_number.required' => 'Captura el número de empleado.',
            'employee_number.max' => 'El número de empleado no es válido.',
            'pin.required' => 'Captura el PIN.',
            'pin.regex' => 'El PIN debe tener entre 4 y 6 dígitos numéricos.',
            'branch_id.required' => 'Selecciona la sucursal del checador.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'event_type.required' => 'Selecciona el tipo de marcaje.',
            'event_type.in' => 'El tipo de marcaje no es válido.',
            'gps.array' => 'La ubicación enviada no es válida.',
            'gps.latitude.between' => 'La latitud está fuera de rango.',
            'gps.longitude.between' => 'La longitud está fuera de rango.',
            'gps.accuracy.min' => 'La precisión del GPS no es válida.',
        ];
    }
}
