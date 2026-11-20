<?php

namespace App\Contracts;

use App\Services\Face\FaceDeleteResult;
use App\Services\Face\FaceIndexResult;
use App\Services\Face\FaceSearchResult;

/**
 * Contrato del proveedor biométrico del checador.
 *
 * La implementación activa la elige `App\Services\Face\FaceRecognitionManager`
 * y se resuelve dinámicamente desde el contenedor: `NotConfiguredFaceRecognition`
 * cuando faltan credenciales/colección, `FakeFaceRecognition` en el modo de
 * simulación explícito y `AwsRekognitionService` con credenciales válidas.
 *
 * Reglas comunes a todas las implementaciones:
 * - Ninguna devuelve excepciones al checador: los fallos se tipan con
 *   `FaceIndexResult`/`FaceSearchResult`/`FaceDeleteResult`.
 * - Ninguna registra imágenes ni secretos en logs, auditoría o base de datos.
 * - Cada operación facturable se anota en `aws_usage_records` con
 *   `idempotency_key` único: reintentar con la misma clave no duplica consumo.
 */
interface FaceRecognitionService
{
    /**
     * Indica si el proveedor tiene credenciales, colección y SDK listos.
     */
    public function isConfigured(): bool;

    /**
     * Driver efectivo: `aws`, `fake` o `not_configured`.
     */
    public function driver(): string;

    /**
     * Indica si las operaciones son simuladas y deben etiquetarse como demo.
     */
    public function isSimulated(): bool;

    /**
     * Indexa el rostro de un colaborador y devuelve el identificador externo.
     *
     * @param  string  $imageContent  Contenido binario de la imagen (no se persiste).
     * @param  int|null  $branchId  Sucursal del colaborador, solo para el consumo interno.
     * @param  string|null  $idempotencyKey  Clave del intento lógico (UUID si se omite).
     */
    public function indexFace(
        int $employeeId,
        string $imageContent,
        ?int $branchId = null,
        ?string $idempotencyKey = null,
    ): FaceIndexResult;

    /**
     * Busca a qué colaborador corresponde un rostro dentro de una sucursal.
     *
     * Una búsqueda sin coincidencias es una operación exitosa con
     * `employeeId = null` (AWS cobra la imagen analizada igualmente).
     */
    public function searchFaceByIdentity(
        int $branchId,
        string $imageContent,
        ?string $idempotencyKey = null,
    ): FaceSearchResult;

    /**
     * Elimina el rostro indexado de un colaborador.
     */
    public function deleteFace(
        string $externalFaceId,
        ?int $employeeId = null,
        ?int $branchId = null,
        ?string $idempotencyKey = null,
    ): FaceDeleteResult;
}
