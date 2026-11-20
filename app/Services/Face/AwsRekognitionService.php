<?php

namespace App\Services\Face;

use App\Contracts\FaceRecognitionService;
use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageSource;
use App\Enums\AwsUsageStatus;
use App\Models\BiometricEnrollment;
use App\Models\Employee;
use App\Services\Aws\AwsClientFactory;
use Aws\Exception\AwsException;
use Aws\Exception\CredentialsException;
use Aws\ResultInterface;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adaptador real de AWS Rekognition (`IndexFaces`, `SearchFacesByImage`,
 * `DeleteFaces`) sobre `aws/aws-sdk-php`.
 *
 * - Credenciales, región, colección y tiempos se leen solo de
 *   `config('rekognition.*')`.
 * - Los fallos del SDK (`AwsException`, credenciales, red) se traducen a
 *   resultados tipados: el checador nunca recibe una excepción.
 * - Cada intento lógico se anota una sola vez en `aws_usage_records` con
 *   `source=real` y el `aws_request_id` cuando el SDK lo devuelve; los fallos
 *   se registran con importe cero.
 * - Las imágenes se envían en memoria y se descartan: no se persisten ni se
 *   escriben en logs.
 */
final class AwsRekognitionService implements FaceRecognitionService
{
    public function __construct(
        private readonly AwsClientFactory $clients,
        private readonly FaceUsageRecorder $usage,
    ) {}

    public function isConfigured(): bool
    {
        return $this->clients->hasCredentials()
            && $this->clients->hasCollection()
            && $this->clients->isSdkInstalled();
    }

    public function driver(): string
    {
        return FaceRecognitionManager::DRIVER_AWS;
    }

    public function isSimulated(): bool
    {
        return false;
    }

    public function indexFace(
        int $employeeId,
        string $imageContent,
        ?int $branchId = null,
        ?string $idempotencyKey = null,
    ): FaceIndexResult {
        if (! $this->isConfigured()) {
            return FaceIndexResult::failure('not_configured', 'El reconocimiento facial aún no está configurado.');
        }

        $key = $this->idempotencyKey($idempotencyKey);

        try {
            $response = $this->clients->rekognition()->indexFaces([
                'CollectionId' => $this->collectionId(),
                'Image' => ['Bytes' => $imageContent],
                'ExternalImageId' => (string) $employeeId,
                'MaxFaces' => 1,
                'QualityFilter' => 'AUTO',
            ]);
        } catch (Throwable $exception) {
            return $this->indexFailure($key, $employeeId, $branchId, $exception);
        }

        $faceRecords = $response['FaceRecords'] ?? [];
        $faceId = is_array($faceRecords) && isset($faceRecords[0]['Face']['FaceId'])
            ? $faceRecords[0]['Face']['FaceId']
            : null;
        $requestId = $this->requestId($response);

        if (! is_string($faceId) || $faceId === '') {
            $this->record(AwsFaceOperation::IndexFaces, AwsUsageStatus::Failed, $key, $employeeId, $branchId, $requestId, [
                'error_code' => 'no_face',
            ]);

            return FaceIndexResult::failure('no_face', 'No se detectó un rostro en la imagen enviada.');
        }

        $this->record(AwsFaceOperation::IndexFaces, AwsUsageStatus::Succeeded, $key, $employeeId, $branchId, $requestId);

        return FaceIndexResult::success($faceId, $requestId);
    }

    public function searchFaceByIdentity(
        int $branchId,
        string $imageContent,
        ?string $idempotencyKey = null,
    ): FaceSearchResult {
        if (! $this->isConfigured()) {
            return FaceSearchResult::failure('not_configured', 'El reconocimiento facial aún no está configurado.');
        }

        $key = $this->idempotencyKey($idempotencyKey);

        try {
            $response = $this->clients->rekognition()->searchFacesByImage([
                'CollectionId' => $this->collectionId(),
                'Image' => ['Bytes' => $imageContent],
                'MaxFaces' => 1,
            ]);
        } catch (Throwable $exception) {
            return $this->searchFailure($key, $branchId, $exception);
        }

        $requestId = $this->requestId($response);

        // AWS cobra la imagen analizada aunque no haya coincidencias.
        $this->record(AwsFaceOperation::SearchFacesByImage, AwsUsageStatus::Succeeded, $key, null, $branchId, $requestId);

        $faceMatches = $response['FaceMatches'] ?? [];

        if (! is_array($faceMatches)) {
            return FaceSearchResult::success(requestId: $requestId);
        }

        foreach ($faceMatches as $match) {
            $face = is_array($match) && is_array($match['Face'] ?? null) ? $match['Face'] : [];
            $faceId = is_string($face['FaceId'] ?? null) ? $face['FaceId'] : null;
            $employeeId = $this->employeeIdForMatch($face, $faceId);

            if ($employeeId === null) {
                continue;
            }

            if (! Employee::query()->whereKey($employeeId)->where('branch_id', $branchId)->exists()) {
                continue;
            }

            $similarity = is_array($match) && is_numeric($match['Similarity'] ?? null)
                ? (float) $match['Similarity']
                : null;

            return FaceSearchResult::success($employeeId, $faceId, $similarity, $requestId);
        }

        return FaceSearchResult::success(requestId: $requestId);
    }

    public function deleteFace(
        string $externalFaceId,
        ?int $employeeId = null,
        ?int $branchId = null,
        ?string $idempotencyKey = null,
    ): FaceDeleteResult {
        if (! $this->isConfigured()) {
            return FaceDeleteResult::failure('not_configured', 'El reconocimiento facial aún no está configurado.');
        }

        if (trim($externalFaceId) === '') {
            return FaceDeleteResult::failure('invalid_request', 'No hay un rostro registrado que eliminar.');
        }

        $key = $this->idempotencyKey($idempotencyKey);

        try {
            $response = $this->clients->rekognition()->deleteFaces([
                'CollectionId' => $this->collectionId(),
                'Faces' => [$externalFaceId],
            ]);
        } catch (Throwable $exception) {
            return $this->deleteFailure($key, $employeeId, $branchId, $exception);
        }

        $requestId = $this->requestId($response);
        $this->record(AwsFaceOperation::DeleteFaces, AwsUsageStatus::Succeeded, $key, $employeeId, $branchId, $requestId);

        return FaceDeleteResult::success($requestId);
    }

    /**
     * Resuelve el colaborador de una coincidencia por `ExternalImageId` y, si
     * no está disponible, por el registro de enrolamiento del rostro.
     *
     * @param  array<string, mixed>  $face
     */
    private function employeeIdForMatch(array $face, ?string $faceId): ?int
    {
        $externalImageId = $face['ExternalImageId'] ?? null;

        if (is_numeric($externalImageId)) {
            return (int) $externalImageId;
        }

        if ($faceId === null) {
            return null;
        }

        $employeeId = BiometricEnrollment::query()
            ->where('external_face_id', $faceId)
            ->value('employee_id');

        return $employeeId !== null ? (int) $employeeId : null;
    }

    private function indexFailure(string $key, int $employeeId, ?int $branchId, Throwable $exception): FaceIndexResult
    {
        [$code, $message] = $this->describe($exception);
        $this->record(AwsFaceOperation::IndexFaces, AwsUsageStatus::Failed, $key, $employeeId, $branchId, $this->exceptionRequestId($exception), [
            'error_code' => $code,
        ]);

        return FaceIndexResult::failure($code, $message);
    }

    private function searchFailure(string $key, int $branchId, Throwable $exception): FaceSearchResult
    {
        [$code, $message] = $this->describe($exception);
        $this->record(AwsFaceOperation::SearchFacesByImage, AwsUsageStatus::Failed, $key, null, $branchId, $this->exceptionRequestId($exception), [
            'error_code' => $code,
        ]);

        return FaceSearchResult::failure($code, $message);
    }

    private function deleteFailure(string $key, ?int $employeeId, ?int $branchId, Throwable $exception): FaceDeleteResult
    {
        [$code, $message] = $this->describe($exception);
        $this->record(AwsFaceOperation::DeleteFaces, AwsUsageStatus::Failed, $key, $employeeId, $branchId, $this->exceptionRequestId($exception), [
            'error_code' => $code,
        ]);

        return FaceDeleteResult::failure($code, $message);
    }

    /**
     * Identificador de la petición fallida cuando AWS alcanzó a responder.
     */
    private function exceptionRequestId(Throwable $exception): ?string
    {
        if (! $exception instanceof AwsException) {
            return null;
        }

        $requestId = $exception->getAwsRequestId();

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        AwsFaceOperation $operation,
        AwsUsageStatus $status,
        string $idempotencyKey,
        ?int $employeeId,
        ?int $branchId,
        ?string $requestId,
        array $metadata = [],
    ): void {
        try {
            $this->usage->record(
                operation: $operation,
                status: $status,
                source: AwsUsageSource::Real,
                idempotencyKey: $idempotencyKey,
                quantity: 1,
                branchId: $branchId,
                employeeId: $employeeId,
                awsRequestId: $requestId,
                metadata: $metadata,
            );
        } catch (Throwable $exception) {
            // El consumo interno no debe romper el checador ni la operación.
            report($exception);
        }
    }

    /**
     * Traduce el fallo del SDK a un código y un mensaje en español.
     *
     * @return array{0: string, 1: string}
     */
    private function describe(Throwable $exception): array
    {
        if ($exception instanceof CredentialsException) {
            return ['invalid_credentials', 'No fue posible resolver las credenciales de AWS.'];
        }

        if ($exception instanceof ConnectException) {
            return ['timeout', 'La conexión con AWS Rekognition agotó el tiempo de espera.'];
        }

        if (! $exception instanceof AwsException) {
            return ['unknown', 'No fue posible completar la operación con AWS Rekognition.'];
        }

        $code = (string) ($exception->getAwsErrorCode() ?? '');
        $previous = $exception->getPrevious();

        return match (true) {
            in_array($code, ['InvalidClientTokenId', 'SignatureDoesNotMatch', 'UnrecognizedClientException', 'InvalidSignatureException', 'InvalidAccessKeyId'], true),
            $previous instanceof CredentialsException => ['invalid_credentials', 'Las credenciales de AWS no son válidas.'],
            in_array($code, ['AccessDeniedException', 'AccessDenied'], true) => ['access_denied', 'La cuenta de AWS no tiene permiso para usar Rekognition.'],
            in_array($code, ['ThrottlingException', 'Throttling', 'ProvisionedThroughputExceededException', 'TooManyRequestsException'], true) => ['throttled', 'AWS limitó las solicitudes; vuelve a intentarlo en unos segundos.'],
            in_array($code, ['ResourceNotFoundException', 'ResourceNotFound'], true) => ['collection_not_found', 'La colección de rostros configurada no existe en AWS Rekognition.'],
            in_array($code, ['InvalidParameterException', 'InvalidImageFormatException', 'ImageTooLargeException'], true) => ['invalid_image', 'La imagen o la colección no cumplen los requisitos de AWS Rekognition.'],
            in_array($code, ['RequestTimeout', 'RequestTimeoutException', 'NetworkingError'], true),
            $previous instanceof ConnectException => ['timeout', 'La conexión con AWS Rekognition agotó el tiempo de espera.'],
            $code === 'InternalServerError' => ['aws_error', 'AWS Rekognition devolvió un error interno; intenta más tarde.'],
            default => ['aws_error', 'AWS Rekognition respondió con un error.'],
        };
    }

    private function requestId(ResultInterface $response): ?string
    {
        $metadata = $response['@metadata'] ?? null;
        $requestId = is_array($metadata) ? ($metadata['requestId'] ?? null) : null;

        if (! is_string($requestId) || $requestId === '') {
            $responseMetadata = $response['ResponseMetadata'] ?? null;
            $requestId = is_array($responseMetadata) ? ($responseMetadata['RequestId'] ?? null) : null;
        }

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    private function collectionId(): string
    {
        return trim((string) config('rekognition.collection_id'));
    }

    private function idempotencyKey(?string $idempotencyKey): string
    {
        $key = $idempotencyKey !== null ? trim($idempotencyKey) : '';

        return $key !== '' ? $key : (string) Str::uuid();
    }
}
