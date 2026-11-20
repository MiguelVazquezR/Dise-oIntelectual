<?php

namespace App\Http\Requests\Aws;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de la bitácora de auditoría (§7.4).
 */
class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('audit.view') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.max' => 'La acción no puede exceder 120 caracteres.',
            'user_id.exists' => 'El usuario indicado no existe.',
            'from.date' => 'La fecha inicial no es válida.',
            'to.date' => 'La fecha final no es válida.',
            'to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'per_page.in' => 'El tamaño de página debe ser 10, 25, 50 o 100.',
        ];
    }
}
