<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import PrintLayout from '@/layouts/print/PrintLayout.vue';
import { formatDate, formatDays, formatMoney } from '@/lib/format';
import { payrollConceptTypeLabel } from '@/lib/labels';
import type { PayrollReceiptProps, PayrollSignatureLine } from '@/types';

type SignatureView = {
    label: string;
    caption: string;
};

const props = defineProps<PayrollReceiptProps>();

defineOptions({
    layout: PrintLayout,
});

/** D-14: leyenda permanente, aunque el backend aún no envíe `notice`. */
const DEFAULT_NOTICE =
    'Documento preliminar; no constituye CFDI ni cálculo fiscal definitivo.';

const noticeText = computed(() => props.notice?.trim() || DEFAULT_NOTICE);

/** El backend serializa el enum como `earning`/`Earning`; se tolera ambos. */
function isEarning(type: string): boolean {
    return type.replace(/[^a-z]/gi, '').toLowerCase() === 'earning';
}

const companyName = computed(
    () => props.company?.name?.trim() || 'Diseño Intelectual',
);

const companyAddress = computed(() => props.company?.address?.trim() || null);

const logoUrl = computed(
    () => props.company?.logo_url?.trim() || '/images/logo.jpeg',
);

function toSignatureView(
    line: PayrollSignatureLine,
    index: number,
): SignatureView {
    if (typeof line === 'string') {
        const label = line.trim();

        return {
            label: label === '' ? `Firma ${index + 1}` : label,
            caption: '',
        };
    }

    return {
        label: line.label?.trim() || `Firma ${index + 1}`,
        caption: line.caption?.trim() || line.name?.trim() || '',
    };
}

const signatureLines = computed<SignatureView[]>(() => {
    const raw = props.signature_lines;

    if (!Array.isArray(raw) || raw.length === 0) {
        return [
            {
                label: 'Firma del colaborador',
                caption: props.item.employee.full_name,
            },
            {
                label: 'Firma de quien autoriza',
                caption: companyName.value,
            },
        ];
    }

    return raw.map(toSignatureView);
});

function printReceipt(): void {
    window.print();
}

function goBack(): void {
    if (window.history.length > 1) {
        window.history.back();
    }
}
</script>

<template>
    <div class="space-y-6">
        <Head :title="`Recibo de ${props.item.employee.full_name}`" />

        <div class="flex flex-wrap items-center justify-end gap-2 print:hidden">
            <Button type="button" variant="outline" @click="goBack">
                <ArrowLeft aria-hidden="true" />
                Volver
            </Button>
            <Button type="button" @click="printReceipt">
                <Printer aria-hidden="true" />
                Imprimir / Guardar como PDF
            </Button>
        </div>

        <article
            aria-labelledby="receipt-title"
            class="rounded-lg border border-neutral-300 bg-white p-6 text-neutral-900 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none"
        >
            <header
                class="flex flex-col gap-4 border-b border-neutral-300 pb-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <img
                        :src="logoUrl"
                        :alt="companyName"
                        width="209"
                        height="230"
                        decoding="async"
                        class="h-16 w-auto max-w-24 object-contain"
                    />
                    <div class="min-w-0">
                        <p class="text-base font-semibold text-neutral-900">
                            {{ companyName }}
                        </p>
                        <p
                            v-if="companyAddress"
                            class="text-xs text-neutral-600"
                        >
                            {{ companyAddress }}
                        </p>
                    </div>
                </div>

                <div class="sm:text-right">
                    <h1
                        id="receipt-title"
                        class="text-lg font-semibold tracking-tight text-neutral-900"
                    >
                        Recibo de pre-nómina
                    </h1>
                    <p class="text-xs text-neutral-600">
                        Documento preliminar · Control interno
                    </p>
                </div>
            </header>

            <section
                aria-label="Datos del colaborador y del periodo"
                class="mt-4 grid gap-4 sm:grid-cols-2"
            >
                <div>
                    <h2
                        class="text-xs font-semibold tracking-wide text-neutral-500 uppercase"
                    >
                        Colaborador
                    </h2>
                    <dl class="mt-2 space-y-1 text-sm">
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-neutral-600">Nombre:</dt>
                            <dd class="font-medium text-neutral-900">
                                {{ props.item.employee.full_name }}
                            </dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-neutral-600">Número:</dt>
                            <dd class="text-neutral-900">
                                {{ props.item.employee.employee_number }}
                            </dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-neutral-600">Puesto:</dt>
                            <dd class="text-neutral-900">
                                {{ props.item.employee.position ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-neutral-600">Sucursal:</dt>
                            <dd class="text-neutral-900">
                                {{ props.item.branch?.name ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h2
                        class="text-xs font-semibold tracking-wide text-neutral-500 uppercase"
                    >
                        Periodo
                    </h2>
                    <dl class="mt-2 space-y-1 text-sm">
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-neutral-600">Del:</dt>
                            <dd class="text-neutral-900">
                                {{ formatDate(props.item.period.start_date) }}
                            </dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-neutral-600">Al:</dt>
                            <dd class="text-neutral-900">
                                {{ formatDate(props.item.period.end_date) }}
                            </dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-neutral-600">Día de pago:</dt>
                            <dd class="font-medium text-neutral-900">
                                {{ formatDate(props.item.period.payment_date) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="mt-6" aria-labelledby="receipt-concepts">
                <h2
                    id="receipt-concepts"
                    class="text-xs font-semibold tracking-wide text-neutral-500 uppercase"
                >
                    Conceptos
                </h2>

                <div class="mt-2 overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <caption class="sr-only">
                            Conceptos de percepción y deducción del recibo
                        </caption>
                        <thead>
                            <tr
                                class="border-y border-neutral-300 bg-neutral-100 text-left text-xs font-semibold tracking-wide text-neutral-700 uppercase"
                            >
                                <th scope="col" class="px-2 py-2">Código</th>
                                <th scope="col" class="px-2 py-2">Concepto</th>
                                <th scope="col" class="px-2 py-2">Tipo</th>
                                <th scope="col" class="px-2 py-2 text-right">
                                    Cantidad
                                </th>
                                <th scope="col" class="px-2 py-2 text-right">
                                    Importe unitario
                                </th>
                                <th scope="col" class="px-2 py-2 text-right">
                                    Importe
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(concept, index) in props.item.concepts"
                                :key="`${concept.code}-${index}`"
                                class="border-b border-neutral-200 align-top"
                            >
                                <td
                                    class="px-2 py-2 font-mono text-xs whitespace-nowrap text-neutral-700"
                                >
                                    {{ concept.code }}
                                </td>
                                <td class="px-2 py-2 text-neutral-900">
                                    {{ concept.name }}
                                </td>
                                <td class="px-2 py-2 whitespace-nowrap">
                                    <span
                                        :class="
                                            isEarning(concept.type)
                                                ? 'text-emerald-700'
                                                : 'text-amber-700'
                                        "
                                    >
                                        {{
                                            payrollConceptTypeLabel(
                                                concept.type,
                                            )
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-2 py-2 text-right whitespace-nowrap text-neutral-900 tabular-nums"
                                >
                                    {{ formatDays(concept.quantity) }}
                                </td>
                                <td
                                    class="px-2 py-2 text-right whitespace-nowrap text-neutral-900 tabular-nums"
                                >
                                    {{ formatMoney(concept.unit_amount) }}
                                </td>
                                <td
                                    class="px-2 py-2 text-right font-medium whitespace-nowrap text-neutral-900 tabular-nums"
                                >
                                    {{ formatMoney(concept.amount) }}
                                </td>
                            </tr>

                            <tr v-if="props.item.concepts.length === 0">
                                <td
                                    colspan="6"
                                    class="px-2 py-3 text-center text-sm text-neutral-600"
                                >
                                    Sin conceptos calculados en este periodo.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="mt-4 flex justify-end"
                aria-label="Totales del recibo"
            >
                <dl class="w-full max-w-xs space-y-1 text-sm">
                    <div class="flex items-center justify-between gap-6">
                        <dt class="text-neutral-600">Percepciones</dt>
                        <dd class="text-neutral-900 tabular-nums">
                            {{
                                formatMoney(props.item.totals?.earnings ?? null)
                            }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-6">
                        <dt class="text-neutral-600">Descuentos</dt>
                        <dd class="text-neutral-900 tabular-nums">
                            {{
                                formatMoney(
                                    props.item.totals?.deductions ?? null,
                                )
                            }}
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-6 border-t border-neutral-400 pt-2 text-base font-semibold"
                    >
                        <dt>Neto a pagar</dt>
                        <dd class="tabular-nums">
                            {{ formatMoney(props.item.totals?.net ?? null) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                aria-label="Firmas"
                class="mt-10 grid break-inside-avoid gap-8 sm:grid-cols-2"
            >
                <div
                    v-for="(line, index) in signatureLines"
                    :key="`${line.label}-${index}`"
                    class="pt-8"
                >
                    <div class="border-t border-neutral-400 pt-2 text-center">
                        <p class="text-sm font-medium text-neutral-900">
                            {{ line.label }}
                        </p>
                        <p v-if="line.caption" class="text-xs text-neutral-600">
                            {{ line.caption }}
                        </p>
                    </div>
                </div>
            </section>

            <p
                class="mt-6 border-t border-neutral-300 pt-3 text-xs text-neutral-600"
                role="note"
            >
                {{ noticeText }}
            </p>
        </article>
    </div>
</template>
