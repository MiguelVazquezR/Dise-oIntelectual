<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pre-nómina de control interno (OLA 4-A)
    |--------------------------------------------------------------------------
    |
    | Estos valores son PARÁMETROS INTERNOS de la pre-nómina de demostración.
    | NO son reglas fiscales ni laborales: la aplicación no calcula ISR, IMSS,
    | INFONAVIT, prima vacacional, aguinaldo ni ningún otro concepto de ley
    | (D-14). Sirven únicamente para producir un desglose reproducible que el
    | cliente revisa antes de cerrar el periodo.
    |
    | Todo el dinero se calcula en centavos enteros; los factores se guardan
    | como flotantes solo para lectura humana y se convierten a puntos base
    | (1.0 = 10000) antes de operar, por lo que nunca se acumula `float`.
    |
    */

    'currency' => env('PAYROLL_CURRENCY', 'MXN'),

    /*
    | Versión del juego de reglas internas. Se guarda en `rules_snapshot` para
    | poder reproducir un cálculo histórico aunque cambien los parámetros.
    */
    'rules_version' => 'payroll-rules-2026.11',

    /*
    | Propuesta interna (no legal) para horas extra: minutos extra del motor de
    | asistencias × tarifa horaria × factor. 1.0 deja las horas extra a tarifa
    | normal; el cliente puede capturar el multiplicador que decida aplicar sin
    | que la aplicación afirme que es el de la LFT.
    */
    'overtime_factor' => (float) env('PAYROLL_OVERTIME_FACTOR', 1.0),

    /*
    | Propuesta interna para faltas injustificadas: días de falta × tarifa diaria
    | × factor. 1.0 descuenta un día de sueldo por día de falta.
    */
    'absence_factor' => (float) env('PAYROLL_ABSENCE_FACTOR', 1.0),

    /*
    | Tratamiento de retardos. `none` = solo informativo: los minutos se guardan
    | en `payroll_items.late_minutes` y NO generan descuento automático. Se deja
    | la clave para poder añadir una política sin tocar la calculadora.
    */
    'late_rounding' => env('PAYROLL_LATE_ROUNDING', 'none'),

    /*
    | Días laborables nominales por frecuencia de pago. Se usan para la tarifa
    | diaria (descuento por falta) y la tarifa horaria (horas extra). Son días
    | de trabajo esperados, no días naturales.
    */
    'default_work_days' => [
        'weekly' => 6,
        'biweekly' => 12,
        'monthly' => 24,
    ],

    /*
    | Días naturales nominales de un periodo de cada frecuencia. Se usan para
    | prorratear el sueldo del colaborador (`base_salary`) cuando el periodo de
    | pre-nómina no coincide con su frecuencia de pago:
    |   importe = sueldo × (días naturales del periodo ÷ días nominales).
    */
    'frequency_days' => [
        'weekly' => 7,
        'biweekly' => 15,
        'monthly' => 30,
    ],

    /*
    | Jornada nominal usada para convertir la tarifa diaria en tarifa horaria.
    */
    'hours_per_day' => env('PAYROLL_HOURS_PER_DAY', 8),

    /*
    | Aviso permanente de la pre-nómina (se muestra en el listado, el detalle y
    | los recibos). Texto obligatorio de la OLA 4, alineado con D-14: la frase
    | "Pre-nómina para revisión; no constituye CFDI ni cálculo fiscal
    | definitivo" debe enviarse literalmente al frontend.
    */
    'notice' => 'Pre-nómina para revisión; no constituye CFDI ni cálculo fiscal definitivo. Cálculo de control interno: no incluye ISR, IMSS, INFONAVIT ni prestaciones de ley (D-14).',

    /*
    | Aviso del recibo imprimible. Sin UUID, sellos ni datos fiscales.
    */
    'receipt_notice' => 'Documento preliminar; no constituye CFDI ni cálculo fiscal definitivo.',

    /*
    | Datos de presentación del recibo. No incluir RFC, régimen fiscal ni
    | domicilio fiscal real: la demo no guarda datos fiscales (D-12).
    */
    'company' => [
        'name' => env('PAYROLL_COMPANY_NAME', env('APP_NAME', 'Diseño Intelectual')),
        'logo_url' => env('PAYROLL_COMPANY_LOGO_URL', '/images/logo.jpeg'),
        'address' => env('PAYROLL_COMPANY_ADDRESS'),
    ],

    /*
    | Líneas de firma del recibo impreso (contrato §7.4).
    */
    'signature_lines' => [
        ['label' => 'Recibí de conformidad', 'name' => 'employee'],
        ['label' => 'Autorizó', 'name' => null],
    ],

];
