<?php

namespace App\Http\Requests\Kiosk;

use App\Enums\AttendanceEventType;
use App\Http\Requests\Concerns\ValidatesFaceImage;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marcaje por reconocimiento facial (pendiente de configuración, D-13).
 */
class StoreFaceClockRequest extends FormRequest
{
    use ValidatesFaceImage;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->hasPermission('attendance.record')) {
            return false;
        }

        $branchId = $this->integer('branch_id');

        return $branchId > 0 && BranchAccess::allows($user, $branchId);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'event_type' => ['required', 'string', Rule::in(AttendanceEventType::values())],
            'image' => $this->faceImageRules(required: false),
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
            'branch_id.required' => 'Selecciona la sucursal del checador.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'event_type.required' => 'Selecciona el tipo de marcaje.',
            'event_type.in' => 'El tipo de marcaje no es válido.',
            'image.max' => 'La imagen excede el tamaño permitido (5 MB por rostro).',
            'gps.array' => 'La ubicación enviada no es válida.',
        ];
    }
}
