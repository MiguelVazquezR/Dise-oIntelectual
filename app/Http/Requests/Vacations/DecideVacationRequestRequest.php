<?php

namespace App\Http\Requests\Vacations;

use App\Models\User;
use App\Models\VacationRequest;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideVacationRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->hasPermission('vacations.approve')) {
            return false;
        }

        $vacationRequest = $this->route('vacationRequest');

        if (! $vacationRequest instanceof VacationRequest) {
            return true;
        }

        return BranchAccess::allows($user, (int) $vacationRequest->employee->branch_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Indica la decisión de la solicitud.',
            'decision.in' => 'La decisión debe ser aprobada o rechazada.',
            'notes.max' => 'Las notas no pueden exceder 500 caracteres.',
        ];
    }
}
