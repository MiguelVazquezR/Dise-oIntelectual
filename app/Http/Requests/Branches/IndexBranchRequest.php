<?php

namespace App\Http\Requests\Branches;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('branches.view') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'La búsqueda no puede exceder 120 caracteres.',
            'status.in' => 'El estatus debe ser activo o inactivo.',
        ];
    }
}
