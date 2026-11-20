<?php

namespace App\Http\Requests\Branches;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends StoreBranchRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['code'] = [
            'required',
            'string',
            'max:20',
            Rule::unique('branches', 'code')->ignore($this->route('branch')),
        ];

        return $rules;
    }
}
