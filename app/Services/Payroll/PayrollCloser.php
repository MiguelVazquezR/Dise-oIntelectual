<?php

namespace App\Services\Payroll;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollRunStatus;
use App\Models\PayrollItem;
use App\Models\PayrollItemConcept;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\Exceptions\PayrollPeriodClosedException;
use App\Services\Payroll\Exceptions\PayrollStateException;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Transiciones de estado de la pre-nómina: `open → calculated → reviewed → closed`.
 *
 * - `calculated` lo produce `PayrollCalculator`.
 * - `review` congela la revisión en las corridas (`reviewed_at/by`) y exige
 *   `payroll.review`.
 * - `close` exige `payroll.close`, guarda una instantánea congelada de
 *   corridas, items y conceptos en `rules_snapshot` de cada corrida y en la
 *   bitácora, y marca el periodo como no recalculable.
 */
final class PayrollCloser
{
    public function __construct(private readonly PayrollAuditRecorder $auditor) {}

    public function review(
        PayrollPeriod $period,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PayrollPeriod {
        if (! $actor->hasPermission('payroll.review')) {
            throw new AuthorizationException('No tienes permiso para revisar la pre-nómina.');
        }

        if ($period->status === PayrollPeriodStatus::Closed) {
            throw new PayrollPeriodClosedException('El periodo está cerrado: no se puede revisar.');
        }

        if ($period->status === PayrollPeriodStatus::Open) {
            throw new PayrollStateException('Primero calcula la pre-nómina del periodo antes de revisarla.');
        }

        if ($period->status === PayrollPeriodStatus::Reviewed) {
            throw new PayrollStateException('El periodo ya está revisado.');
        }

        $before = $this->auditor->snapshot($period);
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($period, $actor, $now): void {
            PayrollRun::query()
                ->where('payroll_period_id', $period->id)
                ->where('status', '!=', PayrollRunStatus::Closed)
                ->update([
                    'status' => PayrollRunStatus::Reviewed,
                    'reviewed_at' => $now,
                    'reviewed_by' => $actor->id,
                ]);

            $period->update(['status' => PayrollPeriodStatus::Reviewed]);
        });

        $period->refresh();
        $this->auditor->reviewed($period, $actor, $before, $ipAddress, $userAgent);

        return $period;
    }

    public function close(
        PayrollPeriod $period,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PayrollPeriod {
        if (! $actor->hasPermission('payroll.close')) {
            throw new AuthorizationException('No tienes permiso para cerrar periodos de pre-nómina.');
        }

        if ($period->status === PayrollPeriodStatus::Closed) {
            throw new PayrollPeriodClosedException('El periodo ya está cerrado.');
        }

        if ($period->status !== PayrollPeriodStatus::Reviewed) {
            throw new PayrollStateException('Solo se puede cerrar un periodo revisado.');
        }

        $before = $this->auditor->snapshot($period);
        $now = CarbonImmutable::now();

        $snapshot = DB::transaction(function () use ($period, $actor, $now): array {
            $runs = PayrollRun::query()
                ->where('payroll_period_id', $period->id)
                ->with([
                    'branch:id,name',
                    'items.employee:id,employee_number,first_name,last_name,second_last_name',
                    'items.concepts',
                ])
                ->orderBy('branch_id')
                ->get();

            $closedSnapshot = [
                'period' => [
                    'id' => $period->id,
                    'payroll_schedule_id' => $period->payroll_schedule_id,
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'payment_date' => $period->payment_date->toDateString(),
                ],
                'closed_at' => $now->toIso8601String(),
                'closed_by' => $actor->id,
                'runs' => [],
            ];

            foreach ($runs as $run) {
                $frozenRun = [
                    'id' => $run->id,
                    'branch' => ['id' => $run->branch_id, 'name' => $run->branch->name],
                    'status' => PayrollRunStatus::Closed->value,
                    'totals' => [
                        'earnings' => (string) $run->total_earnings,
                        'deductions' => (string) $run->total_deductions,
                        'net' => (string) $run->total_net,
                    ],
                    'items' => $run->items
                        ->map(fn (PayrollItem $item): array => [
                            'id' => $item->id,
                            'employee_id' => $item->employee_id,
                            'employee_number' => $item->employee->employee_number,
                            'base_amount' => (string) $item->base_amount,
                            'overtime_amount' => (string) $item->overtime_amount,
                            'bonus_amount' => (string) $item->bonus_amount,
                            'adjustment_amount' => (string) $item->adjustment_amount,
                            'absence_deduction' => (string) $item->absence_deduction,
                            'net_amount' => (string) $item->net_amount,
                            'concepts' => $item->concepts
                                ->map(fn (PayrollItemConcept $concept): array => [
                                    'code' => $concept->code,
                                    'name' => $concept->name,
                                    'type' => $concept->type->value,
                                    'quantity' => (string) $concept->quantity,
                                    'unit_amount' => (string) $concept->unit_amount,
                                    'amount' => (string) $concept->amount,
                                    'rule_applied' => $concept->rule_applied,
                                    'source_type' => $concept->source_type,
                                    'source_id' => $concept->source_id,
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                ];

                $snapshot = $run->rules_snapshot ?? [];
                $snapshot['frozen'] = $frozenRun;

                $run->update([
                    'status' => PayrollRunStatus::Closed,
                    'closed_at' => $now,
                    'closed_by' => $actor->id,
                    'rules_snapshot' => $snapshot,
                ]);

                $closedSnapshot['runs'][] = $frozenRun;
            }

            $period->update([
                'status' => PayrollPeriodStatus::Closed,
                'closed_at' => $now,
                'closed_by' => $actor->id,
            ]);

            return $closedSnapshot;
        });

        $period->refresh();
        $this->auditor->closed($period, $actor, $before, ['closed_snapshot' => $snapshot], $ipAddress, $userAgent);

        return $period;
    }
}
