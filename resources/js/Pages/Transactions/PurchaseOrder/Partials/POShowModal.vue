<script setup>
import { ref, watch, nextTick, computed, inject } from 'vue'
import axios from 'axios'
import POShowA4 from './POShowA4.vue'
import { useFormat } from '@/Composables/useFormat'

const toast = inject('toast', null)
const { formatDateTime } = useFormat()

/* =========================
   PROPS / EMITS
========================= */
const props = defineProps({
    po: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close', 'refresh'])

/* =========================
   STATE
========================= */
const loading = ref(false)
const printing = ref(false)
const submittingConfirm = ref(false)

const fullPO = ref(null)
const company = ref(null)

/* ---- confirm form ---- */
const orderDate = ref('')
const signedPOFile = ref(null)

/* =========================
   DERIVED
========================= */
const status = computed(() => fullPO.value?.status)
const isIssued = computed(() => status.value === 'issued')
const isConfirmed = computed(() => status.value === 'confirmed')

/* =========================
   LOAD PO
========================= */
async function loadPO() {
    if (!props.po?.uuid) return

    loading.value = true
    fullPO.value = null
    company.value = null

    try {
        const res = await axios.get(
            route('purchase-orders.show', props.po.uuid)
        )

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

/* =========================
   CONFIRM ORDER
========================= */
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

/* =========================
   PRINT
========================= */
function printPage() {
    printing.value = true
    nextTick(() => {
        requestAnimationFrame(() => {
            window.print()
            printing.value = false
        })
    })
}

/* =========================
   CLOSE
========================= */
function closeModal() {
    emit('refresh')
    emit('close')
}
</script>

<template>
<div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center no-print">
<div class="bg-gray-100 w-full h-full md:h-[90vh] md:w-[90vw] rounded shadow-xl overflow-hidden">

<!-- ================= HEADER ================= -->
<div class="sticky top-0 bg-white border-b px-6 py-3 flex items-center no-print">
    <h2 class="font-semibold text-lg">
        Purchase Order — {{ fullPO?.code ?? '-' }}
    </h2>

    <div class="ml-auto flex items-center gap-3">
        <button
            class="text-sm px-3 py-1 bg-gray-200 rounded"
            @click="printPage"
        >
            Print / Save PDF
        </button>

        <button
            @click="closeModal"
            class="text-red-600"
        >
            <i class="mdi mdi-close text-xl"></i>
        </button>
    </div>
</div>

<!-- ================= BODY ================= -->
<div class="flex h-[calc(100%-56px)] gap-6 p-6">

<!-- ================= LEFT : A4 ================= -->
<div class="flex-1 overflow-auto bg-gray-100">
    <div v-if="loading" class="text-center py-24 text-gray-400">
        Loading purchase order…
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

<!-- ================= RIGHT : SIDEBAR ================= -->
<div
    v-if="fullPO"
    class="w-[360px] shrink-0 bg-white border rounded-lg p-4 space-y-6 overflow-auto no-print"
>

<!-- ================= STATUS ================= -->
<div class="space-y-3">

    <!-- DRAFT -->
    <div
        v-if="status === 'draft'"
        class="p-4 rounded-lg bg-gray-100 border"
    >
        <div class="flex items-center gap-2 text-gray-600">
            <i class="mdi mdi-pencil-outline text-xl"></i>
            <span class="font-semibold">Draft</span>
        </div>
    </div>

    <!-- ISSUED -->
    <div
        v-else-if="status === 'issued'"
        class="p-4 rounded-lg bg-blue-50 border border-blue-200 space-y-3"
    >
        <div class="flex items-center gap-2 text-blue-700">
            <i class="mdi mdi-truck-fast-outline text-xl"></i>
            <span class="font-semibold">Purchase Order Issued</span>
        </div>

        <div class="text-xs text-gray-600">
            Awaiting confirmation with signed purchase order.
        </div>
    </div>

    <!-- CONFIRMED -->
    <div
        v-else-if="status === 'confirmed'"
        class="p-4 rounded-lg bg-green-50 border border-green-200 space-y-3"
    >
        <div class="flex items-center gap-2 text-green-700">
            <i class="mdi mdi-shield-check-outline text-xl"></i>
            <span class="font-semibold">Order Confirmed</span>
        </div>

        <div class="text-sm">
            <div class="font-medium">
                {{ fullPO.confirm_by?.name }}
            </div>
            <div class="text-xs text-gray-600">
                {{ formatDateTime(fullPO.confirmed_at) }}
            </div>
        </div>

        <a
            :href="fullPO.signed_po.url"
            target="_blank"
            class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:underline"
        >
            <i class="mdi mdi-file-pdf-box text-lg"></i>
            View signed PO
        </a>

        <div class="text-[11px] text-gray-500 flex items-center gap-1">
            <i class="mdi mdi-lock-outline"></i>
            Document is locked
        </div>
    </div>

</div>

<!-- ================= CONFIRM FORM ================= -->
<div
    v-if="isIssued"
    class="border-t pt-4 space-y-4"
>
    <div class="text-sm font-semibold">
        Confirm Purchase Order
    </div>

    <div>
        <label class="text-xs text-gray-500">
            Order Date
        </label>
        <input
            type="date"
            v-model="orderDate"
            class="w-full border rounded px-2 py-1 text-sm"
        />
    </div>

    <div>
        <label class="text-xs text-gray-500">
            Signed Purchase Order
        </label>
        <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            @change="e => signedPOFile = e.target.files[0]"
            class="w-full text-sm"
        />
    </div>

    <button
        @click="confirmOrder"
        :disabled="submittingConfirm || !orderDate"
        class="w-full py-2 bg-green-600 text-white rounded
               disabled:opacity-40"
    >
        Confirm Order
    </button>
</div>

</div>
</div>
</div>
</div>
</template>
