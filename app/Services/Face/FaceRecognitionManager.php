<?php

namespace App\Services\Face;

use App\Contracts\FaceRecognitionService;
use App\Services\Aws\AwsClientFactory;
use Illuminate\Contracts\Foundation\Application;

/**
 * Fábrica del proveedor biométrico activo (patrón manager).
 *
 * Decide en cada petición, sin llamar a AWS:
 * - `driver=fake` (explícito) → `FakeFaceRecognition`, todo etiquetado `demo`.
 * - integración desactivada o sin credenciales/colección/SDK →
 *   `NotConfiguredFaceRecognition` (el PIN del checador sigue operativo).
 * - `enabled=true` con credenciales completas → `AwsRekognitionService`.
 *
 * El resultado se memoriza durante la petición (`scoped` en el contenedor).
 */
final class FaceRecognitionManager
{
    public const DRIVER_AWS = 'aws';

    public const DRIVER_FAKE = 'fake';

    public const DRIVER_NOT_CONFIGURED = 'not_configured';

    private ?FaceRecognitionService $resolved = null;

    public function __construct(private readonly Application $app) {}

    /**
     * Driver efectivo, sin efectos secundarios.
     */
    public function driver(): string
    {
        $configured = strtolower(trim((string) config('rekognition.driver', self::DRIVER_AWS)));

        if ($configured === self::DRIVER_FAKE) {
            return self::DRIVER_FAKE;
        }

        if (! (bool) config('rekognition.enabled')) {
            return self::DRIVER_NOT_CONFIGURED;
        }

        /** @var AwsClientFactory $clients */
        $clients = $this->app->make(AwsClientFactory::class);

        if (! $clients->hasCredentials() || ! $clients->hasCollection() || ! $clients->isSdkInstalled()) {
            return self::DRIVER_NOT_CONFIGURED;
        }

        return self::DRIVER_AWS;
    }

    /**
     * Implementación activa del contrato biométrico.
     */
    public function service(): FaceRecognitionService
    {
        return $this->resolved ??= match ($this->driver()) {
            self::DRIVER_FAKE => $this->app->make(FakeFaceRecognition::class),
            self::DRIVER_AWS => new AwsRekognitionService(
                $this->app->make(AwsClientFactory::class),
                $this->app->make(FaceUsageRecorder::class),
            ),
            default => $this->app->make(NotConfiguredFaceRecognition::class),
        };
    }
}
