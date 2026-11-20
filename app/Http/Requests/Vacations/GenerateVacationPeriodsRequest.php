<?php

namespace App\Http\Requests\Vacations;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateVacationPeriodsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('vacations.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.required' => 'Indica el año del que se generarán los periodos.',
            'year.integer' => 'El año debe ser un número entero.',
            'year.min' => 'El año debe ser mayor o igual a 2000.',
            'year.max' => 'El año debe ser menor o igual a 2100.',
        ];
    }
}
