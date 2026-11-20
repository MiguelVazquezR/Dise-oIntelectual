<?php

namespace App\Http\Controllers;

use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageSource;
use App\Http\Requests\Aws\IndexAwsUsageRequest;
use App\Models\AwsUsageRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Services\Aws\AwsCostCalculator;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use App\Support\Pagination\PaginatedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consumo interno de AWS Rekognition (`/aws/usage`, §7.4).
 *
 * Muestra los totales facturables calculados por `AwsCostCalculator`, el
 * detalle paginado y la tarifa interna acordada. Los registros `source=demo`
 * corresponden a simulaciones y la página debe etiquetarlos como tales.
 */
class AwsUsageController extends Controller
{
    private const DEFAULT_TIMEZONE = 'America/Mexico_City';

    private const DEFAULT_DAYS = 30;

    private const PRICING_NOTE = 'Cargo interno de demostración; no es la tarifa oficial de AWS.';

    public function __construct(private readonly AwsCostCalculator $costCalculator) {}

    public function index(IndexAwsUsageRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        abort_if($branchId !== null && ! BranchAccess::allows($user, $branchId), 403);

        $operation = isset($filters['operation']) ? AwsFaceOperation::from((string) $filters['operation']) : null;
        $source = isset($filters['source']) ? AwsUsageSource::from((string) $filters['source']) : null;
        $perPage = (int) ($filters['per_page'] ?? 25);
        $timezone = $this->timezone($branchId);

        [$from, $to] = $this->period($timezone, $filters['from'] ?? null, $filters['to'] ?? null);

        $totals = $this->costCalculator->totals($this->usageQuery($branchId, $operation, $source, $from, $to));

        $records = $this->usageQuery($branchId, $operation, $source, $from, $to)
            ->with([
                'branch:id,name',
                'employee:id,first_name,last_name,second_last_name,employee_number',
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AwsUsageRecord $record): array => [
                'id' => $record->id,
                'occurred_at' => $record->occurred_at->utc()->toIso8601String(),
                'branch' => $record->branch instanceof Branch
                    ? ['id' => $record->branch->id, 'name' => $record->branch->name]
                    : null,
                'employee' => $record->employee instanceof Employee
                    ? [
                        'id' => $record->employee->id,
                        'full_name' => $record->employee->fullName(),
                        'employee_number' => $record->employee->employee_number,
                    ]
                    : null,
                'operation' => $record->operation->value,
                'quantity' => $record->quantity,
                'status' => $record->status->value,
                'source' => $record->source->value,
                'aws_request_id' => $record->aws_request_id,
                'amount_mxn' => (string) $record->amount_mxn,
            ]);

        return Inertia::render('aws/Usage', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch_id' => $branchId,
                'operation' => $operation?->value,
                'source' => $source?->value,
                'per_page' => $perPage,
            ],
            'totals' => $totals,
            'records' => PaginatedPayload::from($records),
            'pricing' => [
                'price_per_1000' => $this->costCalculator->pricePerThousandForDisplay(),
                'currency' => 'MXN',
                'note' => self::PRICING_NOTE,
            ],
            'can' => [
                'export' => false,
            ],
            'options' => [
                'branches' => BranchOptions::forUser($user),
                'operations' => array_map(
                    fn (AwsFaceOperation $operation): array => ['value' => $operation->value, 'label' => $operation->label()],
                    AwsFaceOperation::cases(),
                ),
                'sources' => array_map(
                    fn (AwsUsageSource $source): array => ['value' => $source->value, 'label' => $source->label()],
                    AwsUsageSource::cases(),
                ),
            ],
        ]);
    }

    /**
     * @return Builder<AwsUsageRecord>
     */
    private function usageQuery(
        ?int $branchId,
        ?AwsFaceOperation $operation,
        ?AwsUsageSource $source,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): Builder {
        return BranchAccess::scope(AwsUsageRecord::query())
            ->whereBetween('occurred_at', [$from->utc(), $to->utc()])
            ->when($branchId !== null, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when(
                $operation instanceof AwsFaceOperation,
                fn (Builder $query): Builder => $query->where('operation', $operation->value),
            )
            ->when(
                $source instanceof AwsUsageSource,
                fn (Builder $query): Builder => $query->where('source', $source->value),
            );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(string $timezone, ?string $from, ?string $to): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $fromDate = $from !== null
            ? CarbonImmutable::parse($from, $timezone)->startOfDay()
            : $today->subDays(self::DEFAULT_DAYS - 1);

        $toDate = $to !== null
            ? CarbonImmutable::parse($to, $timezone)->endOfDay()
            : $today->endOfDay();

        if ($toDate->lessThan($fromDate)) {
            $toDate = $fromDate->endOfDay();
        }

        return [$fromDate, $toDate];
    }

    private function timezone(?int $branchId): string
    {
        if ($branchId === null) {
            return self::DEFAULT_TIMEZONE;
        }

        $timezone = Branch::query()->whereKey($branchId)->value('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : self::DEFAULT_TIMEZONE;
    }
}
