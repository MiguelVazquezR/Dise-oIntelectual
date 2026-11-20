<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    CloudCog,
    FlaskConical,
    Info,
    KeyRound,
    ListChecks,
    Lock,
    ServerCog,
    ShieldAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import type { AwsCheck, AwsSettingsProps, AwsTestResult } from '@/types/aws';

const props = defineProps<AwsSettingsProps>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Configuración AWS', href: '/settings/aws' }],
    },
});

const configuration = computed(() => props.configuration ?? null);

const configured = computed(() => configuration.value?.configured === true);

/** Solo un `manage` explícito habilita acciones; nunca se asume permiso. */
const canManage = computed(() => props.can?.manage === true);

const checks = computed<AwsCheck[]>(() => props.checks ?? []);

/** Nombres de variables de entorno; nunca se muestran valores. */
const ENV_VARIABLES: { name: string; description: string }[] = [
    {
        name: 'AWS_REKOGNITION_ENABLED=true',
        description: 'Activa el reconocimiento facial (por defecto está en false).',
    },
    {
        name: 'AWS_ACCESS_KEY_ID',
        description: 'Identificador de la llave de un usuario IAM con permiso a Rekognition.',
    },
    {
        name: 'AWS_SECRET_ACCESS_KEY',
        description: 'Llave secreta correspondiente. Se configura solo en el servidor.',
    },
    {
        name: 'AWS_REKOGNITION_COLLECTION_ID',
        description: 'Identificador de la colección de rostros ya creada en AWS.',
    },
    {
        name: 'AWS_DEFAULT_REGION',
        description: 'Región de AWS donde vive la colección, por ejemplo us-east-1.',
    },
];

/* Prueba de configuración */
const isTesting = ref(false);
const localResult = ref<AwsTestResult | null>(null);

const result = computed<AwsTestResult | null>(
    () => localResult.value ?? props.test_result ?? null,
);

function runTest(): void {
    if (isTesting.value || !canManage.value) {
        return;
    }

    localResult.value = null;

    router.post(
        '/settings/aws/test',
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isTesting.value = true;
            },
            onSuccess: () => {
                isTesting.value = false;
            },
            onError: () => {
                isTesting.value = false;
            },
            onFinish: () => {
                isTesting.value = false;
            },
        },
    );
}

function credentialSummary(): string {
    if (!configuration.value) {
        return '—';
    }

    if (configuration.value.has_access_key && configuration.value.has_secret) {
        return 'Configuradas (solo se confirma su existencia)';
    }

    if (configuration.value.has_access_key || configuration.value.has_secret) {
        return 'Incompletas';
    }

    return 'No configuradas';
}

function credentialVariant(): 'success' | 'warning' | 'neutral' {
    if (!configuration.value) {
        return 'neutral';
    }

    const { has_access_key: hasAccessKey, has_secret: hasSecret } =
        configuration.value;

    if (hasAccessKey && hasSecret) {
        return 'success';
    }

    return hasAccessKey || hasSecret ? 'warning' : 'neutral';
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Configuración AWS" />

        <PageHeader
            title="Configuración de AWS Rekognition"
            description="Estado, diagnóstico y activación del reconocimiento facial del checador. Las claves se administran en el servidor y nunca se muestran ni se capturan aquí."
        >
            <template #actions>
                <StatusBadge :variant="configured ? 'success' : 'warning'">
                    {{ configured ? 'Integración configurada' : 'Integración no configurada' }}
                </StatusBadge>
                <StatusBadge v-if="!canManage" variant="neutral">
                    <Lock class="size-3.5" aria-hidden="true" />
                    Solo lectura
                </StatusBadge>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="!canManage || isTesting"
                    :title="
                        canManage
                            ? 'Comprueba la configuración sin exponer secretos'
                            : 'Solo lectura: requiere el permiso aws.settings.manage'
                    "
                    @click="runTest"
                >
                    <Spinner v-if="isTesting" />
                    <FlaskConical v-else aria-hidden="true" />
                    {{ isTesting ? 'Probando…' : 'Probar configuración' }}
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <p
            v-if="props.notice"
            role="note"
            class="flex items-start gap-2 rounded-lg border border-sky-700/30 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-100"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span class="text-pretty">{{ props.notice }}</span>
        </p>

        <p
            v-if="!configured"
            role="note"
            class="flex items-start gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-foreground"
        >
            <KeyRound class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span class="text-pretty">
                <strong class="font-semibold">Integración no configurada:</strong>
                el checador por PIN sigue operativo y no se registran cargos de
                reconocimiento facial. El consumo se marca como simulación
                mientras no haya credenciales válidas.
            </span>
        </p>

        <section
            class="rounded-lg border border-border bg-card p-4 md:p-6"
            aria-labelledby="aws-configuration"
        >
            <div class="flex items-center gap-2">
                <CloudCog class="size-4 text-primary" aria-hidden="true" />
                <h2 id="aws-configuration" class="text-base font-semibold">
                    Estado de la integración
                </h2>
            </div>

            <dl
                v-if="configuration"
                class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                <div class="space-y-1">
                    <dt class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Habilitada
                    </dt>
                    <dd>
                        <StatusBadge
                            :variant="configuration.enabled ? 'success' : 'warning'"
                        >
                            {{ configuration.enabled ? 'Activada' : 'Desactivada' }}
                        </StatusBadge>
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Región
                    </dt>
                    <dd class="font-mono text-sm text-foreground">
                        {{ configuration.region ?? 'No definida' }}
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Colección
                    </dt>
                    <dd class="font-mono text-sm break-all text-foreground">
                        {{ configuration.collection_id_masked ?? 'No definida' }}
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Credenciales
                    </dt>
                    <dd class="flex flex-wrap items-center gap-2 text-sm">
                        <StatusBadge :variant="credentialVariant()">
                            {{ credentialSummary() }}
                        </StatusBadge>
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Driver
                    </dt>
                    <dd class="font-mono text-sm text-foreground">
                        {{ configuration.driver ?? '—' }}
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Estado general
                    </dt>
                    <dd class="text-sm text-foreground">
                        {{
                            configuration.configured
                                ? 'Lista para operar con AWS'
                                : 'Operando en modo local (PIN)'
                        }}
                    </dd>
                </div>
            </dl>

            <EmptyState
                v-else
                class="mt-4 border-0 bg-transparent"
                :icon="CloudCog"
                title="Sin estado de la integración"
                description="El backend todavía no devuelve el estado de AWS. Mientras tanto, el checador por PIN sigue operativo."
            />
        </section>

        <section
            class="rounded-lg border border-border bg-card p-4 md:p-6"
            aria-labelledby="aws-checks"
        >
            <div class="flex items-center gap-2">
                <ListChecks class="size-4 text-primary" aria-hidden="true" />
                <h2 id="aws-checks" class="text-base font-semibold">
                    Diagnóstico
                </h2>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Comprobaciones que ejecuta el servidor; no requieren permisos de
                AWS en el navegador.
            </p>

            <div
                v-if="result"
                role="status"
                aria-live="polite"
                class="mt-4 flex items-start gap-3 rounded-lg border px-3 py-2 text-sm"
                :class="
                    result.ok
                        ? 'border-emerald-600/30 bg-emerald-50 text-emerald-900 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-100'
                        : 'border-destructive/40 bg-destructive/10 text-destructive-strong'
                "
            >
                <CircleCheck
                    v-if="result.ok"
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <CircleX v-else class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <div class="min-w-0 space-y-1">
                    <p class="font-medium">
                        {{ result.ok ? 'Prueba de configuración exitosa' : 'La prueba de configuración falló' }}
                    </p>
                    <p v-if="result.message" class="text-pretty">
                        {{ result.message }}
                    </p>
                    <p
                        v-if="result.checked_at"
                        class="text-xs text-muted-foreground"
                    >
                        Última prueba: {{ formatDateTime(result.checked_at) }}
                    </p>
                </div>
            </div>

            <ul v-if="checks.length > 0" class="mt-4 space-y-2">
                <li
                    v-for="(check, index) in checks"
                    :key="`${check.label}-${index}`"
                    class="flex items-start gap-3 rounded-md border border-border px-3 py-2"
                >
                    <CircleCheck
                        v-if="check.ok"
                        class="mt-0.5 size-4 shrink-0 text-emerald-700 dark:text-emerald-300"
                        aria-hidden="true"
                    />
                    <CircleX
                        v-else
                        class="mt-0.5 size-4 shrink-0 text-destructive-strong"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-medium text-foreground">
                            {{ check.label }}
                            <span class="sr-only">
                                {{ check.ok ? ': correcto' : ': error' }}
                            </span>
                        </p>
                        <p
                            v-if="check.detail"
                            class="text-sm break-words text-muted-foreground"
                        >
                            {{ check.detail }}
                        </p>
                    </div>
                </li>
            </ul>

            <EmptyState
                v-else
                class="mt-4"
                :icon="ListChecks"
                title="Sin comprobaciones disponibles"
                description="El backend todavía no devuelve el diagnóstico de la integración."
            />
        </section>

        <section
            class="rounded-lg border border-border bg-card p-4 md:p-6"
            aria-labelledby="aws-activation"
        >
            <div class="flex items-center gap-2">
                <ServerCog class="size-4 text-primary" aria-hidden="true" />
                <h2 id="aws-activation" class="text-base font-semibold">
                    Activación
                </h2>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                La activación se hace en el servidor editando el archivo
                <code class="rounded bg-muted px-1 py-0.5 font-mono text-xs">.env</code>;
                nunca desde el navegador ni en este repositorio.
            </p>

            <ol class="mt-4 space-y-3">
                <li
                    v-for="(variable, index) in ENV_VARIABLES"
                    :key="variable.name"
                    class="flex items-start gap-3"
                >
                    <span
                        class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                        aria-hidden="true"
                    >
                        {{ index + 1 }}
                    </span>
                    <div class="min-w-0 space-y-1">
                        <code
                            class="block w-fit max-w-full overflow-x-auto rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground"
                        >
                            {{ variable.name }}
                        </code>
                        <p class="text-sm text-muted-foreground">
                            {{ variable.description }}
                        </p>
                    </div>
                </li>
            </ol>

            <p
                class="mt-4 flex items-start gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-foreground"
                role="note"
            >
                <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span class="text-pretty">
                    Las credenciales deben pertenecer a un usuario IAM con permiso
                    exclusivo a Rekognition y rotarse de forma periódica. Esta
                    pantalla solo confirma si existen: nunca las muestra ni
                    permite capturarlas.
                </span>
            </p>
        </section>

        <section
            class="rounded-lg border border-amber-600/40 bg-amber-50 p-4 text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-100 md:p-6"
            role="note"
            aria-labelledby="aws-limitations"
        >
            <div class="flex items-start gap-3">
                <ShieldAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                <div class="space-y-2 text-sm">
                    <h2 id="aws-limitations" class="text-base font-semibold">
                        Limitaciones y consentimiento (KNOWN_LIMITATIONS)
                    </h2>
                    <p class="text-pretty">
                        <strong class="font-semibold">Sin verificación de vida:</strong>
                        el reconocimiento facial no evita la suplantación con una
                        fotografía o un video; requiere validación adicional antes
                        de usarse en producción.
                    </p>
                    <p class="text-pretty">
                        <strong class="font-semibold">Consentimiento:</strong>
                        el registro biométrico del personal exige aviso de
                        privacidad y consentimiento informado por escrito. Los
                        datos de rostro son sensibles y deben tratarse conforme a
                        la normativa aplicable.
                    </p>
                    <p class="text-pretty">
                        Mientras la integración no esté configurada, el checador
                        por PIN sigue siendo el método operativo y no se realizan
                        llamadas reales a AWS.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
