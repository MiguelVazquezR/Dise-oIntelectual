<?php

namespace App\Http\Requests\Incidents;

use App\Enums\IncidentType;
use App\Models\AttendanceIncident;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición de una incidencia pendiente. El colaborador y la sucursal no cambian.
 */
class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $incident = $this->route('incident');

        if (! $user instanceof User || ! $user->hasPermission('incidents.manage')) {
            return false;
        }

        return $incident instanceof AttendanceIncident
            && BranchAccess::allows($user, $incident->branch_id);
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
