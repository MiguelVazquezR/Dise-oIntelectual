<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Aws\AwsClientFactory;
use App\Services\Aws\AwsCredentialChecker;
use App\Services\Face\FaceRecognitionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración y diagnóstico de AWS Rekognition (`/settings/aws`, §7.4).
 *
 * - Solo se muestran metadatos: la colección va enmascarada y las claves nunca
 *   salen del servidor.
 * - `POST /settings/aws/test` comprueba credenciales con `STS
 *   GetCallerIdentity` (sin costo), deja `test_result` en sesión y responde con
 *   `flash.success`/`flash.error`.
 * - La configuración se administra por variables de entorno; la aplicación no
 *   escribe `.env` ni permite alternar `AWS_REKOGNITION_ENABLED` en caliente.
 */
class AwsSettingsController extends Controller
{
    private const NOTICE = 'La integración se configura por variables de entorno del servidor (AWS_REKOGNITION_ENABLED, AWS_REKOGNITION_DRIVER, AWS_DEFAULT_REGION, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_REKOGNITION_COLLECTION_ID y AWS_REKOGNITION_PRICE_PER_1000). La aplicación no escribe el archivo .env ni muestra claves. AWS Rekognition está desactivado por defecto (D-13).';

    public function __construct(
        private readonly FaceRecognitionManager $manager,
        private readonly AwsClientFactory $clients,
        private readonly AwsCredentialChecker $checker,
    ) {}

    public function index(Request $request): Response
    {
        $configuration = $this->configuration();

        /** @var array<string, mixed>|null $testResult */
        $testResult = $request->session()->pull('test_result');
        $testResult = is_array($testResult) ? $testResult : null;

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Aws', [
            'configuration' => $configuration,
            'checks' => $this->checks($configuration),
            'notice' => self::NOTICE,
            'can' => [
                'manage' => $user->hasPermission('aws.settings.manage'),
            ],
            'test_result' => $testResult,
        ]);
    }

    public function test(Request $request): RedirectResponse
    {
        $result = $this->checker->run();

        $request->session()->put('test_result', $result);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     region: string,
     *     collection_id_masked: string|null,
     *     has_access_key: bool,
     *     has_secret: bool,
     *     configured: bool,
     *     driver: string
     * }
     */
    private function configuration(): array
    {
        return [
            'enabled' => (bool) config('rekognition.enabled'),
            'region' => (string) config('rekognition.region', 'us-east-1'),
            'collection_id_masked' => $this->maskCollectionId(config('rekognition.collection_id')),
            'has_access_key' => $this->clients->hasAccessKey(),
            'has_secret' => $this->clients->hasSecretKey(),
            'configured' => $this->manager->driver() === FaceRecognitionManager::DRIVER_AWS,
            'driver' => $this->manager->driver(),
        ];
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<int, array{label: string, ok: bool, detail: string}>
     */
    private function checks(array $configuration): array
    {
        $enabled = (bool) $configuration['enabled'];
        $driver = (string) $configuration['driver'];

        return [
            [
                'label' => 'Integración habilitada',
                'ok' => $enabled,
                'detail' => $enabled
                    ? 'AWS_REKOGNITION_ENABLED está activo.'
                    : 'AWS_REKOGNITION_ENABLED está desactivado; el checador sigue con PIN.',
            ],
            [
                'label' => 'Driver efectivo',
                'ok' => $driver !== FaceRecognitionManager::DRIVER_NOT_CONFIGURED,
                'detail' => $driver === FaceRecognitionManager::DRIVER_AWS
                    ? 'Adaptador real de AWS Rekognition.'
                    : ($driver === FaceRecognitionManager::DRIVER_FAKE
                        ? 'Modo simulación explícito: el consumo se registra como source=demo.'
                        : 'Sin credenciales, colección o SDK: no se realizan llamadas a AWS.'),
            ],
            [
                'label' => 'Credenciales definidas',
                'ok' => (bool) $configuration['has_access_key'] && (bool) $configuration['has_secret'],
                'detail' => (bool) $configuration['has_access_key'] && (bool) $configuration['has_secret']
                    ? 'AWS_ACCESS_KEY_ID y AWS_SECRET_ACCESS_KEY están definidas.'
                    : 'Falta AWS_ACCESS_KEY_ID y/o AWS_SECRET_ACCESS_KEY.',
            ],
            [
                'label' => 'Colección de rostros',
                'ok' => $configuration['collection_id_masked'] !== null,
                'detail' => $configuration['collection_id_masked'] !== null
                    ? 'Colección configurada: '.$configuration['collection_id_masked'].'.'
                    : 'AWS_REKOGNITION_COLLECTION_ID no está definida.',
            ],
            [
                'label' => 'Región y SDK',
                'ok' => $this->clients->isSdkInstalled(),
                'detail' => $this->clients->isSdkInstalled()
                    ? 'Región '.$configuration['region'].'; aws/aws-sdk-php disponible.'
                    : 'El SDK de AWS no está disponible.',
            ],
        ];
    }

    private function maskCollectionId(mixed $collectionId): ?string
    {
        if (! is_string($collectionId) || trim($collectionId) === '') {
            return null;
        }

        return '****'.mb_substr(trim($collectionId), -4);
    }
}
