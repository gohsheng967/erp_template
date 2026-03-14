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

function signatureUrl(user) {
    if (!user) return null
    const raw = user.signature ?? user.signature_url ?? user.signature_path ?? null
    if (!raw) return null

    const value = String(raw).trim()
    if (!value) return null
    if (/^https?:\/\//i.test(value)) return value
    if (value.startsWith('data:image/')) return value
    if (value.startsWith('/storage/')) return value
    if (value.startsWith('storage/')) return `/${value}`
    return `/storage/${value.replace(/^\/+/, '')}`
}

function onSignatureImageError(event) {
    const img = event?.target
    if (!img) return
    img.classList.add('hidden')
    const fallback = img.nextElementSibling
    if (fallback) fallback.classList.remove('hidden')
}

const totalAmount = computed(() =>
    props.po.items?.reduce(
        (sum, item) => sum + Number(item.quantity) * Number(item.unit_price),
        0
    ) ?? 0
)
</script>

<template>
<div
    class="claim-print relative mx-auto min-h-[297mm] w-[210mm] border border-slate-300 bg-white p-[12mm] text-[13px] leading-relaxed text-slate-800"
>
    <div class="mb-6 flex items-start justify-between border-b-2 border-slate-300 pb-4">
        <div class="max-w-[65%]">
            <div class="text-base font-bold tracking-tight">{{ company.company_name }}</div>
            <div class="text-xs text-slate-500">Reg No: {{ company.company_reg_no }}</div>
            <div class="whitespace-pre-line text-xs text-slate-500">{{ company.address }}</div>
            <div class="text-xs text-slate-500">Tel: {{ company.office_number }}</div>
        </div>

        <div class="text-right whitespace-nowrap">
            <div class="text-lg font-bold tracking-wide text-slate-900">PURCHASE ORDER</div>
            <div class="text-sm font-semibold text-slate-700">PO No: {{ po.code }}</div>
            <div class="mt-1 text-xs">Order Date: {{ formatDate(po.order_date) }}</div>
            <div v-if="po.expected_delivery_date" class="text-xs">
                Expected Delivery: {{ formatDate(po.expected_delivery_date) }}
            </div>
        </div>
    </div>

    <div class="mb-6 rounded border border-slate-300 bg-slate-50 px-4 py-3">
        <div class="grid grid-cols-2 gap-x-12 gap-y-2">
            <div><span class="font-semibold">Supplier:</span> {{ po.supplier?.company_name ?? '-' }}</div>
            <div><span class="font-semibold">Attention:</span> {{ po.supplier?.contact_person ?? '-' }}</div>
            <div><span class="font-semibold">Address:</span> {{ po.supplier?.address ?? '-' }}</div>
            <div><span class="font-semibold">Currency:</span> MYR</div>
        </div>
    </div>

    <table class="mb-6 w-full border border-slate-400">
        <thead class="border-b-2 border-slate-400 bg-slate-100 text-slate-700">
            <tr>
                <th class="w-[28px] border border-slate-400 px-2 py-2 text-center">#</th>
                <th class="border border-slate-400 px-2 py-2 text-left">Item</th>
                <th class="border border-slate-400 px-2 py-2 text-left">Description</th>
                <th class="w-[70px] border border-slate-400 px-2 py-2 text-right">Qty</th>
                <th class="w-[100px] border border-slate-400 px-2 py-2 text-right">Unit Price</th>
                <th class="w-[110px] border border-slate-400 px-2 py-2 text-right">Amount (RM)</th>
            </tr>
        </thead>

        <tbody>
            <tr v-for="(item, index) in po.items ?? []" :key="item.id">
                <td class="border border-slate-300 px-2 py-2 text-center">{{ index + 1 }}</td>
                <td class="border border-slate-300 px-2 py-2">{{ item.item_name }}</td>
                <td class="border border-slate-300 px-2 py-2">{{ item.description ?? '-' }}</td>
                <td class="border border-slate-300 px-2 py-2 text-right">{{ item.quantity }}</td>
                <td class="border border-slate-300 px-2 py-2 text-right tabular-nums">{{ formatCurrency(item.unit_price) }}</td>
                <td class="border border-slate-300 px-2 py-2 text-right tabular-nums">{{ formatCurrency(item.quantity * item.unit_price) }}</td>
            </tr>

            <tr v-if="!po.items?.length">
                <td colspan="6" class="border border-slate-300 px-2 py-6 text-center text-slate-400">
                    No purchase items
                </td>
            </tr>
        </tbody>
    </table>

    <div class="mb-8 flex justify-end">
        <div class="w-[280px] rounded border border-slate-300 bg-slate-50 px-4 py-3">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold">Total Amount</span>
                <span class="font-bold tabular-nums">{{ formatCurrency(totalAmount) }}</span>
            </div>
        </div>
    </div>

    <div class="mb-8 rounded border border-slate-300 bg-white p-4 text-sm">
        <div class="mb-2 text-sm font-semibold tracking-wide">TERMS AND CONDITIONS</div>

        <div class="mb-3 grid grid-cols-1 gap-1 text-[13px]">
            <div><span class="font-semibold">Delivery Period:</span> {{ po.delivery_period || '-' }}</div>
            <div><span class="font-semibold">Terms of Payment:</span> {{ po.payment_terms || '-' }}</div>
            <div><span class="font-semibold">Site Contact Person:</span> {{ po.site_contact?.name || '-' }}</div>
        </div>

        <ol class="list-decimal space-y-1 pl-5">
            <li v-for="(term, i) in po.terms ?? []" :key="i">{{ term }}</li>
        </ol>

        <div v-if="!po.terms?.length" class="text-slate-400">-</div>
    </div>

    <div class="mb-8">
        <div class="mb-1 font-medium">Amount In Words</div>
        <div class="min-h-[40px] rounded border border-slate-300 px-3 py-2 whitespace-pre-wrap">
            {{ amountToWords(totalAmount) || '-' }}
        </div>
    </div>

    <div class="mt-14 grid grid-cols-2 gap-10 text-sm">
        <div>
            <div class="h-12 mb-2 flex items-end">
                <template v-if="signatureUrl(po.purchase_request?.approver)">
                    <img
                        :src="signatureUrl(po.purchase_request?.approver)"
                        alt="Authorized signature"
                        class="h-10 max-w-[160px] object-contain"
                        @error="onSignatureImageError"
                    >
                    <div class="hidden text-[11px] text-slate-400 italic">No signature</div>
                </template>
                <div v-else class="text-[11px] text-slate-400 italic">No signature</div>
            </div>
            <div class="font-medium">Authorized By</div>
            <div class="text-xs text-slate-500">{{ po.purchase_request?.approver?.name ?? '-' }}</div>
            <div class="mt-4 border-t-2 border-slate-300 pt-2 text-xs text-slate-400">
                Signature
                <br>
                Date:
            </div>
        </div>

        <div>
            <div class="h-12 mb-2 flex items-end">
                <div class="text-[11px] text-slate-400 italic">Vendor signature on stamped copy</div>
            </div>
            <div class="font-medium">Vendor Acceptance</div>
            <div class="text-xs text-slate-500">{{ po.supplier?.company_name ?? '-' }}</div>
            <div class="mt-4 border-t-2 border-slate-300 pt-2 text-xs text-slate-400">
                Signature
                <br>
                Date:
            </div>
        </div>
    </div>
</div>
</template>
