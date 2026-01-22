<script setup>
import { computed } from 'vue'
import { amountToWords } from '@/helpers/string'

const props = defineProps({
    po: {
        type: Object,
        required: true,
    },
    company: {
        type: Object,
        required: true,
    },
})

/* =====================
   HELPERS
===================== */
function formatDate(date) {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-GB')
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-MY', {
        style: 'currency',
        currency: 'MYR',
        minimumFractionDigits: 2,
    }).format(value ?? 0)
}

/* =====================
   TOTAL
===================== */
const totalAmount = computed(() =>
    props.po.items?.reduce(
        (sum, item) =>
            sum + Number(item.quantity) * Number(item.unit_price),
        0
    ) ?? 0
)
</script>

<template>
<!-- =====================
     A4 PRINT ROOT
===================== -->
<div
    class="claim-print relative bg-white text-gray-800 mx-auto
           w-[210mm] min-h-[297mm]
           p-[12mm] text-[13px] leading-relaxed"
>

    <!-- =====================
         HEADER
    ====================== -->
    <div class="flex justify-between items-start border-b pb-4 mb-6 relative z-10">

        <!-- COMPANY INFO -->
        <div class="flex gap-3 max-w-[65%]">
            <div>
                <div class="font-semibold text-base">
                    {{ company.company_name }}
                </div>
                <div class="text-xs text-gray-500">
                    Reg No: {{ company.company_reg_no }}
                </div>
                <div class="text-xs text-gray-500 whitespace-pre-line">
                    {{ company.address }}
                </div>
                <div class="text-xs text-gray-500">
                    Tel: {{ company.office_number }}
                </div>
            </div>
        </div>

        <!-- DOCUMENT META -->
        <div class="text-right whitespace-nowrap">
            <div class="text-lg font-bold tracking-wide">
                PURCHASE ORDER
            </div>
            <div class="text-sm">
                PO No: {{ po.code }}
            </div>
            <div class="text-xs mt-1">
                Order Date: {{ formatDate(po.order_date) }}
            </div>
            <div
                v-if="po.expected_delivery_date"
                class="text-xs"
            >
                Expected Delivery:
                {{ formatDate(po.expected_delivery_date) }}
            </div>
        </div>
    </div>

    <!-- =====================
         SUPPLIER INFO
    ====================== -->
    <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-6 relative z-10">
        <div>
            <span class="font-medium">Supplier</span>:
            {{ po.supplier?.company_name ?? '-' }}
        </div>

        <div>
            <span class="font-medium">Attention</span>:
            {{ po.supplier?.contact_person ?? '-' }}
        </div>

        <div>
            <span class="font-medium">Address</span>:
            {{ po.supplier?.address ?? '-' }}
        </div>

        <div>
            <span class="font-medium">Currency</span>: MYR
        </div>
    </div>

    <!-- =====================
         ITEMS TABLE
    ====================== -->
    <table class="w-full border border-gray-400 mb-6 relative z-10">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-2 py-2 w-[28px] text-center">#</th>
                <th class="border px-2 py-2 text-left">Item</th>
                <th class="border px-2 py-2 text-left">Description</th>
                <th class="border px-2 py-2 w-[70px] text-right">Qty</th>
                <th class="border px-2 py-2 w-[100px] text-right">Unit Price</th>
                <th class="border px-2 py-2 w-[110px] text-right">
                    Amount (RM)
                </th>
            </tr>
        </thead>

        <tbody>
            <tr
                v-for="(item, index) in po.items ?? []"
                :key="item.id"
            >
                <td class="border px-2 py-2 text-center">
                    {{ index + 1 }}
                </td>
                <td class="border px-2 py-2">
                    {{ item.item_name }}
                </td>
                <td class="border px-2 py-2">
                    {{ item.description ?? '-' }}
                </td>
                <td class="border px-2 py-2 text-right">
                    {{ item.quantity }}
                </td>
                <td class="border px-2 py-2 text-right tabular-nums">
                    {{ formatCurrency(item.unit_price) }}
                </td>
                <td class="border px-2 py-2 text-right tabular-nums">
                    {{ formatCurrency(item.quantity * item.unit_price) }}
                </td>
            </tr>

            <tr v-if="!po.items?.length">
                <td
                    colspan="6"
                    class="border px-2 py-6 text-center text-gray-400"
                >
                    No purchase items
                </td>
            </tr>
        </tbody>
    </table>

    <!-- =====================
         TOTAL
    ====================== -->
    <div class="flex justify-end mb-10 relative z-10">
        <div class="w-[260px] border-t pt-3">
            <div class="flex justify-between">
                <span class="font-medium">Total Amount</span>
                <span class="font-bold tabular-nums">
                    {{ formatCurrency(totalAmount) }}
                </span>
            </div>
        </div>
    </div>

    <!-- =====================
         TERMS
    ====================== -->
    <div class="mb-10 text-sm">
        <div class="font-semibold mb-1">
            Terms & Conditions
        </div>

        <ol class="list-decimal pl-5 space-y-1">
            <li
                v-for="(term, i) in po.terms ?? []"
                :key="i"
            >
                {{ term }}
            </li>
        </ol>

        <div
            v-if="!po.terms?.length"
            class="text-gray-400"
        >
            -
        </div>
    </div>

    <!-- =====================
         AMOUNT IN WORDS
    ====================== -->
    <div class="mb-10 relative z-10">
        <div class="font-medium mb-1">Amount In Words</div>
        <div class="border px-3 py-2 min-h-[40px] whitespace-pre-wrap">
            {{ amountToWords(totalAmount) || '-' }}
        </div>
    </div>

    <!-- =====================
         SIGNATURES
    ====================== -->
    <div class="grid grid-cols-2 gap-10 mt-16 text-sm relative z-10">
        <!-- AUTHORIZED BY -->
        <div>
            <div class="mb-8"></div>
            <div class="font-medium">Authorized By</div>
            <div class="text-xs text-gray-500">
                {{ po.purchase_request?.approver?.name ?? '-' }}
            </div>
            <div class="mt-12 border-t pt-2 text-xs text-gray-400">
                Signature
                <br>
                Date:
            </div>
        </div>

        <!-- VENDOR ACCEPTANCE -->
        <div>
            <div class="mb-8"></div>
            <div class="font-medium">Vendor Acceptance</div>
            <div class="text-xs text-gray-500">
                {{ po.supplier?.company_name ?? '-' }}
            </div>
            <div class="mt-12 border-t pt-2 text-xs text-gray-400">
                Signature
                <br>
                Date:
            </div>
            
        </div>
    </div>
</div>
</template>
