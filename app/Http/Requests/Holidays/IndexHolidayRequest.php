<?php

namespace App\Http\Requests\Holidays;

use App\Enums\HolidayType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexHolidayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('holidays.view');
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
            'type' => ['nullable', 'string', Rule::in(HolidayType::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.integer' => 'El año debe ser un número entero.',
            'year.min' => 'El año debe ser mayor o igual a 2000.',
            'year.max' => 'El año debe ser menor o igual a 2100.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'type.in' => 'El tipo de festivo no es válido.',
        ];
    }
}
