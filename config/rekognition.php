<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AWS Rekognition (reconocimiento facial del checador)
    |--------------------------------------------------------------------------
    |
    | Configuración del proveedor biométrico. Desactivado por defecto (D-13):
    | si `enabled` es false, la aplicación debe usar el driver simulado y
    | etiquetar el consumo como `source=demo`.
    |
    */

    'enabled' => (bool) env('AWS_REKOGNITION_ENABLED', false),

    /*
    | Driver efectivo del checador facial (D-13): `aws` usa el adaptador real
    | de Rekognition y `fake` es una simulación explícita cuyos registros se
    | guardan con `source=demo`. Cualquier otro valor se trata como `aws`.
    */
    'driver' => strtolower(trim((string) env('AWS_REKOGNITION_DRIVER', 'aws'))),

    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    'collection_id' => env('AWS_REKOGNITION_COLLECTION_ID'),

    'access_key' => env('AWS_ACCESS_KEY_ID'),

    'secret_key' => env('AWS_SECRET_ACCESS_KEY'),

    /*
    | Precio en MXN por cada 1,000 llamadas. Se mantiene como cadena decimal
    | para no introducir `float` en el cálculo de costos (S-05).
    */
    'price_per_1000' => (string) env('AWS_REKOGNITION_PRICE_PER_1000', '24'),

    'timeout' => (int) env('AWS_REKOGNITION_TIMEOUT', 10),

    'retries' => (int) env('AWS_REKOGNITION_RETRIES', 2),

];
