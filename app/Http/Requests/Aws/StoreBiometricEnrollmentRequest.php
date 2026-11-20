<?php

namespace App\Http\Requests\Aws;

use App\Http\Requests\Concerns\ValidatesFaceImage;
use App\Models\Employee;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consentimiento y enrolamiento biométrico de un colaborador.
 *
 * Exige `employees.manage` y `aws.settings.manage` (además del middleware de
 * la ruta) y que el actor tenga acceso a la sucursal del colaborador.
 */
class StoreBiometricEnrollmentRequest extends FormRequest
{
    use ValidatesFaceImage;

    public function authorize(): bool
    {
        $user = $this->user();
        $employee = $this->route('employee');

        if (! $user instanceof User || ! $employee instanceof Employee) {
            return false;
        }

        return $user->hasPermission('employees.manage')
            && $user->hasPermission('aws.settings.manage')
            && BranchAccess::allows($user, $employee->branch_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'consent_given' => ['required', 'accepted'],
            'consent_version' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
            'image' => $this->faceImageRules(required: true),
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent_given.required' => 'Debes registrar el consentimiento explícito del colaborador.',
            'consent_given.accepted' => 'El consentimiento biométrico debe aceptarse de forma explícita.',
            'consent_version.required' => 'Indica la versión del consentimiento aceptado.',
            'consent_version.regex' => 'La versión del consentimiento solo admite letras, números, punto, guion y guion bajo.',
            'image.required' => 'Adjunta una fotografía del rostro del colaborador.',
            'image.max' => 'La imagen excede el tamaño permitido (5 MB por rostro).',
            'image.string' => 'Adjunta la imagen del rostro en base64.',
            'idempotency_key.uuid' => 'La clave de idempotencia no es válida.',
        ];
    }
}
