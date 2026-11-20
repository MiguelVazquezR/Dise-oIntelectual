<?php

namespace App\Http\Requests\Vacations;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexVacationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('vacations.view');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.integer' => 'El año debe ser un número entero.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'employee_id.exists' => 'El colaborador indicado no existe.',
        ];
    }
}
