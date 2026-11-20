/** Fila del listado de sucursales (contrato ARCHITECTURE §7.1). */
export type Branch = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    timezone: string;
    is_active: boolean;
    employees_count: number;
    schedules_count: number;
};

/** Registro editable de sucursal (`/branches/create` y `/branches/{id}/edit`). */
export type BranchRecord = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    latitude: string | number | null;
    longitude: string | number | null;
    geofence_radius_m: number | null;
    timezone: string;
    is_active: boolean;
};

export type BranchFilters = {
    search: string | null;
    status: string | null;
};

export type BranchPermissions = {
    manage: boolean;
};
