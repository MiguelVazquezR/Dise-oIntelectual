<?php

namespace App\Http\Requests\Aws;

use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del consumo de AWS Rekognition (§7.4).
 */
class IndexAwsUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('aws.usage.view') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'operation' => ['nullable', 'string', Rule::in(AwsFaceOperation::values())],
            'source' => ['nullable', 'string', Rule::in(AwsUsageSource::values())],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
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
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'operation.in' => 'La operación solicitada no es válida.',
            'source.in' => 'El origen solicitado no es válido.',
            'per_page.in' => 'El tamaño de página debe ser 10, 25, 50 o 100.',
        ];
    }
}
