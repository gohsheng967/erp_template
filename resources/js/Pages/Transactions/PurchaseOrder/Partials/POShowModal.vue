<script setup>
import { ref, watch, nextTick, computed, inject } from 'vue'
import axios from 'axios'
import POShowA4 from './POShowA4.vue'
import { useFormat } from '@/Composables/useFormat'

const toast = inject('toast', null)
const { formatDateTime } = useFormat()

const props = defineProps({
    po: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close', 'refresh'])

const loading = ref(false)
const printing = ref(false)
const submittingConfirm = ref(false)

const fullPO = ref(null)
const company = ref(null)

const orderDate = ref('')
const signedPOFile = ref(null)

const status = computed(() => fullPO.value?.status)
const isIssued = computed(() => status.value === 'issued')
const isConfirmed = computed(() => status.value === 'confirmed')

const statusLabel = computed(() => {
    if (status.value === 'confirmed') return 'Confirmed'
    if (status.value === 'issued') return 'Issued'
    return 'Draft'
})

const statusPillClass = computed(() => {
    if (status.value === 'confirmed') return 'bg-emerald-100 text-emerald-700 border-emerald-200'
    if (status.value === 'issued') return 'bg-amber-100 text-amber-700 border-amber-200'
    return 'bg-slate-100 text-slate-700 border-slate-200'
})

async function loadPO() {
    if (!props.po?.uuid) return

    loading.value = true
    fullPO.value = null
    company.value = null

    try {
        const res = await axios.get(route('purchase-orders.show', props.po.uuid))

        fullPO.value = res.data.po
        company.value = res.data.company ?? null

        orderDate.value = fullPO.value.order_date ?? ''
    } catch (e) {
        toast?.value?.show('Failed to load purchase order', 'error')
    } finally {
        loading.value = false
    }
}

watch(
    () => props.po.uuid,
    loadPO,
    { immediate: true }
)

async function confirmOrder() {
    if (!fullPO.value) return

    submittingConfirm.value = true

    try {
        const fd = new FormData()
        fd.append('order_date', orderDate.value)

        if (signedPOFile.value) {
            fd.append('signed_po', signedPOFile.value)
        }

        await axios.post(
            route('purchase-orders.confirm-order', fullPO.value.uuid),
            fd,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        )

        toast?.value?.show('Purchase Order confirmed', 'success')

        await loadPO()
        emit('refresh')
    } catch (e) {
        toast?.value?.show(
            e.response?.data?.message ?? 'Confirmation failed',
            'error'
        )
    } finally {
        submittingConfirm.value = false
    }
}

function printPage() {
    printing.value = true
    nextTick(() => {
        requestAnimationFrame(() => {
            window.print()
            printing.value = false
        })
    })
}

function closeModal() {
    emit('refresh')
    emit('close')
}
</script>

<template>
<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/55 p-4 no-print">
<div class="h-full w-full overflow-hidden rounded-xl bg-slate-100 shadow-2xl ring-1 ring-slate-200 md:h-[92vh] md:w-[95vw]">

<div class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white/95 px-6 py-4 backdrop-blur no-print">
    <div>
        <h2 class="text-lg font-semibold tracking-tight text-slate-800">
            Purchase Order {{ fullPO?.code ?? '-' }}
        </h2>
        <div class="text-xs text-slate-500">
            Professional preview and issuance controls
        </div>
    </div>

    <span
        class="ml-auto inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
        :class="statusPillClass"
    >
        {{ statusLabel }}
    </span>

    <div class="flex items-center gap-2">
        <button
            class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            @click="printPage"
        >
            <i class="mdi mdi-printer-outline text-base"></i>
            Print / Save PDF
        </button>

        <button
            @click="closeModal"
            class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
        >
            <i class="mdi mdi-close text-xl leading-none"></i>
        </button>
    </div>
</div>

<div class="flex h-[calc(100%-72px)] gap-6 p-5 md:p-6">

<div class="flex-1 overflow-auto rounded-lg border border-slate-200 bg-slate-200/60 p-3 md:p-4">
    <div v-if="loading" class="py-24 text-center text-slate-500">
        Loading purchase order...
    </div>

    <POShowA4
        v-if="fullPO && !printing"
        :po="fullPO"
        :company="company"
    />

    <Teleport to="body">
        <POShowA4
            v-if="fullPO && printing"
            :po="fullPO"
            :company="company"
        />
    </Teleport>
</div>

<div
    v-if="fullPO"
    class="w-[380px] shrink-0 space-y-4 overflow-auto no-print"
>

<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
        Status
    </div>

    <div
        v-if="status === 'draft'"
        class="rounded-lg border border-slate-200 bg-slate-50 p-3"
    >
        <div class="flex items-center gap-2 text-slate-600">
            <i class="mdi mdi-pencil-outline text-xl"></i>
            <span class="font-semibold">Draft</span>
        </div>
    </div>

    <div
        v-else-if="status === 'issued'"
        class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-3"
    >
        <div class="flex items-center gap-2 text-amber-700">
            <i class="mdi mdi-truck-fast-outline text-xl"></i>
            <span class="font-semibold">Purchase Order Issued</span>
        </div>

        <div class="text-xs text-slate-600">
            Awaiting confirmation with signed purchase order.
        </div>
    </div>

    <div
        v-else-if="status === 'confirmed'"
        class="space-y-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3"
    >
        <div class="flex items-center gap-2 text-emerald-700">
            <i class="mdi mdi-shield-check-outline text-xl"></i>
            <span class="font-semibold">Order Confirmed</span>
        </div>

        <div class="text-sm">
            <div class="font-medium">
                {{ fullPO.confirm_by?.name }}
            </div>
            <div class="text-xs text-slate-600">
                {{ formatDateTime(fullPO.confirmed_at) }}
            </div>
        </div>

        <a
            :href="fullPO.signed_po.url"
            target="_blank"
            class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:underline"
        >
            <i class="mdi mdi-file-pdf-box text-lg"></i>
            View signed PO
        </a>

        <div class="flex items-center gap-1 text-[11px] text-slate-500">
            <i class="mdi mdi-lock-outline"></i>
            Document is locked
        </div>
    </div>
</div>

<div
    v-if="isIssued"
    class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
>
    <div class="text-sm font-semibold text-slate-800">
        Confirm Purchase Order
    </div>

    <div>
        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
            Order Date
        </label>
        <input
            type="date"
            v-model="orderDate"
            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
        />
    </div>

    <div>
        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
            Signed Purchase Order
        </label>
        <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            @change="e => signedPOFile = e.target.files[0]"
            class="w-full rounded-md border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded file:border-0 file:bg-indigo-100 file:px-2 file:py-1 file:text-indigo-700 hover:border-indigo-300"
        />
    </div>

    <button
        @click="confirmOrder"
        :disabled="submittingConfirm || !orderDate"
        class="w-full rounded-md bg-emerald-600 py-2 font-medium text-white transition hover:bg-emerald-700 disabled:opacity-40"
    >
        Confirm Order
    </button>
</div>

</div>
</div>
</div>
</div>
</template>
