<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rango de fechas de «Mi asistencia» (sin permisos adicionales: el usuario solo
 * consulta el colaborador vinculado a su cuenta).
 */
class MyAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
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
        ];
    }
}
