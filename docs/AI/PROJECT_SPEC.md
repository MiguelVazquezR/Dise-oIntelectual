# Especificación del proyecto — Asistencia y pre-nómina (Diseño Intelectual)

Documento consolidado. La fuente original es `02_INSTRUCCIONES_DESARROLLO_NOMINA_DSH.md` (entregada por el usuario); este archivo resume el alcance acordado y añade los criterios de arquitectura del repositorio. No duplicar la fuente completa.

## 1. Contexto técnico real (verificado)

| Elemento | Valor |
|---|---|
| Framework | Laravel 13.43 |
| PHP | 8.3 en CLI/Herd (8.5 instalado en Herd, no asignado al sitio) |
| Auth | Laravel Fortify (login, reset password) + 2FA no habilitado |
| Frontend | Vue 3.5 + TypeScript, Inertia v3 (`@inertiajs/vue3`), Vite (`vite-plus`) |
| UI | Tailwind CSS 4 + shadcn-vue (reka-ui), `resources/js/components/ui/*` |
| Rutas tipadas | Laravel Wayfinder (`resources/js/routes`, `resources/js/actions`) |
| BD | SQLite (`database/database.sqlite`), migraciones base ejecutadas |
| Branding | `public/images/logo.jpeg` (logo completo), `public/images/isologo.jpeg` (isologo/favicon) |
| Servidor | Laravel Herd — **https://diseno-intelectual.test** |

## 2. Objetivo

Sistema web de asistencia y pre-nómina listo para pruebas manuales locales. Sin despliegue, sin timbrado CFDI, sin cálculo fiscal definitivo, sin dispersión bancaria. AWS Rekognition opcional y desactivado hasta que el usuario configure credenciales.

## 3. Módulos y alcance

### 3.1 Autenticación, roles y permisos
- Login/logout y recuperación de contraseña con Fortify (ya existente). Sin registro público.
- RBAC propio (roles, permisos, pivotes) administrable por UI. Roles iniciales: `admin`, `hr`, `branch_manager`, `supervisor`, `viewer`.
- Usuarios ligados opcionalmente a una o varias sucursales. Sin asignación ⇒ sin acceso a datos de sucursal salvo permiso `data.all_branches`.
- Autorización en backend por Policies + verificación de permisos; aislamiento por sucursal en consultas, exports y acciones.
- Usuario administrador demo creado por seeder con contraseña documentada en el README local (no productiva).

### 3.2 Sucursales y horarios
- CRUD de sucursales: nombre, clave única, dirección, coordenadas y radio opcionales, zona horaria, estado.
- Horarios por sucursal con vigencia (`effective_from`/`effective_to`), por día de semana, con múltiples segmentos (turno partido) y descansos.
- Tolerancia de retardo, duración de descanso, umbral de horas extra, día de descanso semanal.
- Soporte de turnos que cruzan medianoche.
- Cambios de horario no recalculan periodos cerrados.

### 3.3 Colaboradores
- CRUD con número de empleado único, nombre, apellidos, sucursal, puesto, fecha de ingreso, estado, correo/teléfono opcionales.
- Compensación con historial de vigencia (sueldo base + frecuencia de pago).
- Baja con historial (`termination_date`), sin borrado físico de información histórica.
- Filtros, búsqueda, paginación y exportación CSV.

### 3.4 Checador
- Dos métodos: PIN (número de empleado + PIN con hash) y reconocimiento facial (AWS, opcional).
- Eventos: `clock_in`, `break_start`, `break_end`, `clock_out`.
- Registro de empleado, sucursal, tipo, fecha/hora UTC, `work_date` en zona de sucursal, método, GPS opcional (lat/lng/precisión), estado de validación y metadatos de auditoría.
- GPS nunca bloquea el marcaje; se guarda como evidencia aproximada.
- PIN nunca en texto plano, nunca en respuestas ni logs; rate limiting por empleado/IP.
- Interfaz táctil, responsive, usable en tablet/móvil.

### 3.5 AWS Rekognition (opcional)
- Adaptador desacoplado (`FaceRecognitionService` + driver AWS + driver fake etiquetado).
- Collection ID y región configurables; credenciales solo por `.env`.
- Funciones: enrolar rostro (`IndexFaces`), buscar (`SearchFacesByImage`), eliminar (`DeleteFaces`), diagnóstico de configuración sin exponer secretos.
- Sin credenciales válidas: la UI indica "Integración no configurada" y el PIN sigue operativo. Nunca simular éxito de AWS.
- No se guardan imágenes faciales localmente; solo `external_face_id` y estado.
- Consentimiento explícito registrado antes de enrolar; borrado/revocación documentado.
- No se asume liveness; se advierte que no evita suplantación.

### 3.6 Consumo y costo interno AWS
- Tabla `aws_usage_records` con operación, cantidad, estado, `request_id`, `idempotency_key` único, origen `demo`/`real`.
- Tarifa interna configurable: **$24 MXN por cada 1,000 rostros** (`AWS_REKOGNITION_PRICE_PER_1000`), cálculo decimal exacto, redondeo al final del periodo.
- Pantalla de consumo con totales por periodo/sucursal/operación; solo roles autorizados.
- Se separa explícitamente "cargo interno estimado" del costo real facturado por AWS.

### 3.7 Festivos
- CRUD por año; tipos `mandatory` (LFT art. 74) y `company`.
- Generación automática de días de descanso obligatorio con reglas versionadas y fuente oficial registrada (URL + fecha de consulta + artículo).
- No modificar festivos de años cerrados sin auditoría.

### 3.8 Asistencias e incidencias
- Vista calendario/tabla por colaborador, sucursal y rango.
- Incidencias: vacaciones, falta justificada/injustificada, incapacidad, permiso con/sin goce, descanso trabajado, festivo trabajado, otras configurables.
- Estados `pending/approved/rejected`, autor, motivo, evidencia opcional, historial y prevención de duplicados.
- Las incidencias aprobadas alimentan la pre-nómina sin duplicarse.

### 3.9 Motor de cálculo
- Servicios deterministas y probados: retardos, tiempo trabajado (descontando descansos), faltas (con exclusiones por descanso, festivo, vacación, incidencia aprobada) y horas extra propuestas con desglose.
- Sin multiplicadores legales automáticos: parámetros configurables y documentados como revisables.
- Nunca recalcula nóminas cerradas.

### 3.10 Vacaciones
- Acumulación por año laboral/aniversario con tabla legal versionada y fuente oficial.
- Periodos con días otorgados, disfrutados, ajustes y pendientes; solicitudes con autorización/rechazo.
- Saldo negativo solo con ajuste autorizado.

### 3.11 Pre-nómina
- Esquemas semanal y quincenal configurables; periodos con fechas y día de pago.
- Estados: `draft`, `calculated`, `reviewed`, `closed`. Cierre congela snapshot (reglas, horarios, importes).
- Consolidación de sueldo base, incidencias, retardos, faltas, horas extra, bonos, penalizaciones y ajustes con desglose por concepto y trazabilidad al origen.
- Recálculo idempotente: no duplica conceptos.
- Aviso visible: "Pre-nómina para revisión; no constituye CFDI ni cálculo fiscal definitivo".

### 3.12 Recibos
- Vista imprimible por colaborador/periodo con conceptos, totales y espacio de firma.
- CSS `@media print` y "Guardar como PDF" del navegador (sin dependencias nuevas).
- Marcado como preliminar/no timbrado; sin UUID ni datos fiscales inventados.

### 3.13 Bonos y penalizaciones
- Catálogo de conceptos (ingreso/descuento, importe fijo o por cantidad), ajustes por colaborador/periodo con motivo, autor y autorización.
- Descuentos marcados como sensibles y sujetos a revisión; historial y no duplicidad.

### 3.14 Dashboard
- Filtros por periodo y sucursal; colaboradores activos, retardos, faltas, horas extra, incidencias, estado de pre-nómina y comparativo por sucursal; datos reales y respeto de permisos.

## 4. Diseño
- Colores: rojo `#E83B32`, azul marino `#04315D`, blanco, grises, negro. Rojo para acciones/alertas, azul marino para navegación.
- Sidebar en escritorio, navegación adaptable en móvil; tablas con búsqueda/filtros/paginación/estados vacíos.
- Accesibilidad básica (contraste, labels, foco visible, teclado). Sin segundo framework CSS.

## 5. Fuera de alcance
CFDI/timbrado, ISR/IMSS/INFONAVIT, dispersión bancaria, app móvil nativa, liveness biométrico, despliegue, servicios productivos, microservicios/colas/Redis/Docker.

## 6. Criterios de aceptación
Los del documento fuente §11. Se marcan en `docs/AI/PROJECT_STATUS.md` conforme se verifican.
