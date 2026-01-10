<script setup>
import { ref, watch, nextTick, computed, inject  } from 'vue'
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
const submittingConfirm = ref(false)
const submittingTerms = ref(false)
const printing = ref(false)

const fullPO = ref(null)
const company = ref(null)

/* ---- Order confirmation ---- */
const orderDate = ref('')
const signedPOFile = ref(null)

/* ---- Terms & Conditions ---- */
const termsList = ref([])
const isConfirmed = computed(() =>
    Boolean(fullPO.value?.confirmed_by)
)
/* =========================
   DERIVED
========================= */
const hasSignedPO = computed(() =>
    Boolean(fullPO.value?.signed_po_path)
)

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

        orderDate.value = fullPO.value.order_date
        signedPOFile.value = null

        if (Array.isArray(fullPO.value.terms)) {
            termsList.value = [...fullPO.value.terms]
        } else if (typeof fullPO.value.terms === 'string') {
            termsList.value = fullPO.value.terms
                .split(/\r?\n/)
                .map(t => t.replace(/^\d+\.\s*/, '').trim())
                .filter(Boolean)
        } else {
            termsList.value = []
        }


    } catch (e) {
        console.error('Failed to load PO', e)
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
   SAVE TERMS & CONDITIONS
========================= */
async function saveTerms() {
    if (!fullPO.value) return

    submittingTerms.value = true

    try {
        await axios.post(
            route('purchase-orders.update-terms', fullPO.value.uuid),
            {
                terms: termsList.value,
            }
        )

        toast?.value?.show('Terms & Conditions saved', 'success')

        await loadPO()
        emit('refresh')

    } catch (e) {
        toast?.value?.show(
            e.response?.data?.message ?? 'Failed to save T&C',
            'error'
        )
    } finally {
        submittingTerms.value = false
    }
}


/* =========================
   SAVE ORDER CONFIRMATION
========================= */
async function saveConfirmation() {
    if (!fullPO.value) return

    submittingConfirm.value = true

    try {
        const fd = new FormData()
        fd.append('order_date', orderDate.value)

        if (signedPOFile.value && !hasSignedPO.value) {
            fd.append('signed_po', signedPOFile.value)
        }

        await axios.post(
            route('purchase-orders.confirm-order', fullPO.value.uuid),
            fd,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            }
        )

        toast?.value?.show('Purchase Order confirmed', 'success')

        await loadPO()
        emit('refresh')

    } catch (e) {
        toast?.value?.show(
            e.response?.data?.message ?? 'Order confirmation failed',
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
      requestAnimationFrame(() => {
        window.print()
        printing.value = false
      })
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

<!-- HEADER -->
<div class="sticky top-0 bg-white border-b px-6 py-3 flex items-center no-print">
    <h2 class="font-semibold text-lg">
        Purchase Order — {{ fullPO?.code }}
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

<!-- BODY -->
<div class="flex h-[calc(100%-56px)] gap-6 p-6">

<!-- LEFT: A4 -->
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

<!-- RIGHT: SIDEBAR -->
<div
    v-if="fullPO"
    class="w-[360px] shrink-0 bg-white border rounded-lg p-4 space-y-6 overflow-auto no-print"
>

<!-- STATUS -->
<div>
    <div class="text-xs text-gray-500">Status</div>
    <div class="font-semibold uppercase">
        {{ fullPO.status }}
    </div>
</div>

<!-- =====================
     TERMS & CONDITIONS
===================== -->
<div v-if="!isConfirmed"
 class="space-y-3 border-b pb-4">
    <div class="flex items-center justify-between">
        <label class="text-sm font-medium">
            Terms & Conditions
        </label>
        <span class="text-xs text-gray-400">
            Numbered
        </span>
    </div>

    <div
        v-for="(term, i) in termsList"
        :key="i"
        class="flex gap-2"
    >
        <span class="text-sm text-gray-500 pt-2">
            {{ i + 1 }}.
        </span>

        <textarea
            v-model="termsList[i]"
            rows="2"
            class="flex-1 border rounded px-2 py-1 text-sm"
        />
    </div>

    <button
        class="text-xs text-indigo-600"
        @click="termsList.push('')"
    >
        + Add Term
    </button>

    <button
        class="w-full py-2 bg-gray-800 text-white rounded disabled:opacity-40"
        @click="saveTerms"
        :disabled="submittingTerms"
    >
        Save T&amp;C
    </button>
</div>

<!-- =====================
     ORDER CONFIRMATION
===================== -->
<div class="space-y-4">

    <div>
        <label class="text-xs text-gray-500">
            Order Date
        </label>
        <div class="font-medium">
            {{ fullPO.order_date }}
        </div>
    </div>

    <div class="space-y-2">
        <label class="text-xs text-gray-500">
            Signed Purchase Order
        </label>

    <div class="mt-3 p-4 rounded-lg bg-green-50 border border-green-200 text-sm space-y-3">

        <!-- HEADER -->
        <div class="flex items-center gap-2 text-green-700">
            <i class="mdi mdi-shield-check-outline text-xl"></i>
            <span class="font-semibold">
                Order Confirmed
            </span>
        </div>

        <!-- CONFIRMED BY -->
        <div class="pl-7 space-y-1">
            <div class="font-semibold text-gray-900">
                {{ fullPO.confirm_by?.name }}
            </div>

            <div class="text-xs text-gray-600">
                {{ formatDateTime(fullPO.confirmed_at) }}
            </div>
        </div>

        <!-- SIGNED DOCUMENT -->
        <div class="pl-7 pt-2 border-t border-green-200 space-y-1">
            <div class="text-xs text-gray-500">
                Signed Purchase Order
            </div>

            <a
                :href="fullPO.signed_po.url"
                target="_blank"
                class="inline-flex items-center gap-2
                    text-sm font-medium text-indigo-600 hover:underline"
            >
                <i class="mdi mdi-file-pdf-box text-lg"></i>
                View signed document
            </a>

            <div class="text-[11px] text-gray-500 flex items-center gap-1">
                <i class="mdi mdi-lock-outline"></i>
                Document is locked and cannot be replaced
            </div>
        </div>

    </div>


    </div>
</div>

</div>
</div>
</div>
</div>
</template>

<style>

</style>
