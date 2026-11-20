<?php

namespace App\Http\Requests\Incidents;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de incidencias (§7.3).
 */
class IndexIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('incidents.view') === true;
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
            'type' => ['nullable', 'string', Rule::in(IncidentType::values())],
            'status' => ['nullable', 'string', Rule::in(IncidentStatus::values())],
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
            'type.in' => 'El tipo de incidencia no es válido.',
            'status.in' => 'El estatus de la incidencia no es válido.',
            'per_page.in' => 'El tamaño de página debe ser 10, 25, 50 o 100.',
        ];
    }
}
