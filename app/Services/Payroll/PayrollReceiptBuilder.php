<?php

namespace App\Services\Payroll;

use App\Enums\PayrollConceptType;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollItemConcept;

/**
 * Arma el payload del recibo imprimible (contrato §7.4).
 *
 * El documento es preliminar: no incluye UUID, sellos ni datos fiscales
 * (D-14). Los importes se presentan como cadena decimal de 2 posiciones y los
 * totales se derivan de los conceptos del item.
 */
final class PayrollReceiptBuilder
{
    public function __construct(private readonly PayrollRules $rules) {}

    /**
     * @return array<string, mixed>
     */
    public function build(PayrollItem $item): array
    {
        $item->loadMissing(['employee', 'run.branch', 'run.period', 'concepts']);

        $employee = $item->employee;
        $run = $item->run;
        $period = $run->period;
        $branch = $run->branch;

        $earnings = 0;
        $deductions = 0;

        $concepts = $item->concepts
            ->sortBy('id')
            ->map(function (PayrollItemConcept $concept) use (&$earnings, &$deductions): array {
                $cents = PayrollMoney::toCents((string) $concept->amount);

                if ($concept->type === PayrollConceptType::Earning) {
                    $earnings += $cents;
                } else {
                    $deductions += $cents;
                }

                return [
                    'code' => $concept->code,
                    'name' => $concept->name,
                    'type' => $concept->type->value,
                    'quantity' => (string) $concept->quantity,
                    'unit_amount' => (string) $concept->unit_amount,
                    'amount' => (string) $concept->amount,
                ];
            })
            ->values()
            ->all();

        /** @var array<string, mixed> $company */
        $company = (array) config('payroll.company', []);

        return [
            'item' => [
                'id' => $item->id,
                'employee' => [
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->fullName(),
                    'position' => $employee->position,
                ],
                'branch' => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ],
                'period' => [
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'payment_date' => $period->payment_date->toDateString(),
                ],
                'concepts' => $concepts,
                'totals' => [
                    'earnings' => PayrollMoney::toDecimal($earnings),
                    'deductions' => PayrollMoney::toDecimal($deductions),
                    'net' => PayrollMoney::toDecimal($earnings - $deductions),
                ],
            ],
            'company' => [
                'name' => $company['name'] ?? config('app.name'),
                'logo_url' => $company['logo_url'] ?? null,
                'address' => $company['address'] ?? null,
            ],
            'notice' => $this->rules->receiptNotice(),
            'signature_lines' => $this->signatureLines($employee),
        ];
    }

    /**
     * Líneas de firma del recibo. Se acepta tanto un texto como un objeto
     * `{label, name}`; `name = "employee"` se sustituye por el nombre del
     * colaborador (contrato §7.4 no fija la forma).
     *
     * @return array<int, array{label: string, name: string|null}>
     */
    private function signatureLines(Employee $employee): array
    {
        $lines = (array) config('payroll.signature_lines', []);

        return array_map(
            function (mixed $line) use ($employee): array {
                if (is_string($line)) {
                    return ['label' => $line, 'name' => null];
                }

                $definition = is_array($line) ? $line : [];
                $name = $definition['name'] ?? null;

                return [
                    'label' => (string) ($definition['label'] ?? 'Firma'),
                    'name' => $name === 'employee'
                        ? $employee->fullName()
                        : (is_string($name) ? $name : null),
                ];
            },
            $lines,
        );
    }
}
