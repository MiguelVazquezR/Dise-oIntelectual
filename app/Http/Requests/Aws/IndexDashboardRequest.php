<?php

namespace App\Http\Requests\Aws;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del dashboard administrativo (§7.4).
 */
class IndexDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('dashboard.view') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period_id' => ['nullable', 'integer', Rule::exists('payroll_periods', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_id.exists' => 'El periodo de nómina indicado no existe.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
        ];
    }
}
