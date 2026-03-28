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

function formatTermsText(value) {
    const raw = String(value ?? '').replace(/\r\n/g, '\n').trim()
    if (!raw) return '-'

    // Normalize numbered items like "1.Text" into "1. Text"
    return raw.replace(/(^|\n)\s*(\d+)\.(\S)/g, '$1$2. $3')
}

function formatTermsList(value) {
    const normalized = formatTermsText(value)
    if (normalized === '-') return []

    return normalized
        .split('\n')
        .map((line) => line.replace(/^\s*\d+\.\s*/, '').trim())
        .filter(Boolean)
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

const totalAmount = computed(() => {
    const poItems = Array.isArray(props.po.items) ? props.po.items : []
    if (!poItems.length) return 0

    const prItems = Array.isArray(props.po.purchase_request?.items)
        ? props.po.purchase_request.items
        : []
    const prById = new Map(prItems.map((item) => [Number(item.id), item]))
    const prParentIds = new Set(
        prItems
            .map((item) => Number(item.parent_id ?? 0))
            .filter((id) => id > 0)
    )

    return poItems.reduce((sum, item) => {
        const prItemId = Number(item.purchase_request_item_id ?? 0)
        const prItem = prById.get(prItemId)
        const isParentWithChildren = prItem ? prParentIds.has(Number(prItem.id)) : false
        if (isParentWithChildren) return sum

        return sum + Number(item.quantity) * Number(item.unit_price)
    }, 0)
})

const paymentTermsList = computed(() => formatTermsList(props.po.payment_terms))
const stageMap = computed(() => {
    const logs = Array.isArray(props.po.purchase_request?.remark_log)
        ? props.po.purchase_request.remark_log
        : []
    const map = {}
    for (const row of logs) {
        if (row?.to) map[row.to] = row
    }
    return map
})

function signerFromLog(toStatus) {
    const row = stageMap.value[toStatus]
    if (!row) return null
    const key = String(row.user_id ?? '')
    return props.po.purchase_request?.remark_signers?.[key] ?? null
}

const poStages = computed(() => {
    const pr = props.po.purchase_request ?? {}
    const stages = [
        {
            key: 'submitted',
            label: 'Submitted by',
            user: pr.requester ?? null,
            name: pr.requester?.name ?? '-',
            at: pr.submitted_at ?? null,
        },
        {
            key: 'verified_own_department',
            label: 'Own Dept Verified by',
            user: signerFromLog('verified_own_department'),
            name: stageMap.value.verified_own_department?.user_name ?? '-',
            at: stageMap.value.verified_own_department?.at ?? null,
        },
    ]

    if (pr.project_id) {
        stages.push({
            key: 'verified_project_department',
            label: 'Project Dept Verified by',
            user: signerFromLog('verified_project_department'),
            name: stageMap.value.verified_project_department?.user_name ?? '-',
            at: stageMap.value.verified_project_department?.at ?? null,
        })
    }

    stages.push(
        {
            key: 'verified_purchasing_department',
            label: 'Purchasing Verified by',
            user: signerFromLog('verified_purchasing_department'),
            name: stageMap.value.verified_purchasing_department?.user_name ?? '-',
            at: stageMap.value.verified_purchasing_department?.at ?? null,
        },
        {
            key: 'po',
            label: 'Approved by',
            user: signerFromLog('po') ?? pr.approver ?? null,
            name: stageMap.value.po?.user_name ?? pr.approver?.name ?? '-',
            at: stageMap.value.po?.at ?? pr.approved_at ?? null,
        },
        {
            key: 'vendor_acceptance',
            label: 'Vendor Acceptance',
            user: null,
            name: props.po.supplier?.company_name ?? '-',
            at: null,
        }
    )

    return stages
})

const displayItems = computed(() => {
    const poItems = Array.isArray(props.po.items) ? props.po.items : []
    if (!poItems.length) return []

    const prItems = Array.isArray(props.po.purchase_request?.items)
        ? props.po.purchase_request.items
        : []
    const prById = new Map(prItems.map((item) => [Number(item.id), item]))

    const roots = []
    const childrenByRoot = new Map()

    const resolveRootId = (prItem) => {
        if (!prItem) return null
        let current = prItem
        let guard = 0
        while (current?.parent_id && guard < 20) {
            const parent = prById.get(Number(current.parent_id))
            if (!parent) break
            current = parent
            guard += 1
        }
        return Number(current?.id ?? 0) || null
    }

    poItems.forEach((item) => {
        const prItem = prById.get(Number(item.purchase_request_item_id ?? 0)) ?? null
        const rootId = resolveRootId(prItem) ?? Number(item.id)
        const isChild = Boolean(prItem?.parent_id)

        if (!childrenByRoot.has(rootId)) {
            childrenByRoot.set(rootId, [])
            roots.push(rootId)
        }

        childrenByRoot.get(rootId).push({
            item,
            isChild,
        })
    })

    const rows = []
    roots.forEach((rootId, rootIndex) => {
        const baseNo = `${rootIndex + 1}`
        const group = childrenByRoot.get(rootId) ?? []

        const parentRows = group.filter((row) => !row.isChild)
        const childRows = group.filter((row) => row.isChild)

        parentRows.forEach((row, idx) => {
            rows.push({
                no: idx === 0 ? baseNo : `${baseNo}.${idx + 1}`,
                depth: 0,
                item: row.item,
            })
        })

        childRows.forEach((row, idx) => {
            rows.push({
                no: `${baseNo}.${idx + 1}`,
                depth: 1,
                item: row.item,
            })
        })
    })

    return rows
})
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
            <tr v-for="row in displayItems" :key="row.item.id">
                <td class="border border-slate-300 px-2 py-2 text-center">{{ row.no }}</td>
                <td class="border border-slate-300 px-2 py-2">
                    <div :class="row.depth > 0 ? 'pl-4' : ''">
                        <span v-if="row.depth > 0" class="mr-1 text-slate-500">↳</span>
                        <span class="font-medium">{{ row.item.item_name }}</span>
                        <span v-if="row.depth === 0" class="ml-2 text-[10px] uppercase tracking-wide text-slate-500">(Parent)</span>
                        <span v-else class="ml-2 text-[10px] uppercase tracking-wide text-slate-500">(Child)</span>
                    </div>
                </td>
                <td class="border border-slate-300 px-2 py-2">{{ row.item.description ?? '-' }}</td>
                <td class="border border-slate-300 px-2 py-2 text-right">{{ row.item.quantity }}</td>
                <td class="border border-slate-300 px-2 py-2 text-right tabular-nums">{{ formatCurrency(row.item.unit_price) }}</td>
                <td class="border border-slate-300 px-2 py-2 text-right tabular-nums">{{ formatCurrency(row.item.quantity * row.item.unit_price) }}</td>
            </tr>

            <tr v-if="!displayItems.length">
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

    <div class="mb-8 overflow-hidden rounded border border-slate-300 bg-white text-sm">
        <div class="bg-slate-100 px-4 py-2 text-sm font-semibold tracking-wide text-slate-800">
            Additional Information
        </div>

        <div class="grid grid-cols-1 gap-4 p-4 text-[13px]">
            <div class="rounded border border-slate-200 bg-slate-50 p-3">
                <div class="mb-2 font-semibold text-slate-800">Terms &amp; Condition</div>
                <ol class="list-decimal space-y-1 pl-5">
                    <li v-for="(term, i) in paymentTermsList" :key="i">{{ term }}</li>
                </ol>
                <div v-if="!paymentTermsList.length" class="text-slate-400">-</div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded border border-slate-200 bg-slate-50 p-3">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Delivery Date</div>
                    <div class="mt-1 font-medium text-slate-800">{{ po.delivery_period || '-' }}</div>
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 p-3">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Person incharge</div>
                    <div class="mt-1 font-medium text-slate-800">{{ po.site_contact?.name || '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-8">
        <div class="mb-1 font-medium">Amount In Words</div>
        <div class="min-h-[40px] rounded border border-slate-300 px-3 py-2 whitespace-pre-wrap">
            {{ amountToWords(totalAmount) || '-' }}
        </div>
    </div>

    <div class="mt-14 text-sm">
        <div
            class="grid gap-4"
            :style="{ gridTemplateColumns: `repeat(${poStages.length}, minmax(0, 1fr))` }"
        >
            <div v-for="stage in poStages" :key="stage.key">
                <div class="h-12 mb-2 flex items-end">
                    <template v-if="signatureUrl(stage.user)">
                        <img
                            :src="signatureUrl(stage.user)"
                            :alt="`${stage.label} signature`"
                            class="h-10 max-w-[160px] object-contain"
                            @error="onSignatureImageError"
                        >
                        <div class="hidden text-[11px] text-slate-400 italic">No signature</div>
                    </template>
                    <div v-else class="text-[11px] text-slate-400 italic">
                        {{ stage.label === 'Vendor Acceptance' ? 'Vendor signature on stamped copy' : 'No signature' }}
                    </div>
                </div>
                <div class="mb-3 border-b-2 border-slate-300"></div>
                <div>{{ stage.label }}</div>
                <div class="text-xs text-slate-500">{{ stage.name }}</div>
                <div class="text-xs text-slate-500">{{ stage.at ? formatDate(stage.at) : '-' }}</div>
            </div>
        </div>
    </div>
</div>
</template>
