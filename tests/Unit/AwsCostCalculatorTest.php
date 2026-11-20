<?php

use App\Enums\AwsUsageStatus;
use App\Services\Aws\AwsCostCalculator;
use Tests\TestCase;

uses(TestCase::class);

function awsCostCalculator(string $pricePerThousand = '24'): AwsCostCalculator
{
    config(['rekognition.price_per_1000' => $pricePerThousand]);

    return new AwsCostCalculator;
}

test('mil rostros a 24 pesos por millar cuestan 24 pesos', function (): void {
    expect(awsCostCalculator()->amountFor(1000))->toBe('24.000000');
});

test('un rostro cuesta 0.024 pesos', function (): void {
    expect(awsCostCalculator()->amountFor(1))->toBe('0.024000');
    expect(awsCostCalculator()->amountFor(7))->toBe('0.168000');
});

test('la tarifa interna se presenta como cadena con dos decimales', function (): void {
    expect(awsCostCalculator('24')->pricePerThousandForDisplay())->toBe('24.00')
        ->and(awsCostCalculator('24.5')->pricePerThousandForDisplay())->toBe('24.50')
        ->and(awsCostCalculator('24.125')->pricePerThousandForDisplay())->toBe('24.13')
        ->and(awsCostCalculator('0.0005')->pricePerThousandForDisplay())->toBe('0.00');
});

test('el importe se calcula con la tarifa configurada', function (): void {
    expect(awsCostCalculator('10')->amountFor(1))->toBe('0.010000');
    expect(awsCostCalculator('10')->amountFor(500))->toBe('5.000000');
});

test('el importe se redondea a seis decimales con medio hacia arriba', function (): void {
    expect(awsCostCalculator('0.0005')->amountFor(1))->toBe('0.000001');
    expect(awsCostCalculator('3.333333')->amountFor(1))->toBe('0.003333');
    expect(awsCostCalculator('24')->amountFor(0))->toBe('0.000000');
});

test('las operaciones fallidas no se cobran', function (): void {
    $calculator = awsCostCalculator();

    expect($calculator->amountForStatus(AwsUsageStatus::Failed, 5))->toBe('0.000000');
    expect($calculator->amountForStatus(AwsUsageStatus::Succeeded, 5))->toBe('0.120000');
});

test('los importes se suman con enteros y no con flotantes', function (): void {
    $calculator = awsCostCalculator();

    expect($calculator->add('0.024000', '0.000001'))->toBe('0.024001');
    expect($calculator->add('24.000000', '0.024000'))->toBe('24.024000');
    expect($calculator->formatMicro($calculator->toMicro('0.024000')))->toBe('0.024000');
});
