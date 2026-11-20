<?php

use App\Services\Vacations\VacationAccrualRules;
use Carbon\CarbonImmutable;

test('la tabla de antigüedad del artículo 76 otorga los días esperados', function (int $years, int $expected): void {
    expect(VacationAccrualRules::daysForYears($years))->toBe($expected);
})->with([
    [0, 0],
    [1, 12],
    [2, 14],
    [3, 16],
    [4, 18],
    [5, 20],
    [6, 22],
    [10, 22],
    [11, 24],
    [15, 24],
    [16, 26],
    [20, 26],
    [21, 28],
    [25, 28],
    [26, 30],
    [30, 30],
    [31, 32],
    [35, 32],
    [36, 34],
    [40, 34],
    [41, 36],
]);

test('los años de servicio se cuentan desde el aniversario de ingreso', function (): void {
    $hireDate = CarbonImmutable::parse('2019-03-04');

    expect(VacationAccrualRules::completedYears($hireDate, CarbonImmutable::parse('2020-03-03')))->toBe(0)
        ->and(VacationAccrualRules::completedYears($hireDate, CarbonImmutable::parse('2020-03-04')))->toBe(1)
        ->and(VacationAccrualRules::completedYears($hireDate, CarbonImmutable::parse('2025-03-04')))->toBe(6)
        ->and(VacationAccrualRules::daysForHireDate($hireDate, CarbonImmutable::parse('2025-03-04')))->toBe(22)
        ->and(VacationAccrualRules::daysForHireDate($hireDate, CarbonImmutable::parse('2026-03-03')))->toBe(22)
        ->and(VacationAccrualRules::daysForHireDate($hireDate, CarbonImmutable::parse('2026-03-04')))->toBe(22);
});

test('la tabla publicada expone once tramos con su base legal y fuente pendiente', function (): void {
    $table = VacationAccrualRules::table();

    expect($table)->toHaveCount(11)
        ->and($table[0])->toMatchArray([
            'years_from' => 1,
            'years_to' => 1,
            'years_label' => '1 año',
            'days' => 12,
            'legal_basis' => 'LFT art. 76',
        ])
        ->and($table[10]['years_label'])->toBe('31 a 35 años')
        ->and($table[10]['days'])->toBe(32)
        ->and(VacationAccrualRules::SOURCE_CHECKED_AT)->toBeNull()
        ->and(VacationAccrualRules::validationNotice())->toContain('PENDIENTE DE VALIDACIÓN');
});
