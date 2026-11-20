# Plan de pruebas manuales

Entorno: **https://diseno-intelectual.test** (Laravel Herd, PHP 8.3, SQLite).
Datos de demostración: ejecutar `php artisan db:seed --no-interaction` (idempotente).

## Credenciales demo

| Usuario | Contraseña | Rol | Alcance |
|---|---|---|---|
| `admin@diseno-intelectual.test` | `NominaDemo2026!` | Administrador | Todo |
| `rrhh@diseno-intelectual.test` | `NominaDemo2026!` | Recursos Humanos | Todo operativo, sin usuarios/AWS settings |
| `norte@diseno-intelectual.test` | `NominaDemo2026!` | Encargado de sucursal | Solo Sucursal Norte |
| `supervisor@diseno-intelectual.test` | `NominaDemo2026!` | Supervisor | Solo lectura + incidencias de Sucursal Sur |
| `consulta@diseno-intelectual.test` | `NominaDemo2026!` | Consulta | Solo lectura de Matriz Centro |

PIN del checador: últimos 4 dígitos del número de empleado (`EMP-0007` → `0007`).
Sucursales demo: `MATRIZ`, `NORTE` (turno nocturno 22:00–06:00), `SUR` (turno partido).

## 1. Acceso y seguridad de sesión
1. Abrir `https://diseno-intelectual.test` → redirige a `/login` con marca y logo.
2. Entrar con `admin@…` → dashboard.
3. Cerrar sesión desde el menú de usuario.
4. Probar `¿Olvidaste tu contraseña?` (el correo se registra en `storage/logs/laravel.log` con el driver `log`).
- Esperado: sin errores 500, favicon visible, título "Diseño Intelectual".

## 2. Identidad visual y navegación
1. Revisar sidebar (4 secciones), colapso a isologo, modo oscuro desde el menú de apariencia.
2. Reducir la ventana a ~400 px: navegación en `Sheet` sin scroll horizontal.
3. Verificar foco visible con `Tab` en menús, tablas y modales.
- Esperado: colores de marca (marino `#04315D`, rojo `#E83B32`), sin elementos del starter kit (Repository/Documentation).

## 3. Sucursales y horarios
1. `/branches` → listar 3 sucursales; crear `Prueba QA` (zona horaria `America/Mexico_City`).
2. Intentar borrar una sucursal con colaboradores → mensaje de error, no se elimina.
3. `/schedules` → abrir NORTE: verificar turno 22:00–06:00 y aviso de "cruza medianoche".
4. Crear un horario con dos segmentos solapados → error de validación.
- Esperado: validación en servidor, mensajes en español, isolación por sucursal (probar con `norte@…`).

## 4. Colaboradores
1. `/employees` → buscar, filtrar por sucursal y estado, paginar.
2. Crear colaborador con compensación; verificar que aparece con badge "PIN: Sin asignar".
3. Asignar PIN desde la ficha (4–6 dígitos) y comprobar "Asignado".
4. `Exportar CSV` con sesión de `norte@…` → solo colaboradores de esa sucursal.
5. Dar de baja (estado `terminated` + fecha) y verificar que deja de aparecer en asistencias.

## 5. Checador
1. `/kiosk` con `admin` o `norte@`: teclado numérico, captura `EMP-0001` + PIN `0001`.
2. Elegir "Entrada" → banner de éxito ≥ 4 s, panel de últimos marcajes actualizado.
3. Repetir el mismo evento en <60 s → mensaje de doble marcaje, sin fila duplicada.
4. Denegar el permiso de ubicación → marcaje se registra igual con aviso "Sin ubicación (opcional)".
5. Conceder ubicación → verificar en BD `attendance_events.gps_latitude/longitude/accuracy` poblados.
6. PIN incorrecto 5 veces → bloqueo temporal ("demasiados intentos").
7. Verificar que el PIN no aparece en `storage/logs/laravel.log` ni en respuestas.
8. Panel de AWS: debe indicar "Integración no configurada" y deshabilitar el botón de rostro.

## 6. Asistencias e incidencias
1. `/attendance` → filtrar por rango y sucursal; revisar retardos, faltas, horas extra y estados.
2. Abrir `/attendance/employee/{id}` → totales y detalle por día.
3. `/incidents` → crear falta justificada, aprobarla, verificar badge y exclusión en asistencias.
4. Crear incidencia duplicada (mismo empleado, tipo y rango) → rechazo.
5. Revisar `/my/attendance` con un usuario sin colaborador vinculado → estado vacío explicativo.

## 7. Festivos
1. `/holidays` → año actual con los 7 festivos oficiales + aviso "pendiente de validación legal".
2. Generar festivos del año siguiente → idempotente (segunda ejecución no duplica).
3. Agregar festivo `company` por sucursal; borrarlo.
4. Intentar borrar un festivo de un año cerrado → bloqueado con mensaje.

## 8. Vacaciones
1. `/vacations` → generar periodos del año; verificar días otorgados por antigüedad.
2. Crear solicitud con saldo disponible; aprobarla → el saldo baja y aparece incidencia `Vacation` aprobada.
3. Solicitud con saldo insuficiente → rechazo con mensaje.
4. `/my/vacations` → solo lectura con saldo.

## 9. Pre-nómina
1. `/payroll/periods` → ver periodo semanal y quincenal demo; crear uno nuevo.
2. Abrir detalle → "Calcular": verificar conceptos desglosados (sueldo, horas extra, faltas, bonos).
3. Recalcular dos veces → mismos importes, sin conceptos duplicados.
4. "Marcar como revisada" y "Cerrar periodo" (con confirmación); tras el cierre, intentar recalcular → rechazo explicado.
5. Abrir un recibo `/payroll/items/{id}/receipt` → vista limpia, botón Imprimir, aviso "preliminar, no CFDI".
6. Guardar como PDF desde el navegador y verificar que no hay menús ni sidebar en la impresión.

## 10. Bonos y penalizaciones
1. `/adjustments` → crear bono y penalización con motivo; aprobar ambos.
2. Verificar que aparecen en la pre-nómina del periodo.
3. Verificar que una penalización sensible muestra la advertencia de revisión.

## 11. Consumo y configuración AWS
1. `/aws/usage` → registros demo con insignia DEMO, totales y nota de tarifa interna.
2. Cambiar filtros de periodo/sucursal/operación.
3. `/settings/aws` → integración deshabilitada, colección enmascarada, sin claves visibles.
4. "Probar configuración" sin credenciales → resultado controlado, sin errores 500.
5. Verificar que el checador por PIN sigue operativo.

## 12. Dashboard y auditoría
1. `/dashboard` → métricas reales, filtros por periodo/sucursal, comparativo por sucursal y tendencia.
2. Verificar que los números cambian al registrar marcajes/incidencias.
3. `/audit` → entradas de festivos, vacaciones, incidencias, nómina y ajustes; sin datos sensibles.

## 13. Permisos y aislamiento
1. Con `norte@…` abrir `/employees/{id}` de SUR → 403.
2. Con `supervisor@…` intentar crear colaborador → acción oculta y 403 por URL directa.
3. Con `consulta@…` abrir `/users` y `/roles` → 403.
4. Con `rrhh@…` abrir `/settings/aws` y `/users` → 403 (sin usuarios ni configuración AWS).

## Pendientes conocidos
- Validación legal de festivos y tabla de vacaciones en fuente oficial (LFT art. 74 y 76).
- Prueba real de reconocimiento facial: requiere credenciales AWS, colección creada y consentimiento; ver `docs/AI/KNOWN_LIMITATIONS.md`.
