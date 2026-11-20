<?php

namespace App\Http\Requests\Holidays;

use App\Enums\HolidayType;
use App\Models\Holiday;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->hasPermission('holidays.manage')) {
            return false;
        }

        $branchId = $this->integer('branch_id');

        return $branchId <= 0 || BranchAccess::allows($user, $branchId);
    }

    /**
     * Normaliza cadenas y vacíos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'legal_basis' => $this->normalizeNullable('legal_basis'),
            'source_url' => $this->normalizeNullable('source_url'),
            'branch_id' => $this->input('branch_id') === '' ? null : $this->input('branch_id'),
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
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', 'string', Rule::in(HolidayType::values())],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'legal_basis' => ['nullable', 'string', 'max:120', 'required_if:type,'.HolidayType::Mandatory->value],
            'source_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * Reglas de negocio que dependen del conjunto de datos.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('type');
            $branchId = $this->input('branch_id');

            if ($type === HolidayType::Mandatory->value && $branchId !== null) {
                $validator->errors()->add(
                    'branch_id',
                    'Los festivos obligatorios aplican a todas las sucursales; deja la sucursal vacía.',
                );
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $duplicate = Holiday::query()
                ->whereDate('date', (string) $this->input('date'))
                ->where('type', $type)
                ->when(
                    $branchId !== null,
                    fn ($query) => $query->where('branch_id', (int) $branchId),
                    fn ($query) => $query->whereNull('branch_id'),
                )
                ->exists();

            if ($duplicate) {
                $validator->errors()->add(
                    'date',
                    'Ya existe un festivo de ese tipo en esa fecha para la misma sucursal.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del festivo es obligatorio.',
            'name.max' => 'El nombre del festivo no puede exceder 120 caracteres.',
            'date.required' => 'La fecha del festivo es obligatoria.',
            'date.date_format' => 'La fecha del festivo debe tener el formato AAAA-MM-DD.',
            'type.required' => 'El tipo de festivo es obligatorio.',
            'type.in' => 'El tipo de festivo no es válido.',
            'branch_id.exists' => 'La sucursal indicada no existe.',
            'legal_basis.required_if' => 'La base legal es obligatoria en los festivos obligatorios (LFT art. 74).',
            'legal_basis.max' => 'La base legal no puede exceder 120 caracteres.',
            'source_url.url' => 'La fuente debe ser una URL válida.',
        ];
    }

    private function normalizeNullable(string $key): mixed
    {
        $value = $this->input($key);

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }
}
