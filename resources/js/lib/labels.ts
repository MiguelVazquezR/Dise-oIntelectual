import type { ActorRef, StatusVariant } from '@/types';

/**
 * Etiquetas de presentación para valores de enum del backend.
 * Solo traducción; el estado y sus reglas viven en el backend.
 */
const EMPLOYEE_STATUSES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    active: { label: 'Activo', variant: 'success' },
    inactive: { label: 'Inactivo', variant: 'warning' },
    terminated: { label: 'Baja', variant: 'danger' },
};

const PAY_FREQUENCIES: Record<string, string> = {
    weekly: 'Semanal',
    biweekly: 'Catorcenal',
    monthly: 'Mensual',
};

const ATTENDANCE_EVENT_TYPES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    clock_in: { label: 'Entrada', variant: 'success' },
    break_start: { label: 'Inicio de descanso', variant: 'info' },
    break_end: { label: 'Fin de descanso', variant: 'info' },
    clock_out: { label: 'Salida', variant: 'neutral' },
};

const ATTENDANCE_METHODS: Record<string, string> = {
    pin: 'PIN',
    face: 'Reconocimiento facial',
};

export function employeeStatusLabel(status: string): string {
    return EMPLOYEE_STATUSES[status]?.label ?? status;
}

export function employeeStatusVariant(status: string): StatusVariant {
    return EMPLOYEE_STATUSES[status]?.variant ?? 'neutral';
}

export function payFrequencyLabel(value: string): string {
    return PAY_FREQUENCIES[value] ?? value;
}

export function attendanceEventLabel(type: string): string {
    return ATTENDANCE_EVENT_TYPES[type]?.label ?? type;
}

export function attendanceEventVariant(type: string): StatusVariant {
    return ATTENDANCE_EVENT_TYPES[type]?.variant ?? 'neutral';
}

export function attendanceMethodLabel(method: string): string {
    return ATTENDANCE_METHODS[method] ?? method;
}

const ATTENDANCE_STATUSES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    complete: { label: 'Completa', variant: 'success' },
    incomplete: { label: 'Incompleta', variant: 'warning' },
    absence: { label: 'Falta', variant: 'danger' },
    rest_day: { label: 'Descanso', variant: 'neutral' },
    holiday: { label: 'Festivo', variant: 'info' },
    vacation: { label: 'Vacaciones', variant: 'info' },
    incident: { label: 'Incidencia', variant: 'warning' },
};

export function attendanceStatusLabel(status: string): string {
    return ATTENDANCE_STATUSES[status]?.label ?? status;
}

export function attendanceStatusVariant(status: string): StatusVariant {
    return ATTENDANCE_STATUSES[status]?.variant ?? 'neutral';
}

/**
 * Normaliza los valores de enum del backend (`justified_absence`,
 * `JustifiedAbsence`) para tolerar ambas convenciones de serialización.
 */
function enumKey(value: string): string {
    return value.replace(/[^a-z0-9]/gi, '').toLowerCase();
}

const INCIDENT_TYPES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    vacation: { label: 'Vacaciones', variant: 'info' },
    justifiedabsence: { label: 'Falta justificada', variant: 'warning' },
    unjustifiedabsence: { label: 'Falta injustificada', variant: 'danger' },
    sickleave: { label: 'Incapacidad', variant: 'warning' },
    paidleave: { label: 'Permiso con goce', variant: 'info' },
    unpaidleave: { label: 'Permiso sin goce', variant: 'neutral' },
    workedrestday: { label: 'Descanso trabajado', variant: 'info' },
    workedholiday: { label: 'Festivo trabajado', variant: 'info' },
    other: { label: 'Otra', variant: 'neutral' },
};

export function incidentTypeLabel(type: string): string {
    return INCIDENT_TYPES[enumKey(type)]?.label ?? type;
}

export function incidentTypeVariant(type: string): StatusVariant {
    return INCIDENT_TYPES[enumKey(type)]?.variant ?? 'neutral';
}

const INCIDENT_STATUSES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    pending: { label: 'Pendiente', variant: 'warning' },
    approved: { label: 'Aprobada', variant: 'success' },
    rejected: { label: 'Rechazada', variant: 'danger' },
    cancelled: { label: 'Cancelada', variant: 'neutral' },
};

export function incidentStatusLabel(status: string): string {
    return INCIDENT_STATUSES[status]?.label ?? status;
}

export function incidentStatusVariant(status: string): StatusVariant {
    return INCIDENT_STATUSES[status]?.variant ?? 'neutral';
}

const VACATION_REQUEST_STATUSES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    pending: { label: 'Pendiente', variant: 'warning' },
    approved: { label: 'Aprobada', variant: 'success' },
    rejected: { label: 'Rechazada', variant: 'danger' },
    cancelled: { label: 'Cancelada', variant: 'neutral' },
};

export function vacationRequestStatusLabel(status: string): string {
    return VACATION_REQUEST_STATUSES[status]?.label ?? status;
}

export function vacationRequestStatusVariant(status: string): StatusVariant {
    return VACATION_REQUEST_STATUSES[status]?.variant ?? 'neutral';
}

const VACATION_PERIOD_STATUSES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    current: { label: 'Vigente', variant: 'success' },
    open: { label: 'Vigente', variant: 'success' },
    active: { label: 'Vigente', variant: 'success' },
    upcoming: { label: 'Próximo', variant: 'info' },
    closed: { label: 'Cerrado', variant: 'neutral' },
    expired: { label: 'Vencido', variant: 'warning' },
    pending: { label: 'Pendiente', variant: 'warning' },
};

export function vacationPeriodStatusLabel(status: string): string {
    return VACATION_PERIOD_STATUSES[status]?.label ?? status;
}

export function vacationPeriodStatusVariant(status: string): StatusVariant {
    return VACATION_PERIOD_STATUSES[status]?.variant ?? 'neutral';
}

const HOLIDAY_TYPES: Record<string, { label: string; variant: StatusVariant }> =
    {
        mandatory: { label: 'Obligatorio', variant: 'info' },
        company: { label: 'Empresa', variant: 'neutral' },
    };

export function holidayTypeLabel(type: string): string {
    return HOLIDAY_TYPES[type]?.label ?? type;
}

export function holidayTypeVariant(type: string): StatusVariant {
    return HOLIDAY_TYPES[type]?.variant ?? 'neutral';
}

const PAYROLL_PERIOD_STATUSES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    open: { label: 'Abierto', variant: 'info' },
    calculated: { label: 'Calculado', variant: 'warning' },
    reviewed: { label: 'Revisado', variant: 'success' },
    closed: { label: 'Cerrado', variant: 'neutral' },
};

export function payrollPeriodStatusLabel(status: string): string {
    return PAYROLL_PERIOD_STATUSES[status]?.label ?? status;
}

export function payrollPeriodStatusVariant(status: string): StatusVariant {
    return PAYROLL_PERIOD_STATUSES[status]?.variant ?? 'neutral';
}

const PAYROLL_RUN_STATUSES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    draft: { label: 'Borrador', variant: 'neutral' },
    calculated: { label: 'Calculado', variant: 'warning' },
    reviewed: { label: 'Revisado', variant: 'success' },
    closed: { label: 'Cerrado', variant: 'neutral' },
};

export function payrollRunStatusLabel(status: string): string {
    return PAYROLL_RUN_STATUSES[status]?.label ?? status;
}

export function payrollRunStatusVariant(status: string): StatusVariant {
    return PAYROLL_RUN_STATUSES[status]?.variant ?? 'neutral';
}

const PAYROLL_CONCEPT_TYPES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    earning: { label: 'Percepción', variant: 'success' },
    deduction: { label: 'Deducción', variant: 'warning' },
};

export function payrollConceptTypeLabel(type: string): string {
    return PAYROLL_CONCEPT_TYPES[enumKey(type)]?.label ?? type;
}

export function payrollConceptTypeVariant(type: string): StatusVariant {
    return PAYROLL_CONCEPT_TYPES[enumKey(type)]?.variant ?? 'neutral';
}

const PAYROLL_CONCEPT_CALCULATIONS: Record<string, string> = {
    fixed: 'Monto fijo',
    per_quantity: 'Por cantidad',
};

export function payrollConceptCalculationLabel(calculation: string): string {
    return PAYROLL_CONCEPT_CALCULATIONS[enumKey(calculation)] ?? calculation;
}

const ADJUSTMENT_STATUSES: Record<
    string,
    { label: string; variant: StatusVariant }
> = {
    pending: { label: 'Pendiente', variant: 'warning' },
    approved: { label: 'Aprobado', variant: 'success' },
    rejected: { label: 'Rechazado', variant: 'danger' },
};

export function adjustmentStatusLabel(status: string): string {
    return ADJUSTMENT_STATUSES[status]?.label ?? status;
}

export function adjustmentStatusVariant(status: string): StatusVariant {
    return ADJUSTMENT_STATUSES[status]?.variant ?? 'neutral';
}

/** Nombre del usuario que registró o decidió un movimiento. */
export function actorLabel(actor: ActorRef): string {
    if (actor === null || actor === undefined) {
        return '—';
    }

    if (typeof actor === 'string' || typeof actor === 'number') {
        const text = String(actor).trim();

        return text === '' ? '—' : text;
    }

    const name = actor.name?.trim();
    const id = actor.id;

    if (name) {
        return name;
    }

    return typeof id === 'number' && id > 0 ? `Usuario #${id}` : '—';
}
