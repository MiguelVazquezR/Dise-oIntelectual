<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta manual de un periodo de pre-nómina (§7.4).
 *
 * La combinación (`payroll_schedule_id`, `start_date`) es única; el mensaje se
 * muestra en español y el controlador responde con `back()->withErrors()`.
 */
class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payroll.calculate') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payroll_schedule_id' => ['required', 'integer', Rule::exists('payroll_schedules', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'payment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:end_date'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $exists = PayrollPeriod::query()
                    ->where('payroll_schedule_id', $this->integer('payroll_schedule_id'))
                    ->whereDate('start_date', $this->string('start_date')->toString())
                    ->exists();

                if (! $exists) {
                    return;
                }

                $validator->errors()->add(
                    'start_date',
                    'Ya existe un periodo de este esquema con la misma fecha de inicio.',
                );
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payroll_schedule_id.required' => 'Selecciona el esquema de pago.',
            'payroll_schedule_id.exists' => 'El esquema de pago no existe o está inactivo.',
            'start_date.required' => 'La fecha inicial es obligatoria.',
            'start_date.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'end_date.required' => 'La fecha final es obligatoria.',
            'end_date.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'end_date.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'payment_date.required' => 'La fecha de pago es obligatoria.',
            'payment_date.date_format' => 'La fecha de pago debe tener el formato AAAA-MM-DD.',
            'payment_date.after_or_equal' => 'La fecha de pago no puede ser anterior al fin del periodo.',
        ];
    }
}
