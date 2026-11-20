<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends StoreUserRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $target = $this->route('user');

        if (! $user instanceof User || ! $user->hasPermission('users.manage')) {
            return false;
        }

        return ! $target instanceof User || BranchAccess::sharesBranchWith($user, $target);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['email'] = [
            'required',
            'string',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($this->route('user')),
        ];

        $rules['password'] = ['nullable', 'string', 'min:12', 'max:255'];

        return $rules;
    }
}
