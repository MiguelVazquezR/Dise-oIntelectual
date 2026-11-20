import type { RawSelectOption } from './options';

/** Usuarios y roles (contrato ARCHITECTURE §7.1). */
export type NamedRef =
    | string
    | {
          id?: number;
          name?: string;
          slug?: string;
      };

export type UserRow = {
    id: number;
    name: string;
    email: string;
    roles: NamedRef[];
    branches: NamedRef[];
    created_at: string;
};

export type UserRecord = {
    id: number;
    name: string;
    email: string;
    role_ids: number[];
    branch_ids: number[];
};

/** Estado local del formulario de usuario (contraseña solo por referencia). */
export type UserFormData = {
    name: string;
    email: string;
    password: string;
    role_ids: number[];
    branch_ids: number[];
};

export type UserFilters = {
    search: string | null;
    role_slug: string | null;
};

export type UserOptions = {
    roles: RawSelectOption[];
    branches: RawSelectOption[];
};

export type UserPermissions = {
    manage: boolean;
};

export type RoleRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permissions: string[];
};

export type PermissionItem = {
    slug: string;
    name: string;
};

export type PermissionGroup = {
    group: string;
    permissions: PermissionItem[];
};

export type RolePermissions = {
    manage: boolean;
};
