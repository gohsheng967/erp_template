<script setup>
import { ref, inject, onMounted, watch } from 'vue'
import axios from 'axios'
import { useFormat } from '@/Composables/useFormat'

const toast = inject('toast', null)
const { formatCurrency } = useFormat()

/* ===============================
   PROPS
================================ */
const props = defineProps({
    projectId: {
        type: [Number, String],
        required: true,
    },
})

/* ===============================
   STATE
================================ */
const loading = ref(true)

const summary = ref({
    draft: 0,
    issued: 0,
    received: 0,
    total_amount: 0,
    received_amount: 0,
})

const invoices = ref([])

/* ===============================
   LOAD SUMMARY
================================ */
async function loadInvoices() {
    if (!props.projectId) return

    loading.value = true

    try {
        const res = await axios.get(
            route('projects.ar.summary', props.projectId)
        )

        summary.value = res.data.summary
        invoices.value = res.data.invoices

    } catch (e) {
        console.error(e)
        toast?.value?.show('Failed to load AR invoices', 'error')
    } finally {
        loading.value = false
    }
}

onMounted(loadInvoices)
watch(() => props.projectId, loadInvoices)
</script>

<template>
<div class="space-y-8">

    <!-- ===============================
         HEADER / CREATE
    =============================== -->
    <div class="bg-gray-50 rounded-lg p-4 border flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-gray-700">
                Accounts Receivable
            </h3>
            <p class="text-sm text-gray-500">
                Issued invoices for this project
            </p>
        </div>

        <a
            :href="route('ar-invoices.create', { project_id: projectId })"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md
                   hover:bg-indigo-700"
        >
            New Invoice
        </a>
    </div>

    <!-- ===============================
         LOADING
    =============================== -->
    <div v-if="loading" class="text-sm text-gray-400">
        Loading invoice summary…
    </div>

    <!-- ===============================
         SUMMARY
    =============================== -->
    <div v-else class="grid grid-cols-1 md:grid-cols-4 gap-4">

        <div class="bg-slate-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">Draft</div>
            <div class="text-xl font-semibold">
                {{ summary.draft }}
            </div>
        </div>

        <div class="bg-yellow-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">Issued</div>
            <div class="text-xl font-semibold text-yellow-700">
                {{ summary.issued }}
            </div>
        </div>

        <div class="bg-green-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">Received</div>
            <div class="text-xl font-semibold text-green-700">
                {{ summary.received }}
            </div>
        </div>

        <div class="bg-indigo-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">Total Invoiced</div>
            <div class="text-xl font-semibold text-indigo-700">
                {{ formatCurrency(summary.total_amount) }}
            </div>
        </div>

    </div>

    <!-- ===============================
         RECENT INVOICES
    =============================== -->
    <div v-if="!loading">
        <h3 class="font-semibold text-gray-700 mb-2">
            Recent Invoices
        </h3>

        <table class="min-w-full text-sm divide-y divide-gray-200 bg-white rounded-lg shadow">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Invoice No</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-right">Amount</th>
                    <th class="px-3 py-2 text-left">Date</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
                <tr
                    v-for="inv in invoices"
                    :key="inv.id"
                    class="hover:bg-gray-50"
                >
                    <td class="px-3 py-2 font-medium">
                        {{ inv.invoice_no ?? '-' }}
                    </td>

                    <td class="px-3 py-2 capitalize font-semibold">
                        <span
                            :class="{
                                'text-gray-600': inv.status === 'draft',
                                'text-yellow-600': inv.status === 'issued',
                                'text-green-600': inv.status === 'received',
                                'text-red-600': inv.status === 'cancelled',
                            }"
                        >
                            {{ inv.status }}
                        </span>
                    </td>

                    <td class="px-3 py-2 text-right">
                        {{ formatCurrency(inv.total_amount) }}
                    </td>

                    <td class="px-3 py-2">
                        {{ inv.issued_at ?? inv.created_at }}
                    </td>
                </tr>

                <tr v-if="!invoices.length">
                    <td colspan="4"
                        class="px-3 py-6 text-center text-gray-400">
                        No invoices yet
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ===============================
         LINK
    =============================== -->
    <div class="text-right">
        <a
            :href="route('ar-invoices.index', { project_id: projectId })"
            class="text-sm text-indigo-600 hover:underline font-medium"
        >
            View all invoices →
        </a>
    </div>

</div>
</template>
