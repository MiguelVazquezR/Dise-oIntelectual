# Estado del proyecto

Última actualización: 2026-11-19

## Completado
- **Fase 1 — Reconocimiento:** stack verificado (Laravel 13.43, Inertia v3, Fortify, Vue 3.5 + TS, Tailwind 4, shadcn-vue, SQLite, Herd PHP 8.3). Repo Git en `develop`.
- **Fase 2 — Fundamentos:** 26 migraciones, 23 modelos, 20 enums, RBAC propio (5 roles / 35 permisos), `BranchAccess`, middleware `permission`, props Inertia compartidas, marca aplicada (tokens, logo, favicons, sidebar por permisos), kit UI (PageHeader, DataTable, StatCard, StatusBadge, ConfirmDialog, Pagination, format).
- **Fase 2c:** CRUD sucursales/horarios/colaboradores, usuarios/roles, PIN con hash, export CSV, aislamiento por sucursal, seeders demo.
- **Fase 3 — Asistencia:** checador PIN con GPS opcional y rate limiting, motor de jornada (retardos con tolerancia, descansos reales/programados, turno nocturno con gracia de 120 min, horas extra propuestas), asistencias, incidencias con aprobación, `my/attendance`.
- **Fase 4 — Reglas laborales:** festivos LFT art. 74 versionados (fijos, lunes trasladados, sexenal 1 oct desde 2024, jornada electoral), vacaciones por aniversario LFT art. 76 con saldo y conversión a incidencia aprobada, `my/vacations`.
- **Fase 5 — Pre-nómina:** esquemas semanal/quincenal, periodos, cálculo idempotente con desglose y snapshot, estados borrador/calculada/revisada/cerrada, conceptos, bonos/penalizaciones con autorización, recibos imprimibles.
- **Fase 6 — AWS Rekognition:** adaptador real con SDK, modo no configurado y modo demo etiquetado, consentimiento/enrolamiento, consumo con tarifa interna $24/1,000 y `idempotency_key`, diagnóstico sin secretos.
- **Fase 7 (en curso):** dashboard con datos reales, auditoría, seeders integrados (15), plan de pruebas manuales.

## Verificación actual
- 73 rutas con permisos; suite completa **214 passed, 2 skipped** (2 547 aserciones; los 2 skipped son de Fortify, preexistentes).
- `php -d memory_limit=1G vendor/bin/phpstan analyse` → **[OK] No errors** (nivel 7).
- `vendor/bin/pint --dirty --format agent` → passed. `npm run types:check` y `npm run build` → verdes.
- `php artisan db:seed` idempotente con 15 seeders (3 sucursales, 24 colaboradores, 5 usuarios, 654 marcajes, 15 festivos, 77 periodos de vacaciones, 7 periodos de pre-nómina, 120 registros AWS demo).
- `https://diseno-intelectual.test/login` → 200, título "Diseño Intelectual", favicons servidos.
- `composer audit` y `npm audit --omit=dev`: sin vulnerabilidades.

## Revisiones
- **QA (`nomina-qa`):** corrigió un defecto crítico (la edición de colaboradores fallaba por una regla `unique` de correo sin `ignore`), un hueco de estado de pre-nómina en el dashboard y añadió 45 pruebas (smoke por rol de 28 páginas, exclusiones de faltas, actualización de colaborador, aislamiento de consumo AWS). Suite: **210 passed, 2 skipped**.
- **Seguridad (`nomina-security`):** sin hallazgos críticos ni altos; 5 corregidos (validación real de imágenes base64 por MIME y tamaño, sucursal en revisión/cierre de nómina, CSV injection en export, catálogo de periodos del dashboard, defensa en profundidad en enrolamiento) y 34 pruebas nuevas (`Security*Test`). `composer audit` y `npm audit --omit=dev` sin vulnerabilidades. Riesgos residuales documentados en `KNOWN_LIMITATIONS.md`.

## Pendiente
- Pruebas manuales del usuario (`docs/AI/MANUAL_TEST_PLAN.md`, 13 secciones).
- Validación jurídica de festivos/vacaciones (LFT art. 74 y 76) y del aviso de privacidad biométrico.
- Prueba real de AWS Rekognition cuando existan credenciales y colección (instrucciones en `KNOWN_LIMITATIONS.md`).
- Endurecimiento de producción del punto 1–2 de `KNOWN_LIMITATIONS.md` (rate limit de reset de contraseña y variables `APP_DEBUG`/`SESSION_SECURE_COOKIE`).
- Migración del menú de navegación a Wayfinder (`TODO` en `resources/js/composables/useNavigation.ts`); hoy usa rutas literales del contrato.

## Decisiones registradas
Ver `docs/AI/DECISIONS.md` (D-01…D-15).

## Comandos verificados
- `php artisan migrate` / `migrate:status` → 29 migraciones aplicadas.
- `php artisan db:seed --no-interaction` → idempotente.
- `php artisan test --compact` → 161 passed, 2 skipped.
- `php artisan route:list --except-vendor` → 75 rutas.
- `npm run types:check` y `npm run build` → verdes.
- `vendor/bin/pint --dirty --format agent` → passed.

## Instalación local

```
composer install
npm install
php artisan key:generate          # solo si .env no tiene APP_KEY
php artisan migrate               # nunca migrate:fresh
php artisan db:seed               # 15 seeders idempotentes con datos ficticios
npm run build                     # o `composer run dev` mientras se desarrolla
```
- URL: **https://diseno-intelectual.test** (Laravel Herd; el sitio está asignado a PHP 8.3, Herd también tiene 8.5 instalado).
- Usuario demo: `admin@diseno-intelectual.test` / `NominaDemo2026!` (cámbiala en local con `php artisan tinker` o desde el perfil).
- Variables AWS (solo si se quiere probar reconocimiento facial): `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REKOGNITION_COLLECTION_ID`, `AWS_DEFAULT_REGION`, `AWS_REKOGNITION_ENABLED=true`, `AWS_REKOGNITION_PRICE_PER_1000=24` (`AWS_REKOGNITION_DRIVER=aws`; `fake` solo para simulación etiquetada). Sin esas variables la integración aparece como "no configurada" y el PIN sigue operativo.
- Pruebas: `php artisan test --compact` (165 passed, 2 skipped preexistentes de Fortify).

## Siguiente acción
Cerrar la corrección del marcaje facial, ejecutar la verificación final y pasar QA + seguridad.
