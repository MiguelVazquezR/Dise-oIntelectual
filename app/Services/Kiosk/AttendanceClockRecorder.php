<?php

namespace App\Services\Kiosk;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\Attendance\WorkDateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registro compartido de marcajes del checador (PIN y reconocimiento facial).
 *
 * Centraliza la fecha laboral (`WorkDateResolver`), el estado de validación y
 * la regla de doble marcaje de 60 segundos para que ambos métodos se comporten
 * igual: un segundo marcaje del mismo tipo dentro de la ventana no duplica el
 * evento, lo marca como `flagged` y el controlador responde con error.
 *
 * No persiste imágenes, PIN ni datos biométricos: solo metadatos no sensibles.
 */
final class AttendanceClockRecorder
{
    /** Ventana de idempotencia de doble marcaje (segundos). */
    public const DUPLICATE_WINDOW_SECONDS = 60;

    /** Nota que se agrega al evento cuando se intenta un doble marcaje. */
    public const DUPLICATE_NOTE = 'Intento de doble marcaje dentro de 60 segundos.';

    public function __construct(private readonly WorkDateResolver $workDateResolver) {}

    /**
     * @param  array{latitude?: mixed, longitude?: mixed, accuracy?: mixed}|null  $gps
     * @param  array<string, mixed>  $metadata  Metadatos no sensibles del método de marcaje.
     */
    public function record(
        Employee $employee,
        Branch $branch,
        AttendanceEventType $type,
        AttendanceMethod $method,
        ?array $gps = null,
        array $metadata = [],
        AttendanceStatus $validationStatus = AttendanceStatus::Valid,
        ?string $validationNotes = null,
        ?CarbonImmutable $occurredAt = null,
        ?string $ipAddress = null,
        ?string $deviceLabel = null,
    ): ClockRecordOutcome {
        $occurredAt ??= CarbonImmutable::now();
        $gps = $this->normalizeGps($gps);

        /** @var array{duplicate: AttendanceEvent}|array{event: AttendanceEvent} $result */
        $result = DB::transaction(function () use (
            $employee,
            $branch,
            $type,
            $method,
            $gps,
            $metadata,
            $validationStatus,
            $validationNotes,
            $occurredAt,
            $ipAddress,
            $deviceLabel,
        ): array {
            $existing = $this->findRecentDuplicate($employee, $type, $occurredAt);

            if ($existing instanceof AttendanceEvent) {
                $existing->update([
                    'validation_status' => AttendanceStatus::Flagged,
                    'validation_notes' => self::DUPLICATE_NOTE,
                ]);

                return ['duplicate' => $existing];
            }

            $event = AttendanceEvent::query()->create([
                'employee_id' => $employee->id,
                'branch_id' => $branch->id,
                'type' => $type,
                'occurred_at' => $occurredAt,
                'work_date' => $this->workDateResolver->resolve($branch, $occurredAt)->toDateString(),
                'method' => $method,
                'gps_latitude' => $gps !== null ? $this->decimal($gps['latitude'], 7) : null,
                'gps_longitude' => $gps !== null ? $this->decimal($gps['longitude'], 7) : null,
                'gps_accuracy_m' => $gps !== null && isset($gps['accuracy']) ? $this->decimal($gps['accuracy'], 2) : null,
                'validation_status' => $validationStatus,
                'validation_notes' => $validationNotes,
                'ip_address' => $ipAddress,
                'device_label' => $deviceLabel,
                'metadata' => array_merge([
                    'gps_missing' => $gps === null,
                    'recorded_via' => 'kiosk',
                ], $metadata),
            ]);

            return ['event' => $event];
        });

        if (isset($result['duplicate'])) {
            return ClockRecordOutcome::duplicate($result['duplicate']);
        }

        return ClockRecordOutcome::recorded($result['event']);
    }

    /**
     * Mensaje de éxito del checador, en la hora local de la sucursal.
     */
    public static function successMessage(AttendanceEventType $type, string $localTime): string
    {
        return match ($type) {
            AttendanceEventType::ClockIn => "Entrada registrada {$localTime}",
            AttendanceEventType::BreakStart => "Inicio de descanso registrado {$localTime}",
            AttendanceEventType::BreakEnd => "Fin de descanso registrado {$localTime}",
            AttendanceEventType::ClockOut => "Salida registrada {$localTime}",
        };
    }

    private function findRecentDuplicate(Employee $employee, AttendanceEventType $type, CarbonImmutable $occurredAt): ?AttendanceEvent
    {
        return AttendanceEvent::query()
            ->where('employee_id', $employee->id)
            ->where('type', $type)
            ->whereBetween('occurred_at', [
                $occurredAt->subSeconds(self::DUPLICATE_WINDOW_SECONDS),
                $occurredAt->addSecond(),
            ])
            ->orderByDesc('occurred_at')
            ->first();
    }

    /**
     * @param  array<array-key, mixed>|null  $gps
     * @return array{latitude: mixed, longitude: mixed, accuracy?: mixed}|null
     */
    private function normalizeGps(?array $gps): ?array
    {
        if ($gps === null || ! isset($gps['latitude'], $gps['longitude'])) {
            return null;
        }

        return $gps;
    }

    private function decimal(mixed $value, int $precision): string
    {
        return number_format((float) $value, $precision, '.', '');
    }
}
