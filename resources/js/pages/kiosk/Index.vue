<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    RotateCcw,
    ScanFace,
    Video,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import KioskLayout from '@/layouts/kiosk/KioskLayout.vue';
import { formatDateTime, formatTime } from '@/lib/format';
import { attendanceEventLabel, attendanceMethodLabel } from '@/lib/labels';
import { toSelectOptions } from '@/lib/options';
import type {
    GpsPayload,
    KioskEventType,
    KioskFaceFormData,
    KioskFaceStatus,
    KioskPinFormData,
    KioskProps,
    KioskRecentEvent,
} from '@/types';

const props = defineProps<KioskProps>();

defineOptions({
    layout: KioskLayout,
});

type Step = 'employee' | 'pin' | 'event';

type Banner = {
    type: 'success' | 'error';
    message: string;
};

type FlashMessages = {
    success?: string | null;
    error?: string | null;
};

const GPS_TIMEOUT_MS = 8000;
const BANNER_VISIBLE_MS = 6000;
const PIN_MIN_LENGTH = 4;
const PIN_MAX_LENGTH = 6;
const EMPLOYEE_NUMBER_MAX_LENGTH = 30;

const EVENT_BUTTONS: {
    value: KioskEventType;
    label: string;
    description: string;
}[] = [
    {
        value: 'clock_in',
        label: 'Entrada',
        description: 'Inicio de la jornada',
    },
    {
        value: 'break_start',
        label: 'Salida a descanso',
        description: 'Comienza la pausa',
    },
    {
        value: 'break_end',
        label: 'Regreso de descanso',
        description: 'Termina la pausa',
    },
    {
        value: 'clock_out',
        label: 'Salida final',
        description: 'Fin de la jornada',
    },
];

const branchOptions = computed(() => toSelectOptions(props.options?.branches));

const branchId = ref<string>(
    props.branch
        ? String(props.branch.id)
        : (branchOptions.value[0]?.value ?? ''),
);

const step = ref<Step>('employee');
const employeeNumber = ref('');
const pin = ref('');
const banner = ref<Banner | null>(null);
const gpsNotice = ref<string | null>(null);
const activeEvent = ref<KioskEventType | ''>('');
const now = ref(new Date());

let bannerTimer: ReturnType<typeof setTimeout> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

const form = useForm<KioskPinFormData>({
    employee_number: '',
    pin: '',
    branch_id: branchId.value,
    event_type: '',
    gps: null,
});

const face = computed<KioskFaceStatus | null>(() => props.face ?? null);

const faceConfigured = computed(
    () => face.value?.configured === true && face.value?.enabled !== false,
);

const canUseFace = computed(
    () => faceConfigured.value && props.can.face === true,
);

const recentEvents = computed<KioskRecentEvent[]>(
    () => props.recent_events ?? [],
);

const branchTimezone = computed<string | undefined>(
    () => props.branch?.timezone ?? undefined,
);

/** Al cambiar de sucursal se recargan los últimos marcajes de esa sucursal. */
watch(branchId, (value, previous) => {
    if (value === '' || value === previous || form.processing) {
        return;
    }

    router.get(
        '/kiosk',
        { branch_id: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

const stepTitle = computed(() => {
    switch (step.value) {
        case 'employee':
            return 'Número de empleado';
        case 'pin':
            return 'PIN';
        default:
            return 'Selecciona el evento';
    }
});

const stepHint = computed(() => {
    switch (step.value) {
        case 'employee':
            return 'Captura tu número de colaborador con el teclado.';
        case 'pin':
            return 'Captura tu PIN de 4 a 6 dígitos. Se oculta por seguridad.';
        default:
            return 'Elige el movimiento que vas a registrar.';
    }
});

const canGoNext = computed(() => {
    if (step.value === 'employee') {
        return employeeNumber.value.length > 0;
    }

    if (step.value === 'pin') {
        return (
            pin.value.length >= PIN_MIN_LENGTH &&
            pin.value.length <= PIN_MAX_LENGTH
        );
    }

    return false;
});

const displayValue = computed(() => {
    if (step.value === 'employee') {
        return employeeNumber.value;
    }

    if (step.value === 'pin') {
        return '•'.repeat(pin.value.length);
    }

    return attendanceEventLabel(activeEvent.value || 'clock_in');
});

const displayAriaLabel = computed(() => {
    if (step.value === 'employee') {
        return `Número de empleado capturado: ${employeeNumber.value || 'vacío'}`;
    }

    if (step.value === 'pin') {
        return `PIN con ${pin.value.length} dígitos capturados`;
    }

    return 'Evento por registrar';
});

const currentTime = computed(() => formatTime(now.value, branchTimezone.value));

const branchLabel = computed(
    () => props.branch?.name ?? 'Sucursal sin asignar',
);

function showBanner(type: Banner['type'], message: string): void {
    window.clearTimeout(bannerTimer);
    banner.value = { type, message };
    bannerTimer = window.setTimeout(() => {
        banner.value = null;
    }, BANNER_VISIBLE_MS);
}

function readFlashFromPage(pageFlash: unknown): FlashMessages {
    if (pageFlash !== null && typeof pageFlash === 'object') {
        return pageFlash as FlashMessages;
    }

    return {};
}

function addCharacter(character: string): void {
    if (step.value === 'employee') {
        if (!/^[a-z0-9-]$/i.test(character)) {
            return;
        }

        if (employeeNumber.value.length < EMPLOYEE_NUMBER_MAX_LENGTH) {
            employeeNumber.value += character.toUpperCase();
        }

        return;
    }

    if (
        step.value === 'pin' &&
        /^[0-9]$/.test(character) &&
        pin.value.length < PIN_MAX_LENGTH
    ) {
        pin.value += character;
    }
}

function addDigit(digit: string): void {
    addCharacter(digit);
}

function removeDigit(): void {
    if (step.value === 'employee') {
        employeeNumber.value = employeeNumber.value.slice(0, -1);

        return;
    }

    if (step.value === 'pin') {
        pin.value = pin.value.slice(0, -1);
    }
}

function clearEntry(): void {
    if (step.value === 'employee') {
        employeeNumber.value = '';

        return;
    }

    pin.value = '';
}

function goNext(): void {
    if (!canGoNext.value) {
        return;
    }

    if (step.value === 'employee') {
        step.value = 'pin';
        pin.value = '';
        form.clearErrors('employee_number');

        return;
    }

    if (step.value === 'pin') {
        step.value = 'event';
        form.clearErrors('pin');
    }
}

function goBack(): void {
    if (step.value === 'event') {
        step.value = 'pin';

        return;
    }

    if (step.value === 'pin') {
        step.value = 'employee';
        pin.value = '';
    }
}

function resetMarking(): void {
    employeeNumber.value = '';
    pin.value = '';
    step.value = 'employee';
    activeEvent.value = '';
    form.reset();
    form.clearErrors();
}

function captureGps(): Promise<GpsPayload | null> {
    return new Promise((resolve) => {
        if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
            gpsNotice.value = 'Sin ubicación (opcional)';

            resolve(null);

            return;
        }

        let settled = false;
        const timer = window.setTimeout(() => {
            if (settled) {
                return;
            }

            settled = true;
            gpsNotice.value = 'Sin ubicación (opcional)';
            resolve(null);
        }, GPS_TIMEOUT_MS);

        navigator.geolocation.getCurrentPosition(
            (position) => {
                if (settled) {
                    return;
                }

                settled = true;
                window.clearTimeout(timer);
                gpsNotice.value = null;
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                });
            },
            () => {
                if (settled) {
                    return;
                }

                settled = true;
                window.clearTimeout(timer);
                gpsNotice.value = 'Sin ubicación (opcional)';
                resolve(null);
            },
            {
                enableHighAccuracy: true,
                timeout: GPS_TIMEOUT_MS,
                maximumAge: 30_000,
            },
        );
    });
}

async function submitEvent(eventType: KioskEventType): Promise<void> {
    if (form.processing) {
        return;
    }

    if (branchId.value === '') {
        showBanner(
            'error',
            'No hay una sucursal configurada para el checador. Pide apoyo al administrador.',
        );

        return;
    }

    if (employeeNumber.value === '' || pin.value === '') {
        showBanner('error', 'Captura tu número de empleado y tu PIN.');
        step.value = employeeNumber.value === '' ? 'employee' : 'pin';

        return;
    }

    activeEvent.value = eventType;
    const gps = await captureGps();

    form.employee_number = employeeNumber.value;
    form.pin = pin.value;
    form.branch_id = branchId.value;
    form.event_type = eventType;
    form.gps = gps;

    form.post('/kiosk/pin', {
        preserveScroll: true,
        onSuccess: (response) => {
            const flash = readFlashFromPage(response.props.flash);

            if (flash.error) {
                showBanner('error', flash.error);
            } else if (flash.success) {
                showBanner('success', flash.success);
            } else {
                showBanner(
                    'success',
                    `${attendanceEventLabel(eventType)} registrada.`,
                );
            }

            resetMarking();
        },
        onError: () => {
            showBanner(
                'error',
                'No fue posible registrar el marcaje. Revisa el número, el PIN e inténtalo de nuevo.',
            );
            pin.value = '';
            step.value = 'pin';
            form.reset('pin');
        },
        onFinish: () => {
            activeEvent.value = '';
        },
    });
}

/* Rostro (AWS Rekognition) */
const faceDialogOpen = ref(false);
const cameraState = ref<'idle' | 'starting' | 'ready' | 'error'>('idle');
const cameraError = ref<string | null>(null);
const videoRef = ref<HTMLVideoElement | null>(null);

let mediaStream: MediaStream | null = null;

const faceForm = useForm<KioskFaceFormData>({
    branch_id: branchId.value,
    event_type: 'clock_in',
    image: '',
    gps: null,
});

function stopCamera(): void {
    mediaStream?.getTracks().forEach((track) => track.stop());
    mediaStream = null;

    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }

    cameraState.value = 'idle';
}

async function startCamera(): Promise<void> {
    cameraState.value = 'starting';
    cameraError.value = null;

    if (!navigator.mediaDevices?.getUserMedia) {
        cameraState.value = 'error';
        cameraError.value =
            'Este dispositivo o navegador no permite usar la cámara. Usa el PIN.';

        return;
    }

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false,
        });

        const video = videoRef.value;

        if (video) {
            video.srcObject = mediaStream;

            try {
                await video.play();
            } catch {
                // Algunos navegadores requieren interacción previa; el video
                // sigue mostrando el flujo cuando el usuario toca la pantalla.
            }
        }

        cameraState.value = 'ready';
    } catch {
        cameraState.value = 'error';
        cameraError.value =
            'No se pudo acceder a la cámara. Revisa los permisos del navegador o registra con tu PIN.';
        stopCamera();
    }
}

async function openFaceDialog(): Promise<void> {
    if (!canUseFace.value) {
        return;
    }

    faceForm.clearErrors();
    faceForm.reset();
    faceForm.branch_id = branchId.value;
    faceForm.event_type = 'clock_in';
    faceDialogOpen.value = true;

    await nextTick();
    await startCamera();
}

function handleFaceDialogOpen(open: boolean): void {
    faceDialogOpen.value = open;

    if (!open) {
        stopCamera();
        faceForm.reset();
        faceForm.clearErrors();
    }
}

function captureFrame(): string | null {
    const video = videoRef.value;

    if (!video || video.videoWidth === 0) {
        return null;
    }

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    const context = canvas.getContext('2d');

    if (!context) {
        return null;
    }

    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', 0.85);
}

async function submitFace(): Promise<void> {
    if (faceForm.processing) {
        return;
    }

    if (branchId.value === '') {
        cameraError.value = 'No hay una sucursal configurada para el checador.';

        return;
    }

    const image = captureFrame();

    if (image === null) {
        cameraError.value =
            'La cámara aún no está lista. Espera un momento e inténtalo de nuevo.';

        return;
    }

    cameraError.value = null;
    faceForm.image = image;
    faceForm.branch_id = branchId.value;
    faceForm.gps = await captureGps();

    faceForm.post('/kiosk/face', {
        preserveScroll: true,
        onSuccess: (response) => {
            const flash = readFlashFromPage(response.props.flash);

            if (flash.error) {
                showBanner('error', flash.error);
            } else if (flash.success) {
                showBanner('success', flash.success);
            } else {
                showBanner('success', 'Marcaje facial registrado.');
            }

            handleFaceDialogOpen(false);
        },
        onError: () => {
            cameraError.value =
                'No fue posible registrar el marcaje facial. Inténtalo de nuevo o usa el PIN.';
        },
    });
}

function onGlobalKeydown(event: KeyboardEvent): void {
    if (faceDialogOpen.value) {
        return;
    }

    const target = event.target as HTMLElement | null;

    if (
        target?.closest(
            'input, textarea, select, a, [role="combobox"], [role="listbox"]',
        )
    ) {
        return;
    }

    if (event.key.length === 1 && /^[a-z0-9-]$/i.test(event.key)) {
        event.preventDefault();
        addCharacter(event.key);

        return;
    }

    if (event.key === 'Backspace') {
        event.preventDefault();
        removeDigit();

        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        goNext();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);
    clockTimer = window.setInterval(() => {
        now.value = new Date();
    }, 15_000);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
    window.clearTimeout(bannerTimer);
    window.clearInterval(clockTimer);
    stopCamera();
});
</script>

<template>
    <div class="mx-auto w-full max-w-6xl space-y-4">
        <Head title="Checador" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p
                    class="text-xs font-medium tracking-wide text-sidebar-foreground/70 uppercase"
                >
                    Checador
                </p>
                <p class="truncate text-lg font-semibold">
                    {{ branchLabel }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <p
                    class="rounded-md bg-sidebar-accent px-3 py-1.5 font-mono text-lg text-sidebar-accent-foreground tabular-nums"
                    aria-hidden="true"
                >
                    {{ currentTime }}
                </p>
                <Button
                    as-child
                    variant="outline"
                    class="bg-white/95 text-primary hover:bg-white"
                >
                    <Link href="/dashboard">Salir</Link>
                </Button>
            </div>
        </div>

        <div
            v-if="banner"
            role="status"
            aria-live="assertive"
            class="flex items-start gap-3 rounded-lg border px-4 py-3 text-base font-medium shadow-sm"
            :class="
                banner.type === 'success'
                    ? 'border-emerald-600/40 bg-emerald-50 text-emerald-900'
                    : 'border-destructive/40 bg-destructive/10 text-destructive-strong'
            "
        >
            <Check
                v-if="banner.type === 'success'"
                class="mt-0.5 size-5 shrink-0"
                aria-hidden="true"
            />
            <AlertTriangle
                v-else
                class="mt-0.5 size-5 shrink-0"
                aria-hidden="true"
            />
            <p class="min-w-0 text-pretty">{{ banner.message }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section
                class="space-y-4 rounded-xl border border-sidebar-border bg-sidebar-accent/30 p-4 sm:p-5"
                aria-labelledby="kiosk-step-title"
            >
                <div
                    v-if="branchOptions.length > 1"
                    class="grid gap-1.5 sm:max-w-xs"
                >
                    <Label for="kiosk-branch" class="text-sidebar-foreground">
                        Sucursal
                    </Label>
                    <Select v-model="branchId" :disabled="form.processing">
                        <SelectTrigger
                            id="kiosk-branch"
                            class="w-full border-sidebar-border bg-white text-primary"
                        >
                            <SelectValue placeholder="Selecciona la sucursal" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in branchOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-for="(label, index) in ['Número', 'PIN', 'Evento']"
                        :key="label"
                        class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                        :class="
                            (step === 'employee' && index === 0) ||
                            (step === 'pin' && index === 1) ||
                            (step === 'event' && index === 2)
                                ? 'bg-white text-primary'
                                : 'bg-sidebar-accent text-sidebar-accent-foreground/80'
                        "
                        :aria-current="
                            (step === 'employee' && index === 0) ||
                            (step === 'pin' && index === 1) ||
                            (step === 'event' && index === 2)
                                ? 'step'
                                : undefined
                        "
                    >
                        <span
                            class="flex size-5 items-center justify-center rounded-full text-[11px] font-semibold"
                            :class="
                                (step === 'employee' && index === 0) ||
                                (step === 'pin' && index === 1) ||
                                (step === 'event' && index === 2)
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-white/10 text-sidebar-foreground'
                            "
                            aria-hidden="true"
                        >
                            {{ index + 1 }}
                        </span>
                        {{ label }}
                    </span>
                </div>

                <div class="space-y-1">
                    <h1
                        id="kiosk-step-title"
                        class="text-xl font-semibold text-white sm:text-2xl"
                    >
                        {{ stepTitle }}
                    </h1>
                    <p class="text-sm text-sidebar-foreground/80">
                        {{ stepHint }}
                    </p>
                </div>

                <div
                    class="rounded-xl bg-white px-4 py-5 text-center shadow-sm"
                    role="group"
                    :aria-label="displayAriaLabel"
                >
                    <p
                        class="min-h-[2.5rem] font-mono text-3xl font-semibold tracking-[0.35em] break-all text-primary tabular-nums sm:text-4xl"
                        aria-hidden="true"
                    >
                        {{ displayValue || '—' }}
                    </p>
                    <p class="sr-only" aria-live="polite">
                        {{ displayAriaLabel }}
                    </p>
                </div>

                <div v-if="step === 'employee'" class="grid gap-1.5">
                    <Label
                        for="kiosk-employee-number"
                        class="text-sidebar-foreground"
                    >
                        ¿Tu número incluye letras?
                    </Label>
                    <Input
                        id="kiosk-employee-number"
                        v-model="employeeNumber"
                        type="text"
                        maxlength="30"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        placeholder="Ej. EMP-0001"
                        class="h-12 border-sidebar-border bg-white font-mono text-lg tracking-widest text-primary uppercase"
                        @keydown.enter.prevent="goNext"
                    />
                    <p class="text-xs text-sidebar-foreground/70">
                        Captúralo aquí con el teclado del dispositivo o usa el
                        teclado numérico.
                    </p>
                </div>

                <div
                    v-if="step !== 'event'"
                    class="grid grid-cols-3 gap-2 sm:gap-3"
                >
                    <button
                        v-for="digit in [
                            '1',
                            '2',
                            '3',
                            '4',
                            '5',
                            '6',
                            '7',
                            '8',
                            '9',
                        ]"
                        :key="digit"
                        type="button"
                        class="flex h-16 min-h-14 items-center justify-center rounded-xl border border-sidebar-border bg-white text-2xl font-semibold text-primary shadow-sm transition-colors hover:bg-slate-100 focus-visible:ring-4 focus-visible:ring-white/70 focus-visible:outline-none active:bg-slate-200 disabled:opacity-50"
                        :disabled="form.processing"
                        @click="addDigit(digit)"
                    >
                        {{ digit }}
                    </button>

                    <button
                        type="button"
                        class="flex h-16 min-h-14 items-center justify-center gap-1.5 rounded-xl border border-sidebar-border bg-sidebar-accent text-sm font-medium text-sidebar-accent-foreground transition-colors hover:bg-sidebar-primary focus-visible:ring-4 focus-visible:ring-white/70 focus-visible:outline-none disabled:opacity-50"
                        :disabled="form.processing"
                        aria-label="Borrar el último dígito"
                        @click="removeDigit"
                    >
                        <RotateCcw class="size-4" aria-hidden="true" />
                        Borrar
                    </button>

                    <button
                        type="button"
                        class="flex h-16 min-h-14 items-center justify-center rounded-xl border border-sidebar-border bg-white text-2xl font-semibold text-primary shadow-sm transition-colors hover:bg-slate-100 focus-visible:ring-4 focus-visible:ring-white/70 focus-visible:outline-none active:bg-slate-200 disabled:opacity-50"
                        :disabled="form.processing"
                        @click="addDigit('0')"
                    >
                        0
                    </button>

                    <button
                        type="button"
                        class="flex h-16 min-h-14 items-center justify-center rounded-xl border border-sidebar-border bg-sidebar-accent text-sm font-medium text-sidebar-accent-foreground transition-colors hover:bg-sidebar-primary focus-visible:ring-4 focus-visible:ring-white/70 focus-visible:outline-none disabled:opacity-50"
                        :disabled="form.processing"
                        aria-label="Limpiar la captura"
                        @click="clearEntry"
                    >
                        <X class="size-4" aria-hidden="true" />
                        Limpiar
                    </button>
                </div>

                <div
                    v-if="step !== 'event'"
                    class="flex flex-wrap items-center gap-2"
                >
                    <Button
                        v-if="step === 'pin'"
                        type="button"
                        variant="outline"
                        class="bg-transparent text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                        :disabled="form.processing"
                        @click="goBack"
                    >
                        Atrás
                    </Button>

                    <Button
                        type="button"
                        size="lg"
                        class="min-h-14 flex-1 bg-destructive text-base text-white hover:bg-destructive/90 sm:flex-none sm:px-8"
                        :disabled="!canGoNext || form.processing"
                        @click="goNext"
                    >
                        Continuar
                    </Button>
                </div>

                <div v-else class="space-y-3">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="event in EVENT_BUTTONS"
                            :key="event.value"
                            type="button"
                            class="flex min-h-20 flex-col items-start justify-center gap-1 rounded-xl border border-sidebar-border bg-white px-4 py-3 text-left shadow-sm transition-colors hover:bg-slate-100 focus-visible:ring-4 focus-visible:ring-white/70 focus-visible:outline-none disabled:opacity-60"
                            :disabled="form.processing"
                            @click="submitEvent(event.value)"
                        >
                            <span
                                class="flex items-center gap-2 text-base font-semibold text-primary"
                            >
                                <Spinner
                                    v-if="
                                        form.processing &&
                                        activeEvent === event.value
                                    "
                                />
                                {{ event.label }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ event.description }}
                            </span>
                        </button>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="bg-transparent text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                            :disabled="form.processing"
                            @click="goBack"
                        >
                            Atrás
                        </Button>

                        <p class="text-xs text-sidebar-foreground/70">
                            La ubicación es opcional; el marcaje se registra
                            igual.
                        </p>
                    </div>
                </div>

                <p
                    v-if="gpsNotice"
                    class="text-xs text-sidebar-foreground/70"
                    role="status"
                >
                    {{ gpsNotice }}.
                </p>

                <p
                    v-if="form.errors.employee_number || form.errors.pin"
                    class="text-sm text-red-100"
                    role="alert"
                >
                    {{
                        form.errors.employee_number ??
                        form.errors.pin ??
                        'Revisa los datos capturados.'
                    }}
                </p>
            </section>

            <aside class="space-y-4">
                <section
                    class="space-y-3 rounded-xl border border-sidebar-border bg-sidebar-accent/30 p-4"
                    aria-labelledby="kiosk-face-title"
                >
                    <h2
                        id="kiosk-face-title"
                        class="flex items-center gap-2 text-sm font-semibold text-white"
                    >
                        <ScanFace class="size-4" aria-hidden="true" />
                        Reconocimiento facial
                    </h2>

                    <p
                        v-if="!faceConfigured"
                        class="flex items-start gap-2 rounded-md border border-amber-300/40 bg-amber-50/95 px-3 py-2 text-xs text-amber-900"
                    >
                        <AlertTriangle
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            Integración no configurada — use el PIN.
                            <span v-if="face?.message" class="block">
                                {{ face.message }}
                            </span>
                        </span>
                    </p>

                    <p
                        v-else-if="!canUseFace"
                        class="text-xs text-sidebar-foreground/80"
                    >
                        Tu cuenta no tiene habilitado el marcaje facial. Usa el
                        PIN.
                    </p>

                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-12 w-full bg-white/95 text-primary hover:bg-white"
                        :disabled="!canUseFace || form.processing"
                        :aria-disabled="!canUseFace || form.processing"
                        @click="openFaceDialog"
                    >
                        <Video class="size-4" aria-hidden="true" />
                        Marcar con rostro
                    </Button>

                    <p
                        v-if="faceConfigured && face?.collection_masked"
                        class="text-[11px] break-all text-sidebar-foreground/60"
                    >
                        Colección: {{ face.collection_masked }}
                    </p>
                </section>

                <section
                    class="space-y-2 rounded-xl border border-sidebar-border bg-sidebar-accent/30 p-4"
                    aria-labelledby="kiosk-recent-title"
                >
                    <h2
                        id="kiosk-recent-title"
                        class="text-sm font-semibold text-white"
                    >
                        Últimos marcajes de hoy
                    </h2>

                    <p
                        v-if="recentEvents.length === 0"
                        class="text-xs text-sidebar-foreground/70"
                    >
                        Aún no hay marcajes registrados en esta sucursal.
                    </p>

                    <ul v-else class="space-y-2">
                        <li
                            v-for="(event, index) in recentEvents"
                            :key="`${event.occurred_at}-${index}`"
                            class="rounded-md bg-sidebar-accent px-3 py-2"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 truncate text-sm font-medium">
                                    {{ event.employee_name }}
                                </p>
                                <p
                                    class="shrink-0 font-mono text-xs text-sidebar-foreground/80 tabular-nums"
                                >
                                    {{
                                        formatTime(
                                            event.occurred_at,
                                            branchTimezone,
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-1.5"
                            >
                                <span
                                    class="rounded-full bg-white/10 px-2 py-0.5 text-[11px] text-sidebar-foreground"
                                >
                                    {{ attendanceEventLabel(event.type) }}
                                </span>
                                <span
                                    class="rounded-full bg-white/10 px-2 py-0.5 text-[11px] text-sidebar-foreground"
                                >
                                    {{ attendanceMethodLabel(event.method) }}
                                </span>
                            </div>
                        </li>
                    </ul>

                    <p
                        v-if="recentEvents.length > 0"
                        class="text-[11px] text-sidebar-foreground/60"
                    >
                        Registros más recientes:
                        {{
                            formatDateTime(
                                recentEvents[0]?.occurred_at,
                                branchTimezone,
                            )
                        }}
                    </p>
                </section>

                <p class="text-[11px] text-sidebar-foreground/60">
                    El PIN nunca se muestra ni se registra en la pantalla. Si
                    tienes problemas para marcar, avisa a recursos humanos.
                </p>
            </aside>
        </div>

        <Dialog :open="faceDialogOpen" @update:open="handleFaceDialogOpen">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Marcar con rostro</DialogTitle>
                    <DialogDescription>
                        Coloca tu rostro al centro del recuadro con buena luz.
                        Después elige el evento y confirma el marcaje.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <div
                        class="relative overflow-hidden rounded-lg border border-border bg-black"
                    >
                        <video
                            ref="videoRef"
                            class="aspect-video w-full object-cover"
                            autoplay
                            playsinline
                            muted
                        ></video>

                        <p
                            v-if="cameraState === 'starting'"
                            class="absolute inset-0 flex items-center justify-center gap-2 bg-black/60 text-sm text-white"
                        >
                            <Spinner />
                            Iniciando cámara…
                        </p>

                        <p
                            v-else-if="cameraState === 'error'"
                            class="absolute inset-0 flex items-center justify-center bg-black/70 p-4 text-center text-sm text-white"
                        >
                            Cámara no disponible
                        </p>
                    </div>

                    <p
                        v-if="cameraError"
                        class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive-strong"
                        role="alert"
                    >
                        {{ cameraError }}
                    </p>

                    <div class="grid gap-1.5">
                        <Label for="kiosk-face-event">Evento a registrar</Label>
                        <Select v-model="faceForm.event_type">
                            <SelectTrigger id="kiosk-face-event" class="w-full">
                                <SelectValue
                                    placeholder="Selecciona el evento"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="event in EVENT_BUTTONS"
                                    :key="event.value"
                                    :value="event.value"
                                >
                                    {{ event.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <p
                        v-if="
                            faceForm.errors.branch_id ||
                            faceForm.errors.event_type
                        "
                        class="text-sm text-destructive-strong"
                        role="alert"
                    >
                        {{
                            faceForm.errors.branch_id ??
                            faceForm.errors.event_type ??
                            'Revisa los datos del marcaje.'
                        }}
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="faceForm.processing"
                        @click="handleFaceDialogOpen(false)"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        :disabled="
                            cameraState !== 'ready' || faceForm.processing
                        "
                        @click="submitFace"
                    >
                        <Spinner v-if="faceForm.processing" />
                        <Video v-else class="size-4" aria-hidden="true" />
                        Registrar marcaje
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
