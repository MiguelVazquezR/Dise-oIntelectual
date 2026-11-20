# Reglas de desarrollo

## Idioma y estilo
- Documentación, comentarios de negocio y mensajes de UI en español.
- Código (clases, métodos, variables, columnas, rutas) en inglés; tablas en plural snake_case.
- PHP 8 constructor property promotion; tipos de retorno y de parámetros explícitos; llaves en estructuras de control; PHPDoc solo cuando aporta.
- Vue 3 `<script setup lang="ts">`, un solo nodo raíz por componente, `defineProps` tipado.
- Seguir el formato de los archivos vecinos; no reformatear archivos ajenos a la tarea.

## Backend
- Controladores delgados: validar (Form Request) → autorizar (Policy/permiso) → delegar en Service/Action → responder con `Inertia::render` o `back()`.
- Lógica de cálculo (retardos, faltas, horas extra, vacaciones, festivos, pre-nómina, costos AWS) en `app/Services` o `app/Actions`, determinista y con reloj inyectable.
- Fechas: `CarbonImmutable`; instantes en UTC; presentación con zona de sucursal.
- Dinero: `decimal` en BD, `round()` sólo al final; nunca acumular `float`.
- Transacciones en: marcajes, generación de periodos, cálculo/cierre de pre-nómina, decisión de incidencias que afectan saldos.
- Idempotencia: `updateOrCreate`/`firstOrCreate`, `idempotency_key` en consumo AWS, recálculo que reemplaza conceptos en lugar de duplicarlos.
- Prohibido: `DB::statement` destructivo, `migrate:fresh`, `db:wipe`, borrados masivos de datos del usuario.
- Reglas de autorización siempre en backend; el frontend solo oculta, nunca protege.

## Frontend
- Reutilizar `resources/js/components/ui/*` (shadcn-vue) y los componentes existentes (`AppShell`, `AppSidebar`, `AppHeader`, `InputError`, `Heading`).
- Nueva página ⇒ ruta con nombre + importación de Wayfinder si está disponible; si no, `route()` no existe en JS: usar las funciones generadas en `resources/js/routes`/`actions`.
- Props de página documentadas en `docs/AI/ARCHITECTURE.md` §7. No inventar props; si falta un dato, pedirlo al backend a través del coordinador.
- Formularios con `useForm` de Inertia; errores bajo cada campo con `InputError`; confirmación en acciones destructivas (`Dialog`).
- Accesibilidad: `label` asociado, foco visible, contraste AA, navegación por teclado en tablas y modales.
- Sin `any` implícito; `vue-tsc --noEmit` debe pasar.

## Pruebas
- Pest (`php artisan make:test --pest Nombre`). Feature por defecto; Unit para el motor de cálculo.
- Cubrir: permisos y aislamiento por sucursal, CRUD de colaboradores, marcaje PIN + rate limit, retardo/tolerancia, jornada con descansos y turno nocturno, faltas con exclusiones, horas extra, festivos (2 años), vacaciones por aniversario, pre-nómina idempotente, tarifa AWS, AWS no configurado, AWS con SDK mockeado.
- Nunca llamar a AWS real en pruebas.
- Usar factories; datos ficticios en español (`fake('es_MX')` cuando aplique).
- Ejecutar solo el subconjunto afectado: `php artisan test --compact tests/Feature/...`.

## Comandos de verificación
```
php artisan migrate                     # nunca migrate:fresh
php artisan db:seed --class=...         # seeders idempotentes
vendor/bin/pint --dirty --format agent
php artisan test --compact <ruta>
vendor/bin/phpstan analyse              # si el módulo lo amerita
npm run types:check
npm run build                           # tras cambios de frontend
```

## Límites operativos
- No desplegar, no DNS, no servicios productivos, no pagos ni timbrado.
- No escribir secretos; `.env` solo con valores vacíos o de demostración. Nunca imprimir el `.env` completo.
- No crear datos personales reales; solo colaboradores ficticios.
- No añadir dependencias sin justificación registrada en `DECISIONS.md`.
- No delegar a otro especialista (profundidad máxima 1).
