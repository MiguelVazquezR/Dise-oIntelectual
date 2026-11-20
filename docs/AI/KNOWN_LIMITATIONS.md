# Limitaciones conocidas

Se actualiza conforme avanza el proyecto.

## Seguridad aceptada para el entorno local

Estas decisiones son aceptables para la demo local de pruebas manuales, pero **deben resolverse antes de cualquier uso productivo**:

1. **Recuperación de contraseña sin rate limiting propio** (Fortify solo limita el login). En producción añadir un limitador a `password.email`/`password.reset` (p. ej. `throttle:6,1`) desde `config/fortify.php` o un middleware dedicado.
2. **Hardening de producción pendiente de configurar**: `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, HTTPS forzado y `TRUSTED_PROXIES` si hay balanceador.
3. **Bloqueo de PIN dirigido**: el limitador HTTP se liga a empleado + IP, pero el **bloqueo interno de 15 minutos tras 5 fallos sigue siendo por `employee_number`**, por lo que un tercero podría dejar temporalmente sin checador a un empleado conocido. Usar PIN de 6 dígitos y ligar ese contador a sucursal/IP en producción.
4. **Cambios de PIN auditados** en `audit_logs` (sin guardar el PIN ni el hash).
5. **Cookie de apariencia sin cifrar** (valor `light`/`dark`/`system`); no es explotable, pero puede firmarse/cifrarse en producción.
6. **Auditoría global sin aislamiento por sucursal** (permiso `audit.view`, por defecto sólo administrador y RRHH).
7. **Aviso de privacidad biométrico**: el sistema registra `consent_version` y fecha, pero el texto legal (LFPDPPP) y la base jurídica deben validarse con asesoría legal y ligarse al documento entregado al colaborador.

## Integraciones y entorno
- **AWS Rekognition desactivado por defecto.** Sin credenciales válidas la UI muestra "Integración no configurada"; el marcaje por PIN sigue operativo. No se han realizado llamadas reales a AWS.
- **Marcaje facial sin liveness.** El marcaje por rostro se registra con `method=face` y `validation_status=flagged` (nota: reconocimiento facial sin verificación de vida), por lo que requiere validación manual y no sustituye al PIN. La identificación fallida no crea asistencia; una búsqueda sin coincidencia sí se contabiliza como rostro analizado (AWS cobra la imagen).
- **Costo AWS.** La tarifa de $24 MXN por 1,000 rostros es un cargo interno acordado, no la tarifa oficial de AWS. El consumo mostrado en modo demo está etiquetado como simulado.
- **PHP del sitio en 8.3.** Herd tiene 8.5 instalado pero el sitio está asignado a 8.3; el código es compatible con ambos.
- **PHPStan** requiere `memory_limit` mayor al predeterminado de la CLI (usar `php -d memory_limit=1G vendor/bin/phpstan analyse`).

## Checador y datos personales
- **Bloqueo de PIN por `employee_number`**, con ventana de 15 minutos tras 5 fallos, además del límite por IP (`kiosk-pin`). El throttle HTTP devuelve 429 y el contador interno devuelve 302 con mensaje genérico: comportamiento aceptado y documentado.
- El GPS del navegador se guarda como **evidencia aproximada**, nunca como prueba de presencia; no se aplica geocerca en esta versión.
- No se almacenan imágenes faciales ni datos fiscales (CURP, RFC, cuentas bancarias).

## Alcance legal/fiscal
- **Festivos LFT art. 74: pendiente de validación en fuente oficial.** Reglas codificadas (fechas fijas, lunes trasladados, 1 de octubre sexenal desde 2024, jornada electoral federal 2027-06-06 / 2030-06-02 configurables) con `source_url` = LFT (diputados.gob.mx) y `source_checked_at = null` porque la sesión no tuvo acceso a internet. La UI muestra el aviso; el usuario debe confirmar fechas y fracciones antes de usarlas en producción.
- **Tabla de vacaciones LFT art. 76: pendiente de validación en fuente oficial.** Misma situación (`source_checked_at = null`).
- **Días de vacaciones contados como naturales inclusivos** (ver `DECISIONS.md` D-15); no se descuentan descansos semanales ni festivos del saldo. Punto a confirmar con el cliente.
- Sin timbrado CFDI, ISR, IMSS, INFONAVIT ni dispersión bancaria. La pre-nómina es control interno.
- Los cálculos de horas extra son propuestas configurables, no multiplicadores legales automáticos.
