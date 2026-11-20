<?php

namespace App\Http\Requests\Schedules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('schedules.view') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.integer' => 'La sucursal indicada no es válida.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'search.max' => 'La búsqueda no puede exceder 120 caracteres.',
        ];
    }
}
