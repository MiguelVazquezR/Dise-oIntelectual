<?php

use App\Models\Holiday;
use App\Services\Holidays\HolidayGenerator;
use App\Services\Holidays\LegalHolidayRules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
    date_default_timezone_set('UTC');
});

/**
 * Fechas ISO de las reglas de un año.
 *
 * @return array<int, string>
 */
function holidayDates(int $year): array
{
    return array_map(
        fn (array $rule): string => $rule['date']->toDateString(),
        LegalHolidayRules::forYear($year),
    );
}

test('genera los festivos obligatorios de 2026 con los lunes trasladados', function (): void {
    $dates = holidayDates(2026);

    expect($dates)->toBe([
        '2026-01-01',
        '2026-02-02',
        '2026-03-16',
        '2026-05-01',
        '2026-09-16',
        '2026-11-16',
        '2026-12-25',
    ]);

    $rules = LegalHolidayRules::forYear(2026);
    expect($rules[0]['legal_basis'])->toBe('LFT art. 74, fracción I')
        ->and($rules[0]['source_url'])->toBe(LegalHolidayRules::SOURCE_URL)
        ->and($rules[0]['source_checked_at'])->toBeNull()
        ->and(LegalHolidayRules::validationNotice())->toContain('PENDIENTE DE VALIDACIÓN');
});

test('genera 2027 con jornada electoral federal y sin transmisión del ejecutivo', function (): void {
    $dates = holidayDates(2027);

    expect($dates)->toBe([
        '2027-01-01',
        '2027-02-01',
        '2027-03-15',
        '2027-05-01',
        '2027-06-06',
        '2027-09-16',
        '2027-11-15',
        '2027-12-25',
    ]);

    $election = collect(LegalHolidayRules::forYear(2027))
        ->firstWhere('key', 'election_day');

    expect($election['name'])->toBe('Jornada electoral federal')
        ->and($election['legal_basis'])->toBe('LFT art. 74, fracción IX')
        ->and($election['date']->isSunday())->toBeTrue();
});

test('el 1 de octubre solo aplica en años de transmisión del Poder Ejecutivo (sexenal)', function (): void {
    expect(LegalHolidayRules::isPowerTransferYear(2024))->toBeTrue()
        ->and(LegalHolidayRules::isPowerTransferYear(2026))->toBeFalse()
        ->and(LegalHolidayRules::isPowerTransferYear(2030))->toBeTrue()
        ->and(LegalHolidayRules::isPowerTransferYear(2036))->toBeTrue()
        ->and(LegalHolidayRules::isPowerTransferYear(2020))->toBeFalse();

    $rules2030 = collect(LegalHolidayRules::forYear(2030));
    $transfer = $rules2030->firstWhere('key', 'power_transfer');

    expect($transfer['date']->toDateString())->toBe('2030-10-01')
        ->and($transfer['legal_basis'])->toBe('LFT art. 74, fracción VII');

    expect(holidayDates(2030))->toContain('2030-06-02')
        ->and(holidayDates(2031))->not->toContain('2031-10-01')
        ->and(holidayDates(2026))->not->toContain('2026-10-01');
});

test('las reglas de un año no se repiten entre sí', function (): void {
    foreach ([2026, 2027, 2030, 2031] as $year) {
        $dates = holidayDates($year);

        expect(array_unique($dates))->toHaveCount(count($dates));
    }
});

test('generar dos veces el mismo año no duplica festivos ni pisa los de empresa', function (): void {
    Carbon::setTestNow('2026-11-19 10:00:00');

    $company = Holiday::query()->create([
        'name' => 'Aniversario de la empresa',
        'date' => '2026-12-31',
        'type' => 'company',
        'branch_id' => null,
        'legal_basis' => null,
    ]);

    $generator = app(HolidayGenerator::class);

    $first = $generator->forYear(2026);
    $second = $generator->forYear(2026);

    expect($first['created'])->toBe(7)
        ->and($second['created'])->toBe(0)
        ->and($second['skipped'] + $second['refreshed'])->toBe(7)
        ->and(Holiday::query()->whereYear('date', 2026)->count())->toBe(8)
        ->and(Holiday::query()->where('name', 'Aniversario de la empresa')->value('branch_id'))->toBeNull()
        ->and($company->fresh()->type->value)->toBe('company');
});

test('un año cerrado no se regenera sin autorización explícita', function (): void {
    Carbon::setTestNow('2026-11-19 10:00:00');

    $generator = app(HolidayGenerator::class);

    $locked = $generator->forYear(2025);

    expect($locked['locked'])->toBeTrue()
        ->and($locked['created'])->toBe(0)
        ->and(Holiday::query()->whereYear('date', 2025)->count())->toBe(0);

    $forced = $generator->forYear(2025, true);

    expect($forced['locked'])->toBeFalse()
        ->and($forced['created'])->toBe(7);
});
