<script setup>
import { router } from '@inertiajs/vue3'
import { useFormat } from '@/Composables/useFormat'

const props = defineProps({
    invoices: {
        type: Object,
        required: true,
    },
})

const { formatCurrency, formatDate } = useFormat()

function viewInvoice(invoice) {
    router.visit(route('ap-invoices.show', invoice.uuid))
}
</script>

<template>
    <div class="bg-white border rounded-xl overflow-x-auto">

        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left">Invoice No</th>
                    <th class="px-4 py-3 text-left">PO No</th>
                    <th class="px-4 py-3 text-center">Invoice Date</th>
                    <th class="px-4 py-3 text-right">Invoice Amount</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Action</th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="invoice in invoices.data"
                    :key="invoice.uuid"
                    class="border-t hover:bg-gray-50"
                >
                    <!-- INVOICE -->
                    <td class="px-4 py-2 font-medium text-indigo-600">
                        {{ invoice.invoice_number }}
                    </td>

                    <!-- PO -->
                    <td class="px-4 py-2">
                        {{ invoice.purchase_order?.code ?? '-' }}
                    </td>

                    <!-- DATE -->
                    <td class="px-4 py-2 text-center">
                        {{ formatDate(invoice.invoice_date) }}
                    </td>

                    <!-- AMOUNT -->
                    <td class="px-4 py-2 text-right">
                        {{ formatCurrency(invoice.invoice_amount) }}
                    </td>

                    <!-- BALANCE -->
                    <td class="px-4 py-2 text-right font-semibold">
                        {{ formatCurrency(invoice.balance_amount) }}
                    </td>

                    <!-- STATUS -->
                    <td class="px-4 py-2 text-center">
                        <span
                            class="px-2 py-0.5 text-xs rounded-full capitalize"
                            :class="{
                                'bg-amber-100 text-amber-700': invoice.status === 'confirmed',
                                'bg-blue-100 text-blue-700': invoice.status === 'partially_paid',
                                'bg-green-100 text-green-700': invoice.status === 'paid',
                                'bg-red-100 text-red-700': invoice.status === 'cancelled',
                            }"
                        >
                            {{ invoice.status.replace('_', ' ') }}
                        </span>
                    </td>

                    <!-- ACTION -->
                    <td class="px-4 py-2 text-center">
                        <button
                            class="text-indigo-600 hover:text-indigo-800"
                            title="View invoice"
                            @click="viewInvoice(invoice)"
                        >
                            <i class="mdi mdi-eye-outline text-lg"></i>
                        </button>
                    </td>
                </tr>

                <!-- EMPTY -->
                <tr v-if="!invoices.data.length">
                    <td colspan="7" class="py-6 text-center text-gray-400">
                        No invoices found for this supplier
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- PAGINATION -->
        <div
            v-if="invoices.links?.length"
            class="p-3 flex gap-1 flex-wrap border-t"
        >
            <button
                v-for="link in invoices.links"
                :key="link.label"
                :disabled="!link.url"
                v-html="link.label"
                class="px-3 py-1 border rounded text-sm"
                :class="{
                    'text-gray-400 cursor-not-allowed': !link.url,
                    'bg-gray-100': link.active
                }"
                @click="link.url && router.visit(link.url, { preserveScroll: true })"
            />
        </div>

    </div>
</template>
