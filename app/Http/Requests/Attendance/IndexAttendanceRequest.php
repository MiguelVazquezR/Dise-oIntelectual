<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de asistencias (§7.3).
 */
class IndexAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('attendance.view') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date' => 'La fecha inicial no es válida.',
            'to.date' => 'La fecha final no es válida.',
            'to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'employee_id.exists' => 'El colaborador indicado no existe.',
            'search.max' => 'La búsqueda no puede exceder 120 caracteres.',
            'per_page.in' => 'El tamaño de página debe ser 10, 25, 50 o 100.',
        ];
    }
}
