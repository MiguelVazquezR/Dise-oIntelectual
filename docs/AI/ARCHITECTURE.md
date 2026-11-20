# Arquitectura y contrato de implementación

Contrato vinculante para todos los agentes. Si algo debe cambiar, se actualiza este archivo **y** se avisa al coordinador.

## 1. Capas

```
app/
  Enums/                 Enums tipados (BackedEnum) de estados y tipos
  Models/                Eloquent (relaciones, casts, scopes)
  Http/Controllers/       Controladores delgados (Inertia)
  Http/Controllers/Admin/ Usuarios, roles y permisos
  Http/Controllers/Kiosk/ Checador PIN/facial
  Http/Middleware/        EnsurePermission, EnsureBranchAccess
  Http/Requests/          Form Requests (validación)
  Policies/               Autorización por recurso
  Services/               Lógica de dominio (asistencia, vacaciones, festivos, nómina, AWS)
  Actions/                Operaciones transaccionales puntuales (crear/cerrar)
  Support/                Helpers (Money, Time utils, BranchAccess)
  Providers/              AppServiceProvider (Gates), FortifyServiceProvider
```

Reglas: validación en Form Requests; autorización en Policies/Gates + middleware; sin lógica de negocio en controladores ni en Vue; dinero con `decimal` (BD) y cálculo con `brick/math` no disponible ⇒ usar `round(..., 2)` sobre strings/`number_format` con cuidado, nunca `float` acumulado sin redondeo explícito.

## 2. Modelo de datos (tablas y columnas clave)

Todas con `id`, `timestamps()`. Fechas en `date`, instantes en `timestamp` UTC.

| Tabla | Columnas principales |
|---|---|
| `branches` | `name`, `code` unique, `address`, `latitude` decimal(10,7) null, `longitude` decimal(10,7) null, `geofence_radius_m` int null, `timezone` default `America/Mexico_City`, `is_active` bool |
| `user_branches` | `user_id`, `branch_id`, `is_primary` bool, unique(user_id,branch_id) |
| `roles` | `name`, `slug` unique, `description`, `is_system` bool |
| `permissions` | `name`, `slug` unique, `group` |
| `permission_role` | `role_id`, `permission_id`, unique |
| `role_user` | `user_id`, `role_id`, unique |
| `branch_schedules` | `branch_id`, `name`, `effective_from` date, `effective_to` date null, `is_active` |
| `branch_schedule_days` | `branch_schedule_id`, `weekday` tinyint (1=lunes…7=domingo), `is_rest_day` bool, `late_tolerance_minutes` int default 10, `notes` null, unique(schedule_id, weekday) |
| `branch_schedule_segments` | `branch_schedule_day_id`, `start_time`, `end_time`, `kind` enum(`work`,`break`), `sort_order` |
| `employees` | `employee_number` unique, `first_name`, `last_name`, `second_last_name` null, `branch_id`, `position`, `department` null, `email` null, `phone` null, `hire_date` date, `termination_date` date null, `status` enum(`active`,`inactive`,`terminated`), `pin_hash` null, `pin_updated_at` null, `user_id` null |
| `employee_compensations` | `employee_id`, `base_salary` decimal(12,2), `pay_frequency` enum(`weekly`,`biweekly`,`monthly`), `effective_from` date, `effective_to` date null, `notes` |
| `attendance_events` | `employee_id`, `branch_id`, `type` enum(`clock_in`,`break_start`,`break_end`,`clock_out`), `occurred_at` timestamp, `work_date` date, `method` enum(`pin`,`face`), `gps_latitude/longitude` decimal(10,7) null, `gps_accuracy_m` decimal(8,2) null, `validation_status` enum(`valid`,`flagged`,`rejected`), `validation_notes` null, `ip_address` null, `device_label` null, `metadata` json null. Índices: (employee_id, work_date), (branch_id, work_date) |
| `attendance_incidents` | `employee_id`, `branch_id`, `type` enum (ver §3), `start_date`, `end_date`, `quantity` decimal(6,2), `reason` null, `evidence_path` null, `status` enum(`pending`,`approved`,`rejected`,`cancelled`), `created_by`, `decided_by` null, `decided_at` null, `notes`. Unique(employee_id,type,start_date,end_date) |
| `holidays` | `name`, `date`, `type` enum(`mandatory`,`company`), `branch_id` null, `legal_basis` null, `source_url` null, `source_checked_at` null, `created_by`, unique(date, name, branch_id) |
| `vacation_periods` | `employee_id`, `period_start` date, `period_end` date, `anniversary_years` smallint, `days_granted` decimal(5,2), `days_adjustment` decimal(5,2) default 0, `legal_reference` null, unique(employee_id, period_start) |
| `vacation_requests` | `employee_id`, `vacation_period_id` null, `start_date`, `end_date`, `days` decimal(5,2), `status` enum(`pending`,`approved`,`rejected`,`cancelled`), `requested_by`, `decided_by` null, `decided_at` null, `notes` |
| `payroll_schedules` | `name`, `frequency` enum(`weekly`,`biweekly`,`monthly`), `start_weekday` tinyint, `cutoff_weekday` tinyint, `payment_offset_days` smallint default 2, `is_active` |
| `payroll_periods` | `payroll_schedule_id`, `start_date`, `end_date`, `payment_date`, `status` enum(`open`,`calculated`,`reviewed`,`closed`), `closed_at` null, `closed_by` null, unique(schedule_id, start_date) |
| `payroll_runs` | `payroll_period_id`, `branch_id`, `status` enum(`draft`,`calculated`,`reviewed`,`closed`), `total_earnings` decimal(12,2), `total_deductions` decimal(12,2), `total_net` decimal(12,2), `calculated_at`, `calculated_by`, `reviewed_at`, `reviewed_by`, `closed_at`, `closed_by`, `rules_snapshot` json null, unique(period_id, branch_id) |
| `payroll_items` | `payroll_run_id`, `employee_id`, `base_amount`, `overtime_amount`, `bonus_amount`, `adjustment_amount`, `absence_deduction`, `late_minutes` int, `absence_days` decimal(5,2), `overtime_minutes` int, `net_amount`, `snapshot` json null, unique(run_id, employee_id) |
| `payroll_item_concepts` | `payroll_item_id`, `code`, `name`, `type` enum(`earning`,`deduction`), `quantity` decimal(8,2), `unit_amount` decimal(12,2), `amount` decimal(12,2), `rule_applied` null, `source_type` null, `source_id` null |
| `payroll_concepts` | `code` unique, `name`, `type` enum(`earning`,`deduction`), `calculation` enum(`fixed`,`per_quantity`), `default_amount` decimal(12,2) null, `is_sensitive` bool, `is_active`, `notes` |
| `employee_payroll_adjustments` | `employee_id`, `payroll_period_id` null, `payroll_concept_id`, `amount`, `quantity`, `reason`, `status` enum(`pending`,`approved`,`rejected`), `created_by`, `approved_by` null, `approved_at` null, `applied_payroll_item_id` null, `is_sensitive` bool |
| `biometric_enrollments` | `employee_id` unique, `provider` default `aws_rekognition`, `collection_id`, `external_face_id` null, `status` enum(`pending`,`enrolled`,`failed`,`revoked`), `consent_given_at` null, `consent_version` null, `enrolled_at` null, `revoked_at` null, `last_error` null |
| `aws_usage_records` | `branch_id` null, `employee_id` null, `operation` enum(`index_faces`,`search_faces_by_image`,`detect_faces`,`delete_faces`), `quantity` int, `status` enum(`succeeded`,`failed`), `source` enum(`demo`,`real`), `aws_request_id` null, `idempotency_key` unique, `unit_price_mxn` decimal(10,4), `amount_mxn` decimal(12,6), `occurred_at`, `metadata` json null |
| `audit_logs` | `user_id` null, `action`, `auditable_type` null, `auditable_id` null, `before` json null, `after` json null, `ip_address` null, `user_agent` null, `created_at` |

No crear tablas adicionales sin justificarlo aquí.

## 3. Enums (app/Enums)

`AttendanceEventType`, `AttendanceMethod`, `AttendanceStatus`, `IncidentType` (`Vacation`,`JustifiedAbsence`,`UnjustifiedAbsence`,`SickLeave`,`PaidLeave`,`UnpaidLeave`,`WorkedRestDay`,`WorkedHoliday`,`Other`), `IncidentStatus`, `EmployeeStatus`, `PayFrequency`, `HolidayType`, `VacationRequestStatus`, `PayrollScheduleFrequency`, `PayrollPeriodStatus`, `PayrollConceptType`, `PayrollConceptCalculation`, `AdjustmentStatus`, `AwsFaceOperation`, `AwsUsageSource`, `BiometricStatus`, `Weekday`. Claves en `TitleCase`.

## 4. Permisos (slug → grupo)

- `dashboard.view`
- `branches.view`, `branches.manage`
- `schedules.view`, `schedules.manage`
- `employees.view`, `employees.manage`
- `attendance.view`, `attendance.manage`, `attendance.record`
- `incidents.view`, `incidents.manage`, `incidents.approve`
- `holidays.view`, `holidays.manage`
- `vacations.view`, `vacations.manage`, `vacations.approve`
- `payroll.view`, `payroll.calculate`, `payroll.review`, `payroll.close`
- `adjustments.view`, `adjustments.manage`, `adjustments.approve`
- `concepts.view`, `concepts.manage`
- `users.view`, `users.manage`, `roles.manage`
- `aws.settings.view`, `aws.settings.manage`, `aws.usage.view`
- `audit.view`
- `data.all_branches` (ver datos de todas las sucursales)

Roles: `admin` (todos), `hr` (todo operativo + `data.all_branches`), `branch_manager` (operativo de sus sucursales, sin `users.manage`/`aws.*`), `supervisor` (solo lectura + incidencias), `viewer` (solo lectura).

## 5. Middleware y aislamiento

- `EnsurePermission:{slug}` → 403 si el usuario autenticado no tiene el permiso (vía `User::hasPermission()`).
- `User::allowedBranchIds(): ?array` → `null` si tiene `data.all_branches` (sin filtro), o IDs asignados.
- Todo query de datos operativos pasa por `App\Support\BranchAccess::scope(Builder $query, string $column = 'branch_id')`.
- Policies por recurso; el controlador siempre autoriza antes de consultar.
- `HandleInertiaRequests` comparte `auth.user` (id, name, email), `auth.permissions` (slugs), `auth.roles`, `auth.branches`, `flash` (success/error).

## 6. Rutas (Inertia)

| Método | URI | Nombre | Controlador | Permiso |
|---|---|---|---|---|
| GET | `/dashboard` | `dashboard` | `DashboardController@index` | `dashboard.view` |
| GET | `/branches` | `branches.index` | `BranchController@index` | `branches.view` |
| GET | `/branches/create` | `branches.create` | `BranchController@create` | `branches.manage` |
| POST | `/branches` | `branches.store` | `BranchController@store` | `branches.manage` |
| GET | `/branches/{branch}/edit` | `branches.edit` | `BranchController@edit` | `branches.manage` |
| PUT | `/branches/{branch}` | `branches.update` | `BranchController@update` | `branches.manage` |
| DELETE | `/branches/{branch}` | `branches.destroy` | `BranchController@destroy` | `branches.manage` |
| GET | `/schedules` | `schedules.index` | `ScheduleController@index` | `schedules.view` |
| GET | `/schedules/create` | `schedules.create` | `ScheduleController@create` | `schedules.manage` |
| POST | `/schedules` | `schedules.store` | `ScheduleController@store` | `schedules.manage` |
| GET | `/schedules/{schedule}/edit` | `schedules.edit` | `ScheduleController@edit` | `schedules.manage` |
| PUT | `/schedules/{schedule}` | `schedules.update` | `ScheduleController@update` | `schedules.manage` |
| DELETE | `/schedules/{schedule}` | `schedules.destroy` | `ScheduleController@destroy` | `schedules.manage` |
| GET | `/employees` | `employees.index` | `EmployeeController@index` | `employees.view` |
| GET | `/employees/create` | | `EmployeeController@create` | `employees.manage` |
| POST | `/employees` | | `EmployeeController@store` | `employees.manage` |
| GET | `/employees/{employee}` | | `EmployeeController@show` | `employees.view` |
| GET | `/employees/{employee}/edit` | | `EmployeeController@edit` | `employees.manage` |
| PUT | `/employees/{employee}` | | `EmployeeController@update` | `employees.manage` |
| POST | `/employees/{employee}/pin` | `employees.pin` | `EmployeeController@updatePin` | `employees.manage` |
| GET | `/attendance` | `attendance.index` | `AttendanceController@index` | `attendance.view` |
| GET | `/attendance/employee/{employee}` | | `AttendanceController@show` | `attendance.view` |
| GET | `/incidents` | `incidents.index` | `IncidentController@index` | `incidents.view` |
| POST | `/incidents` | `incidents.store` | `IncidentController@store` | `incidents.manage` |
| PUT | `/incidents/{incident}` | | `IncidentController@update` | `incidents.manage` |
| POST | `/incidents/{incident}/decide` | | `IncidentController@decide` | `incidents.approve` |
| GET | `/holidays` | `holidays.index` | `HolidayController@index` | `holidays.view` |
| POST | `/holidays/generate/{year}` | | `HolidayController@generate` | `holidays.manage` |
| POST | `/holidays` | | `HolidayController@store` | `holidays.manage` |
| DELETE | `/holidays/{holiday}` | | `HolidayController@destroy` | `holidays.manage` |
| GET | `/vacations` | `vacations.index` | `VacationController@index` | `vacations.view` |
| POST | `/vacations/periods/generate` | | `VacationController@generatePeriods` | `vacations.manage` |
| POST | `/vacations/requests` | | `VacationRequestController@store` | `vacations.manage` |
| POST | `/vacations/requests/{request}/decide` | | `VacationRequestController@decide` | `vacations.approve` |
| GET | `/payroll/periods` | `payroll.periods.index` | `PayrollPeriodController@index` | `payroll.view` |
| POST | `/payroll/periods` | | `PayrollPeriodController@store` | `payroll.calculate` |
| GET | `/payroll/periods/{period}` | | `PayrollPeriodController@show` | `payroll.view` |
| POST | `/payroll/periods/{period}/calculate` | | `PayrollCalculationController@store` | `payroll.calculate` |
| POST | `/payroll/periods/{period}/review` | | `PayrollPeriodController@review` | `payroll.review` |
| POST | `/payroll/periods/{period}/close` | | `PayrollPeriodController@close` | `payroll.close` |
| GET | `/payroll/items/{item}/receipt` | `payroll.receipt` | `PayrollReceiptController@show` | `payroll.view` |
| GET | `/adjustments` | `adjustments.index` | `AdjustmentController@index` | `adjustments.view` |
| POST | `/adjustments` | | `AdjustmentController@store` | `adjustments.manage` |
| POST | `/adjustments/{adjustment}/decide` | | `AdjustmentController@decide` | `adjustments.approve` |
| GET | `/concepts` | `concepts.index` | `PayrollConceptController@index` | `concepts.view` |
| POST/PUT/DELETE | `/concepts` | | `PayrollConceptController` | `concepts.manage` |
| GET | `/users` | `users.index` | `Admin\UserController@index` | `users.view` |
| CRUD | `/users` | | `Admin\UserController` | `users.manage` |
| GET | `/roles` | `roles.index` | `Admin\RoleController@index` | `roles.manage` |
| PUT | `/roles/{role}` | | `Admin\RoleController@update` | `roles.manage` |
| GET | `/kiosk` | `kiosk.index` | `Kiosk\KioskController@index` | `attendance.record` |
| POST | `/kiosk/pin` | `kiosk.pin` | `Kiosk\PinClockController@store` | `attendance.record` |
| POST | `/kiosk/face` | `kiosk.face` | `Kiosk\FaceClockController@store` | `attendance.record` |
| GET | `/settings/aws` | `aws.settings` | `Settings\AwsSettingsController@index` | `aws.settings.view` |
| GET | `/aws/usage` | `aws.usage` | `AwsUsageController@index` | `aws.usage.view` |
| GET | `/audit` | `audit.index` | `AuditLogController@index` | `audit.view` |
| GET | `/my/attendance` | `my.attendance` | `MyAttendanceController@index` | — (propio empleado) |
| GET | `/my/vacations` | `my.vacations` | `MyVacationController@index` | — |

Rate limits: `kiosk/pin` → `throttle:kiosk-pin` (10/min por IP, 5/min por empleado); `kiosk/face` → `throttle:20,1`.

Archivos de rutas (un dueño por archivo, se registran en `routes/web.php`): `routes/web.php` (coordinador), `routes/admin.php`, `routes/attendance.php`, `routes/payroll.php`, `routes/settings.php` (existente).

## 7. Props compartidas y contratos de página

- Páginas en `resources/js/pages/<modulo>/Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue`.
- Listados: prop `filters` (objeto), `rows` (`{ data, meta }`), `options` (catálogos para selects).
- Formularios: prop `record` (null en create), `options`. Envío con `useForm` de Inertia y `router`.
- Mensajes: flash `success`/`error` (toast `vue-sonner`, helper existente `resources/js/lib/flashToast.ts`).
- Dinero: serializar como string con 2 decimales; formatear en frontend con `Intl.NumberFormat('es-MX', { style:'currency', currency:'MXN' })`.
- Fechas: servidor envía ISO 8601; frontend formatea con `Intl.DateTimeFormat('es-MX', { timeZone: branch.timezone })`.

## 7.1 Contrato de props — Ola 2 (sucursales, horarios, colaboradores, usuarios, roles)

Toda página recibe `can` (objeto de booleanos) para ocultar acciones no permitidas. Los listados envían `filters` (valores aplicados) y la colección paginada de Laravel (`data`, `links`, `meta`). `options` contiene catálogos para selects.

**Sucursales** — `/branches`
- `Index`: `filters {search, status}`, `branches` paginado con `{id, name, code, address, timezone, is_active, employees_count, schedules_count}`, `can {manage}`.
- `Create`/`Edit`: `branch` (`null` | `{id, name, code, address, latitude, longitude, geofence_radius_m, timezone, is_active}`), `timezones: string[]`, `can`.
- Payload: `name` (req, ≤120), `code` (req, ≤20, único), `address` (req), `latitude` (nullable, −90…90), `longitude` (nullable, −180…180), `geofence_radius_m` (nullable, 10…5000), `timezone` (req, validado contra zonas IANA), `is_active` (bool).

**Horarios** — `/schedules`
- `Index`: `filters {branch_id, search}`, `schedules` paginado `{id, name, branch {id,name}, effective_from, effective_to, is_active, days_count}`, `options.branches`, `can`.
- `Create`/`Edit`: `schedule` (`null` | `{id, name, branch_id, effective_from, effective_to, is_active, days[7]: {weekday, is_rest_day, late_tolerance_minutes, segments[]: {start_time, end_time, kind, sort_order}}}`), `options.branches`, `weekdays [{value,label}]`, `can`.
- Payload: `name`, `branch_id`, `effective_from`, `effective_to` (nullable, ≥ effective_from), `is_active`, `days` (7 elementos, `weekday` 1–7 único), cada día con `segments` (`kind`: `work`|`break`, `start_time`/`end_time` en `H:i`, sin solapamientos dentro del mismo día).
- Sin cambio de horario retroactivo: la vigencia `effective_from` es obligatoria y no puede ser anterior al inicio del último periodo cerrado de la sucursal.

**Colaboradores** — `/employees`
- `Index`: `filters {search, branch_id, status, per_page}`, `employees` paginado `{id, employee_number, full_name, branch {id,name}, position, hire_date, status, has_pin}`, `options.branches`, `options.statuses`, `can {manage}`.
- `Create`/`Edit`: `employee` (`null` | `{id, employee_number, first_name, last_name, second_last_name, branch_id, position, department, email, phone, hire_date, status, compensation {base_salary, pay_frequency, effective_from, notes}}`), `options.branches`, `options.pay_frequencies`, `options.statuses`.
- `Show`: `employee` (todo lo anterior + `pin_updated_at` y `has_pin`), `compensations` (historial), `recent_events` (últimos 10 marcajes), `vacation_summary {days_granted, days_taken, days_pending}`, `can`.
- Payload: los campos del contrato de tabla; `compensation.base_salary` string decimal ≥ 0, `compensation.pay_frequency` enum, `compensation.effective_from` fecha (por defecto `hire_date`). El PIN no se envía en el formulario de colaborador; se gestiona en `POST /employees/{employee}/pin` con `{pin, pin_confirmation}` (4–6 dígitos numéricos).
- `GET /employees/export` (nombre `employees.export`, permiso `employees.view`) responde CSV `text/csv` con BOM UTF-8, respetando filtros y aislamiento por sucursal.

**Usuarios** — `/users`
- `Index`: `filters {search, role_slug}`, `users` paginado `{id, name, email, roles[], branches[], created_at}`, `options.roles`, `options.branches`, `can {manage}`.
- `Create`/`Edit`: `user` (`null` | `{id, name, email, role_ids[], branch_ids[]}`), `options.roles`, `options.branches`, `can`.
- Payload: `name`, `email` (único), `password` (req. en creación ≥12, opcional en edición), `role_ids[]` (≥1), `branch_ids[]` (vacío = sin sucursal; no otorga acceso global).
- No se permite que el usuario autenticado se elimine a sí mismo ni quitarse el rol `admin`.

**Roles** — `/roles`
- `Index`: `roles [{id, name, slug, description, is_system, users_count, permissions[]}]`, `permission_groups [{group, permissions [{slug, name}]}]`, `can {manage}`.
- `PUT /roles/{role}` payload `permissions[]` (slugs). El enlace por defecto es por **id** (`Role` no define `getRouteKeyName()`). El rol `admin` es `is_system` y no editable (siempre todos los permisos).

### 7.2 Formas de `options` y detalles acordados

- Catálogos de entidades (`branches`, `roles`): `{ id: number, name: string }`.
- Catálogos de enums (`statuses`, `pay_frequencies`, `weekdays`, `concepts`, `incident_types`): `{ value: string, label: string }`.
- `EmployeeController@show` incluye `branch: {id, name, timezone}` (necesario para mostrar horas en la zona de la sucursal).
- `EmployeeController@edit` y `@show` incluyen `has_pin: bool` y `pin_updated_at: string|null`; solo `Show` incluye `compensations`, `recent_events` y `vacation_summary`.
- Usuarios: no se exige campo de confirmación de contraseña; `password` mínimo 12 en creación y `nullable` en edición.
- Filtro de sucursales: `status` acepta `active` | `inactive` (null = todos).
- El alta de usuario requiere al menos un rol (`role_ids` con `min:1`); `branch_ids` puede quedar vacío.
- Las rutas de roles se enlazan por id; el resto de módulos enlaza por id de modelo.

### 7.3 Contrato de props — Ola 3 (checador, asistencias, incidencias, festivos, vacaciones)

**Checador** — `/kiosk` (permiso `attendance.record`)
- Props: `branch {id, name, timezone}` (sucursal del operador; si tiene varias, `options.branches` y selector), `face {configured, enabled, collection_masked, message}` (estado de AWS sin secretos), `recent_events` (últimos 5 marcajes de la sucursal hoy: `{employee_name, type, occurred_at, method}`), `can {face: bool}`.
- `POST /kiosk/pin` payload: `{employee_number, pin, branch_id, event_type: clock_in|break_start|break_end|clock_out, gps: {latitude, longitude, accuracy}|null}`. Responde redirect 302 a `/kiosk` con `flash.success` ("Entrada registrada 07:58") o `flash.error` (PIN inválido, empleado inactivo, PIN bloqueado). Límite: 10/min por IP y 5/min por empleado (contador por `employee_number`). Nunca registra el PIN en logs ni lo devuelve.
- `POST /kiosk/face` payload: `{branch_id, event_type, image (base64/Blob), gps}`; si AWS no está configurado responde `flash.error` "Integración no configurada" y no llama a AWS.
- GPS es opcional: si falta, el marcaje se registra igual con `validation_status=valid` y `metadata.gps_missing=true`.
- La UI del checador: pantalla táctil, teclado numérico grande, 4 botones de evento, mensaje de resultado visible ≥ 4 s, sin datos sensibles.

**Asistencias** — `/attendance` (permiso `attendance.view`)
- Props: `filters {from, to, branch_id, employee_id, search}`, `rows` paginado donde cada fila es un día colaborador: `{employee {id, full_name, employee_number}, branch {id, name, timezone}, work_date, scheduled {start, end, tolerance_minutes, is_rest_day}, clock_in, break_start, break_end, clock_out, worked_minutes, break_minutes, late_minutes, overtime_minutes, status: complete|incomplete|absence|rest_day|holiday|vacation|incident, incidents [] {id, type, status}}`.
- `summary {employees, late_count, absence_count, overtime_minutes, worked_minutes}`, `options.branches`, `can {manage, incidents}`.
- `/attendance/employee/{employee}`: `employee {id, full_name, employee_number, branch {id,name,timezone}}`, `period {from, to}`, `days` (mismas filas), `totals {worked_minutes, late_minutes, overtime_minutes, absences, vacations}`.

**Incidencias** — `/incidents` (permisos `incidents.view/manage/approve`)
- Props: `filters {from, to, branch_id, employee_id, type, status}`, `incidents` paginado `{id, employee {id, full_name}, branch, type, start_date, end_date, quantity, status, reason, created_by, decided_by, decided_at}`, `options {branches, employees (búsqueda), types, statuses}`, `can {manage, approve}`.
- `POST /incidents` payload `{employee_id, type, start_date, end_date, quantity, reason, evidence (file, opcional, ≤5 MB, pdf/jpg/png)}`; el servidor obtiene `branch_id` del empleado y rechaza duplicados exactos (mismo empleado, tipo y rango).
- `PUT /incidents/{incident}` solo si `status=pending`; `POST /incidents/{incident}/decide` payload `{decision: approved|rejected, notes}` (permiso `incidents.approve`). Aprobar una incidencia de tipo vacaciones ajusta el saldo del periodo correspondiente.

**Festivos** — `/holidays` (permisos `holidays.view/manage`)
- Props: `filters {year, branch_id, type}`, `holidays` agrupado por mes `{id, name, date, type, branch_id, branch, legal_basis, source_url, source_checked_at, is_locked}`, `summary {mandatory, company, total}`, `options {years, branches}`, `can {manage}`, `legal_notice` (texto con fuente y fecha de revisión).
- `POST /holidays/generate/{year}` (idempotente, no duplica; no toca años con `is_locked`), `POST /holidays` payload `{name, date, type, branch_id (solo company), legal_basis (solo mandatory)}`, `DELETE /holidays/{holiday}` (bloqueado si el año está cerrado o hay nómina cerrada que lo use).

**Vacaciones** — `/vacations` (permisos `vacations.view/manage/approve`)
- Props: `filters {year, branch_id, employee_id}`, `periods` paginado `{id, employee {id, full_name, employee_number, hire_date}, branch, period_start, period_end, anniversary_years, days_granted, days_taken, days_adjustment, days_pending, status}`, `requests` paginado `{id, employee, start_date, end_date, days, status, requested_by, decided_by, decided_at, notes}`, `options {branches, years}`, `can {manage, approve}`.
- `POST /vacations/periods/generate` payload `{year}` (crea periodos por aniversario de forma idempotente), `POST /vacations/requests` payload `{employee_id, start_date, end_date, notes}` (valida saldo disponible; saldo negativo solo con ajuste autorizado), `POST /vacations/requests/{request}/decide` payload `{decision, notes}`.

**Mi asistencia / Mis vacaciones** (`/my/attendance`, `/my/vacations`): vistas de solo lectura para el colaborador vinculado al usuario autenticado (`employee.user_id`). Si no hay vínculo, muestran `EmptyState` explicando que la cuenta no está ligada a un colaborador. Props mínimas: `period`, `days`/`periods`, `totals`.

### 7.4 Contrato de props — Ola 4 (pre-nómina, ajustes, conceptos, recibos, AWS, dashboard, auditoría)

**Periodos de pre-nómina** — `/payroll/periods` (permisos `payroll.view/calculate/review/close`)
- `Index`: `filters {schedule_id, status, from, to}`, `periods` paginado `{id, schedule {id, name, frequency}, start_date, end_date, payment_date, status, runs_count, totals {earnings, deductions, net}}`, `options {schedules}`, `can {calculate, review, close}`, `notice` (aviso de pre-nómina preliminar).
- `Show` (`/payroll/periods/{period}`): `period` completa + `runs` por sucursal `{id, branch {id,name}, status, items_count, totals {earnings, deductions, net}, calculated_at, reviewed_at, closed_at, items [{id, employee {id, full_name, employee_number}, totals {net}}]}` + `totals` globales + `can` + `closed_snapshot_at`. Acciones: `POST /payroll/periods/{period}/calculate` (payload opcional `{force: bool}`, obligatorio si el periodo está `reviewed`), `.../review`, `.../close` (payload de `close`: `{confirm: true}`). Cálculo idempotente: reemplaza conceptos e importes, no duplica; si está `closed` responde 409 con `flash.error`.
- Nombres de componente: `payroll/Index` (listado), `payroll/Show` (detalle), `payroll/Receipt` (recibo), `adjustments/Index`, `concepts/Index`.
- `POST /payroll/periods` payload `{payroll_schedule_id, start_date, end_date, payment_date}` (único por esquema y fecha de inicio).
- Ajustes por concepto dentro del periodo: `POST /payroll/periods/{period}/adjustments` reutiliza el payload de ajustes.

**Recibos** — `/payroll/items/{item}/receipt` (permiso `payroll.view`)
- Props: `item {employee {employee_number, full_name, position}, branch, period {start_date, end_date, payment_date}, concepts [{code, name, type, quantity, unit_amount, amount}], totals {earnings, deductions, net}}`, `company {name, logo_url, address}`, `notice` ("Documento preliminar; no constituye CFDI ni cálculo fiscal definitivo"), `signature_lines`.
- Render imprimible (`@media print`, sin sidebar) con botón "Imprimir / Guardar como PDF" (`window.print()`).

**Ajustes (bonos y penalizaciones)** — `/adjustments` (permisos `adjustments.view/manage/approve`)
- `Index`: `filters {period_id, branch_id, employee_id, status, type}`, `adjustments` paginado `{id, employee, branch, concept {id, code, name, type}, amount, quantity, reason, status, created_by, approved_by, approved_at, is_sensitive}`, `options {branches, concepts, periods}`, `can {manage, approve}`, `warning` (los descuentos marcados `is_sensitive` requieren revisión).
- `POST /adjustments` payload `{employee_id, payroll_concept_id, payroll_period_id (nullable), amount, quantity, reason}`; `POST /adjustments/{adjustment}/decide` payload `{decision, notes}`.

**Conceptos** — `/concepts` (permisos `concepts.view/manage`)
- `Index`: `concepts [{id, code, name, type, calculation, default_amount, is_sensitive, is_active, notes}]`, `can {manage}`; CRUD por POST/PUT/DELETE con validación de `code` único.

**Consumo AWS** — `/aws/usage` (permiso `aws.usage.view`)
- `Index`: `filters {from, to, branch_id, operation, source}`, `totals {faces, amount_mxn, by_operation [{operation, faces, amount_mxn}], by_branch [{branch, faces, amount_mxn}]}`, `records` paginado `{id, occurred_at, branch, employee, operation, quantity, status, source, aws_request_id, amount_mxn}`, `options {branches [{id,name}], operations [{value,label}], sources [{value,label}]}`, `pricing {price_per_1000, currency, note}`, `can {}`. Nota visible: "Cargo interno de demostración; no es la tarifa oficial de AWS".
- **Configuración/diagnóstico** — `/settings/aws` (permisos `aws.settings.view/manage`): `configuration {enabled, region, collection_id_masked, has_access_key, has_secret, configured, driver}`, `checks [{label, ok, detail}]`, `notice`, `test_result` (`{ok, message, checked_at, checks[]}` | `null`, procedente de `session('test_result')`), `can {manage}`. `POST /settings/aws/test` responde 302 back con `flash.success`/`flash.error` y `session('test_result')`; no modifica `.env` ni expone claves. No se implementa edición de credenciales desde la UI (se documentan las variables de entorno).

**Dashboard** — `/dashboard` (permiso `dashboard.view`)
- Props: `filters {period_id, branch_id}`, `options {periods [{id, label}], branches [{id, name}]}`, `metrics {active_employees, late_count, justified_absences, unjustified_absences, overtime_minutes, incidents_count}`, `by_branch [{branch {id,name}, employees, late_count, absences, overtime_minutes}]`, `trend [{date, late_count, absences}]`, `recent_incidents [{id, employee {id, full_name}, branch {id,name}, type, start_date, end_date, status}]`, `payroll {period {id, label, start_date, end_date, status}|null, totals {earnings, deductions, net}}`, `can {payroll, incidents, attendance, aws_usage, audit}`.

**Auditoría** — `/audit` (permiso `audit.view`)
- Props: `filters {action, user_id, from, to}`, `logs` paginado `{id, created_at, user, action, auditable_type, auditable_id, ip_address}`, `options {actions, users}`. Sin payloads con datos sensibles (PIN, biometría, secretos).

### 7.5 Props aditivas aceptadas (implementadas y verificadas)

Además de lo declarado en §7.1–§7.4, el backend envía estas props; el frontend puede usarlas sin romper el contrato si están ausentes:

- `employees/Show` → `branch {id, name, timezone}`.
- `payroll/Show` → `items [{id, employee, branch, net_amount, totals}]`, `notice`.
- `payroll/Index` → `notice`.
- `vacations/Index` → `rules` (tabla legal), `options.employees`, `options.request_statuses`, `days_available` por periodo.
- `holidays/Index` → `year_locked`, `options.types`.
- `aws/Usage` → `options {branches, operations, sources}`, `can`, `per_page`.
- Listados (`attendance`, `incidents`, `aws/Usage`) → `filters.per_page`.
- `dashboard/Index` → `payroll.status` (además de `payroll.period`).
- `settings/Aws` → `test_result` (desde `session('test_result')`), `can.manage`.

Comportamiento acordado: los filtros por sucursal ajena en listados operativos (`attendance`, `incidents`) devuelven la colección filtrada por `BranchAccess` (vacía), mientras que las páginas de detalle, recibos, dashboard y consumo responden 403. Cualquier cambio a esta regla debe reflejarse aquí.

## 8. Referencias visuales
- Logo completo en login y sidebar (`public/images/logo.jpeg`), isologo en favicon y sidebar colapsado (`public/images/isologo.jpeg`).
- Tokens de marca en `resources/css/app.css` (`--primary` = azul marino, `--destructive`/acento = rojo marca); no introducir CSS paralelo.
