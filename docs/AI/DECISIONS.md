# Decisiones y supuestos

## Decisiones tomadas

**D-01 RBAC propio, sin paquete externo.** No hay `spatie/laravel-permission` instalado y el proyecto corre Laravel 13.43 (versión reciente); añadir un paquete con restricciones de versión es riesgoso. Se implementa RBAC mínimo: `roles`, `permissions`, pivotes, `User::hasPermission()`, middleware `EnsurePermission`, Gates registrados en `AppServiceProvider`.

**D-02 Aislamiento por sucursal explícito.** `User::allowedBranchIds()` + `App\Support\BranchAccess::scope()` en cada consulta operativa; el permiso `data.all_branches` desactiva el filtro. Se verificará con pruebas y revisión de seguridad.

**D-03 Base de datos: SQLite existente.** No se cambia de motor ni se ejecutan comandos destructivos. Migraciones nuevas sólo con `migrate`.

**D-04 `aws/aws-sdk-php` se añade como dependencia única justificada** (requisito §3.5 del documento fuente). Es compatible con PHP 8.3/8.5. Se instala con `composer require aws/aws-sdk-php`.

**D-05 Sin PDF server-side.** No hay dompdf instalado; los recibos usan vista Blade/Inertia imprimible con `@media print` y "Guardar como PDF" del navegador. No se instala paquete.

**D-06 Exportaciones en CSV generadas en servidor** con `streamDownload`; no se añade `laravel-excel`.

**D-07 PHP del sitio Herd en 8.3.** El sitio `diseno-intelectual.test` está asignado a PHP 8.3 aunque Herd tiene 8.5 instalado. Se mantiene 8.3 para no alterar la configuración local del usuario; el código se escribe compatible con 8.3–8.5. El usuario puede cambiar a 8.5 desde Herd si lo desea (no es requisito).

**D-08 Zona horaria.** `APP_TIMEZONE` de la app queda en `UTC` (valor actual); las reglas de negocio usan `branch.timezone` (`America/Mexico_City` por defecto) para calcular `work_date` y presentar horas. Se documenta la conversión en `AttendanceEvent` (cast UTC → zona de sucursal al mostrar).

**D-09 Turnos que cruzan medianoche.** Un segmento con `end_time <= start_time` cruza al día siguiente; `work_date` corresponde a la fecha de inicio del turno según la sucursal. El motor resuelve la ventana como `[work_date + start, work_date + 1 día + end]`.

**D-10 Festivos legales versionados.** Reglas por año en `App\Services\Holidays\LegalHolidayRules` con `source_url = https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf` y `source_checked_at = null`. Reglas aplicadas: 1 ene; 1er lunes de feb; 3er lunes de mar; 1 may; 16 sep; 3er lunes de nov; **1 oct cada seis años desde 2024** (fr. VII reformada); 25 dic; **jornada electoral federal** en años de elección ordinaria (2027-06-06, 2030-06-02 configurables, default = primer domingo de junio). Como esta sesión no tuvo acceso a internet, la numeración de fracciones y las fechas quedan marcadas como **pendiente de validación en fuente oficial** en `KNOWN_LIMITATIONS.md` y la UI muestra aviso. Años cerrados (`year < año actual`) no se modifican sin `force` de un usuario con `data.all_branches`.

**D-15 Días de vacaciones = días naturales inclusivos.** El payload de solicitud no envía `days`; se calcula `start_date..end_date` inclusivo. No se excluyen descansos semanales ni festivos del conteo: el horario lo gobierna el motor de asistencias. Queda como punto a confirmar con el cliente.

**D-11 Vacaciones por aniversario.** Tabla de días por antigüedad (LFT art. 76) versionada en `App\Services\Vacations\VacationAccrualRules` (1→12, 2→14, 3→16, 4→18, 5→20, 6-10→22, 11-15→24, 16-20→26, 21-25→28, 26-30→30, 31-35→32, +2 por cada 5 años posteriores); los periodos se generan por año laboral con `employee_id + period_start` único y no pisan `days_adjustment`. Al aprobar una solicitud se crea/actualiza la incidencia `Vacation` aprobada, que el motor de asistencias excluye como falta. `source_checked_at = null` (validación oficial pendiente).

**D-12 Demo sin datos sensibles.** No se almacenan CURP, RFC, domicilio ni cuentas bancarias. PIN demo documentado en el seeder y cambiado por hash.

**D-13 AWS desactivado por defecto.** `AWS_REKOGNITION_ENABLED=false`; el driver `fake` sólo se activa explícitamente (`AWS_REKOGNITION_DRIVER=fake`), no identifica identidades reales y su resultado se etiqueta como simulación en la UI y en la BD (`source=demo`). El marcaje por rostro (real o simulado) se registra con `method=face` y `validation_status=flagged` por ausencia de liveness; la búsqueda sin coincidencia consume la imagen analizada y se contabiliza.

**D-14 Nómina sin reglas fiscales.** Los importes son control interno; se muestra advertencia permanente y no se calcula ISR/IMSS/INFONAVIT.

## Supuestos operativos
- S-01 Un colaborador pertenece a una sola sucursal a la vez.
- S-02 Un colaborador puede tener cuenta de usuario opcional (para `/my/*`); la mayoría no la tendrá.
- S-03 El checador es una pantalla compartida por sucursal; el operador no inicia sesión con cada colaborador.
- S-04 Periodo semanal inicia lunes; quincenal inicia día 1 y 16.
- S-05 Moneda MXN, redondeo a 2 decimales sólo al presentar/cerrar; el cálculo interno conserva precisión decimal y redondea el total al final del periodo.
- S-06 Los descansos se descuentan del tiempo trabajado usando los eventos reales; si no hay eventos de descanso, se usa el descanso configurado del horario.

## Preguntas abiertas
- Ninguna bloqueante al inicio. Si aparece, se registra aquí con fecha.
