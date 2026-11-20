<?php

namespace App\Http\Requests\Incidents;

use App\Enums\IncidentStatus;
use App\Models\AttendanceIncident;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aprobación o rechazo de una incidencia (permiso `incidents.approve`).
 */
class DecideIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $incident = $this->route('incident');

        if (! $user instanceof User || ! $user->hasPermission('incidents.approve')) {
            return false;
        }

        return $incident instanceof AttendanceIncident
            && BranchAccess::allows($user, $incident->branch_id);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in([
                IncidentStatus::Approved->value,
                IncidentStatus::Rejected->value,
            ])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Selecciona la decisión.',
            'decision.in' => 'La decisión debe ser aprobada o rechazada.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
        ];
    }
}
