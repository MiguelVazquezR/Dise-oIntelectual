import type { RawSelectOption } from './options';
import type { Paginated } from './pagination';

/**
 * Contrato de consumo y configuración de AWS Rekognition
 * (docs/AI/ARCHITECTURE.md §7.4). La integración está desactivada por
 * defecto; el backend es la fuente de verdad del estado y de los importes.
 */

export type AwsUsageSource = 'demo' | 'real';

export type AwsUsageStatus = 'succeeded' | 'failed' | string;

export type AwsFaceOperation =
    | 'index_faces'
    | 'search_faces_by_image'
    | 'detect_faces'
    | 'delete_faces'
    | string;

export type AwsBranchRef = {
    id?: number | null;
    name?: string | null;
};

export type AwsEmployeeRef = {
    id?: number | null;
    full_name?: string | null;
    name?: string | null;
    employee_number?: string | null;
};

export type AwsUsageFilters = {
    from: string | null;
    to: string | null;
    branch_id: string | number | null;
    operation: string | null;
    source: string | null;
    per_page?: string | number | null;
};

export type AwsOperationTotal = {
    operation: AwsFaceOperation;
    faces: number | string | null;
    amount_mxn: string | number | null;
};

export type AwsBranchTotal = {
    branch: AwsBranchRef | null;
    faces: number | string | null;
    amount_mxn: string | number | null;
};

export type AwsUsageTotals = {
    faces: number | string | null;
    amount_mxn: string | number | null;
    by_operation: AwsOperationTotal[];
    by_branch: AwsBranchTotal[];
};

export type AwsUsageRecord = {
    id: number;
    occurred_at: string;
    branch: AwsBranchRef | null;
    employee: AwsEmployeeRef | null;
    operation: AwsFaceOperation;
    quantity: number | string | null;
    status: AwsUsageStatus;
    source: AwsUsageSource | string;
    aws_request_id: string | null;
    amount_mxn: string | number | null;
};

/**
 * Tarifa interna acordada. `note` es el texto del backend y no debe
 * contradecirse en la UI: no es la tarifa oficial de AWS.
 */
export type AwsPricing = {
    price_per_1000: string | number | null;
    currency: string | null;
    note: string | null;
};

export type AwsUsagePermissions = {
    export?: boolean;
};

/** Catálogos de filtros; §7.4 no los incluye todavía (ver reporte). */
export type AwsUsageOptions = {
    branches: RawSelectOption[];
    operations: RawSelectOption[];
    sources: RawSelectOption[];
};

export type AwsUsageProps = {
    filters?: AwsUsageFilters;
    totals?: AwsUsageTotals;
    records?: Paginated<AwsUsageRecord>;
    pricing?: AwsPricing;
    can?: AwsUsagePermissions;
    options?: Partial<AwsUsageOptions>;
};

/** Estado de la integración sin secretos (`/settings/aws`). */
export type AwsConfiguration = {
    enabled: boolean;
    region: string | null;
    collection_id_masked: string | null;
    has_access_key: boolean;
    has_secret: boolean;
    configured: boolean;
    driver: string | null;
};

export type AwsCheck = {
    label: string;
    ok: boolean;
    detail: string | null;
};

export type AwsTestResult = {
    ok: boolean;
    message?: string | null;
    checked_at?: string | null;
    checks?: AwsCheck[];
};

export type AwsSettingsPermissions = {
    view?: boolean;
    manage?: boolean;
};

export type AwsSettingsProps = {
    configuration?: AwsConfiguration;
    checks?: AwsCheck[];
    notice?: string | null;
    can?: AwsSettingsPermissions;
    /** Resultado de `POST /settings/aws/test` cuando el backend lo comparte. */
    test_result?: AwsTestResult | null;
};
