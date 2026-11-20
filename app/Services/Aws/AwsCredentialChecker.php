<?php

namespace App\Services\Aws;

use Aws\Exception\AwsException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Diagnóstico de credenciales de AWS sin operaciones facturables.
 *
 * Usa `STS GetCallerIdentity`, que no tiene costo, con un tiempo de espera
 * corto. Los mensajes son para el administrador y **nunca** incluyen claves,
 * secretos ni identificadores completos de la cuenta.
 */
final class AwsCredentialChecker
{
    public function __construct(private readonly AwsClientFactory $clients) {}

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     checked_at: string,
     *     checks: array<int, array{label: string, ok: bool, detail: string}>
     * }
     */
    public function run(): array
    {
        $checks = [];

        $this->push($checks, 'Credenciales configuradas', $this->clients->hasCredentials(), $this->clients->hasCredentials()
            ? 'AWS_ACCESS_KEY_ID y AWS_SECRET_ACCESS_KEY están definidas en el entorno.'
            : 'Falta AWS_ACCESS_KEY_ID y/o AWS_SECRET_ACCESS_KEY en el entorno.');

        $this->push($checks, 'SDK de AWS', $this->clients->isSdkInstalled(), $this->clients->isSdkInstalled()
            ? 'aws/aws-sdk-php está disponible.'
            : 'El SDK de AWS no está instalado; no se puede diagnosticar la integración.');

        if (! $this->clients->isSdkInstalled()) {
            return $this->result($checks, false, 'El SDK de AWS no está disponible.');
        }

        if (! $this->clients->hasCredentials()) {
            return $this->result($checks, false, 'No hay credenciales de AWS configuradas; no se realizó ninguna llamada.');
        }

        try {
            $this->clients->sts()->getCallerIdentity([]);
            $this->push($checks, 'Identidad verificada (STS GetCallerIdentity)', true, 'Las credenciales respondieron correctamente; la llamada no tiene costo.');
        } catch (Throwable $exception) {
            $this->push($checks, 'Identidad verificada (STS GetCallerIdentity)', false, $this->describe($exception));

            return $this->result($checks, false, 'No fue posible verificar las credenciales de AWS.');
        }

        return $this->result($checks, true, 'Credenciales de AWS válidas (GetCallerIdentity no facturable).');
    }

    /**
     * @param  array<int, array{label: string, ok: bool, detail: string}>  $checks
     */
    private function push(array &$checks, string $label, bool $ok, string $detail): void
    {
        $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    }

    /**
     * @param  array<int, array{label: string, ok: bool, detail: string}>  $checks
     * @return array{
     *     ok: bool,
     *     message: string,
     *     checked_at: string,
     *     checks: array<int, array{label: string, ok: bool, detail: string}>
     * }
     */
    private function result(array $checks, bool $ok, string $message): array
    {
        return [
            'ok' => $ok,
            'message' => $message,
            'checked_at' => CarbonImmutable::now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    private function describe(Throwable $exception): string
    {
        if (! $exception instanceof AwsException) {
            return 'No fue posible conectar con AWS; revisa el entorno y la red.';
        }

        $code = (string) ($exception->getAwsErrorCode() ?? '');

        return match (true) {
            in_array($code, ['InvalidClientTokenId', 'SignatureDoesNotMatch', 'UnrecognizedClientException', 'InvalidSignatureException', 'InvalidAccessKeyId'], true) => 'Las credenciales de AWS no son válidas.',
            in_array($code, ['AccessDeniedException', 'AccessDenied'], true) => 'La cuenta de AWS no tiene permiso para verificar su identidad.',
            in_array($code, ['ThrottlingException', 'TooManyRequestsException'], true) => 'AWS limitó las solicitudes; vuelve a intentarlo en unos segundos.',
            $code === 'RequestTimeout', $code === 'RequestTimeoutException', $code === 'NetworkingError' => 'La conexión con AWS agotó el tiempo de espera.',
            default => $code !== ''
                ? 'AWS respondió con un error (código '.$code.').'
                : 'No fue posible conectar con AWS; revisa el entorno y la red.',
        };
    }
}
