<?php

namespace App\Http\Requests\Branches;

use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('branches.manage') === true;
    }

    /**
     * Normaliza cadenas y convierte campos opcionales vacíos en `null`.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'code' => is_string($this->input('code')) ? mb_strtoupper(trim($this->input('code'))) : $this->input('code'),
            'address' => is_string($this->input('address')) ? trim($this->input('address')) : $this->input('address'),
            'latitude' => $this->normalizeNullable('latitude'),
            'longitude' => $this->normalizeNullable('longitude'),
            'geofence_radius_m' => $this->normalizeNullable('geofence_radius_m'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:20', Rule::unique('branches', 'code')],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['nullable', 'integer', 'between:10,5000'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la sucursal es obligatorio.',
            'name.max' => 'El nombre no puede exceder 120 caracteres.',
            'code.required' => 'El código de sucursal es obligatorio.',
            'code.max' => 'El código no puede exceder 20 caracteres.',
            'code.unique' => 'Ya existe una sucursal con ese código.',
            'address.required' => 'La dirección es obligatoria.',
            'latitude.numeric' => 'La latitud debe ser un número decimal.',
            'latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'longitude.numeric' => 'La longitud debe ser un número decimal.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
            'geofence_radius_m.integer' => 'El radio de geocerca debe ser un número entero.',
            'geofence_radius_m.between' => 'El radio de geocerca debe estar entre 10 y 5000 metros.',
            'timezone.required' => 'La zona horaria es obligatoria.',
            'timezone.in' => 'La zona horaria debe ser una zona IANA válida.',
            'is_active.boolean' => 'El estatus de la sucursal no es válido.',
        ];
    }

    private function normalizeNullable(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' ? null : $value;
    }
}
