<?php

namespace App\Http\Requests\Schedules;

use App\Models\User;
use App\Support\BranchAccess;
use App\Support\Schedules\ScheduleEffectiveGuard;
use App\Support\Schedules\TimeSegments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * `branch_id` ausente se deja a la validación para responder 422 con el
     * mensaje de campo obligatorio.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->hasPermission('schedules.manage')) {
            return false;
        }

        $branchId = $this->integer('branch_id');

        return $branchId <= 0 || BranchAccess::allows($user, $branchId);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'days' => ['required', 'array', 'size:7'],
            'days.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.is_rest_day' => ['sometimes', 'boolean'],
            'days.*.late_tolerance_minutes' => ['sometimes', 'integer', 'between:0,240'],
            'days.*.notes' => ['nullable', 'string', 'max:255'],
            'days.*.segments' => ['sometimes', 'array'],
            'days.*.segments.*.start_time' => ['required', 'date_format:H:i'],
            'days.*.segments.*.end_time' => ['required', 'date_format:H:i'],
            'days.*.segments.*.kind' => ['required', 'string', Rule::in(['work', 'break'])],
            'days.*.segments.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Validaciones que dependen del conjunto completo de días.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateSegments($validator);
                $this->validateEffectiveFrom($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del horario es obligatorio.',
            'name.max' => 'El nombre del horario no puede exceder 120 caracteres.',
            'branch_id.required' => 'La sucursal es obligatoria.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'effective_from.required' => 'La fecha de inicio de vigencia es obligatoria.',
            'effective_from.date' => 'La fecha de inicio de vigencia no es válida.',
            'effective_to.date' => 'La fecha de fin de vigencia no es válida.',
            'effective_to.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'days.required' => 'Configura los siete días de la semana.',
            'days.size' => 'Debes enviar exactamente los siete días de la semana.',
            'days.*.weekday.required' => 'Cada día debe indicar su número de día de la semana.',
            'days.*.weekday.between' => 'El día de la semana debe estar entre 1 (lunes) y 7 (domingo).',
            'days.*.weekday.distinct' => 'No se puede repetir el día de la semana.',
            'days.*.late_tolerance_minutes.between' => 'La tolerancia debe estar entre 0 y 240 minutos.',
            'days.*.segments.*.start_time.required' => 'La hora de inicio del segmento es obligatoria.',
            'days.*.segments.*.start_time.date_format' => 'La hora de inicio del segmento debe tener formato HH:MM.',
            'days.*.segments.*.end_time.required' => 'La hora de fin del segmento es obligatoria.',
            'days.*.segments.*.end_time.date_format' => 'La hora de fin del segmento debe tener formato HH:MM.',
            'days.*.segments.*.kind.in' => 'El tipo de segmento debe ser trabajo o descanso.',
        ];
    }

    private function validateSegments(Validator $validator): void
    {
        $days = $this->input('days');

        if (! is_array($days)) {
            return;
        }

        foreach (array_values($days) as $index => $day) {
            if (! is_array($day)) {
                continue;
            }

            $segments = $day['segments'] ?? [];

            if (! is_array($segments)) {
                continue;
            }

            $isRestDay = filter_var($day['is_rest_day'] ?? false, FILTER_VALIDATE_BOOL);

            if ($isRestDay && $segments !== []) {
                $validator->errors()->add(
                    "days.{$index}.segments",
                    'Un día de descanso no debe tener segmentos de horario.',
                );

                continue;
            }

            if (! $isRestDay && ! $this->hasWorkSegment($segments)) {
                $validator->errors()->add(
                    "days.{$index}.segments",
                    'El día laboral debe tener al menos un segmento de trabajo.',
                );
            }

            $error = TimeSegments::validationError($segments);

            if ($error !== null) {
                $validator->errors()->add("days.{$index}.segments", $error);
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $segments
     */
    private function hasWorkSegment(array $segments): bool
    {
        foreach ($segments as $segment) {
            if (is_array($segment) && ($segment['kind'] ?? null) === 'work') {
                return true;
            }
        }

        return false;
    }

    private function validateEffectiveFrom(Validator $validator): void
    {
        $branchId = $this->integer('branch_id');
        $effectiveFrom = $this->input('effective_from');

        if ($branchId <= 0 || ! is_string($effectiveFrom) || $effectiveFrom === '') {
            return;
        }

        $earliest = ScheduleEffectiveGuard::earliestEffectiveFrom($branchId);

        if ($earliest !== null && $effectiveFrom < $earliest) {
            $validator->errors()->add(
                'effective_from',
                "La vigencia no puede iniciar antes del último periodo de nómina cerrado ({$earliest}).",
            );
        }
    }
}
