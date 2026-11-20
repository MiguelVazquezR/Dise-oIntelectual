<?php

namespace App\Services\Aws;

use Aws\Rekognition\RekognitionClient;
use Aws\Sts\StsClient;

/**
 * Construye los clientes del SDK de AWS con la configuración del proyecto.
 *
 * Las credenciales se leen **solo** de `config('rekognition.*')`; nunca del
 * entorno en tiempo de ejecución ni de la base de datos. La clase es el único
 * punto de contacto con el SDK, de forma que las pruebas puedan sustituirla sin
 * realizar llamadas reales a AWS.
 */
class AwsClientFactory
{
    /**
     * Cliente de Rekognition para operaciones faciales.
     *
     * Los reintentos del SDK se comparten con el `idempotency_key` del intento
     * lógico, por lo que el consumo interno nunca se duplica.
     */
    public function rekognition(): RekognitionClient
    {
        return new RekognitionClient(array_replace($this->baseConfig(), [
            'retries' => max(0, (int) config('rekognition.retries', 2)),
        ]));
    }

    /**
     * Cliente STS para el diagnóstico no facturable (`GetCallerIdentity`).
     *
     * Usa un tiempo de espera corto y sin reintentos: es una comprobación de
     * credenciales, no una operación de negocio.
     */
    public function sts(): StsClient
    {
        $timeout = min(5, max(1, (int) config('rekognition.timeout', 10)));

        return new StsClient(array_replace($this->baseConfig(), [
            'retries' => 0,
            'http' => [
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
            ],
        ]));
    }

    public function hasCredentials(): bool
    {
        return $this->hasAccessKey() && $this->hasSecretKey();
    }

    public function hasAccessKey(): bool
    {
        return $this->filled(config('rekognition.access_key'));
    }

    public function hasSecretKey(): bool
    {
        return $this->filled(config('rekognition.secret_key'));
    }

    public function hasCollection(): bool
    {
        return $this->filled(config('rekognition.collection_id'));
    }

    public function isSdkInstalled(): bool
    {
        return class_exists(RekognitionClient::class) && class_exists(StsClient::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseConfig(): array
    {
        $accessKey = config('rekognition.access_key');
        $secretKey = config('rekognition.secret_key');

        $config = [
            'region' => (string) config('rekognition.region', 'us-east-1'),
            'version' => 'latest',
            'http' => [
                'timeout' => max(1, (int) config('rekognition.timeout', 10)),
                'connect_timeout' => max(1, min(5, (int) config('rekognition.timeout', 10))),
            ],
        ];

        if ($this->filled($accessKey) && $this->filled($secretKey)) {
            $config['credentials'] = [
                'key' => (string) $accessKey,
                'secret' => (string) $secretKey,
            ];
        }

        return $config;
    }

    protected function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
