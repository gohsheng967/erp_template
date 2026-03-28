<script setup>
import { computed } from 'vue'
import { useFormat } from '@/Composables/useFormat'

const { capitalize, formatCurrency, formatDate } = useFormat()
const props = defineProps({
  request: {
    type: Object,
    required: true,
  },
  company: {
      type: Object,
      required: true,
  },
})

/* =====================
   WATERMARK (PRINT SAFE)
===================== */
const watermark = computed(() => {
  switch (props.request.status) {
    case 'approved':
    case 'ceo_approved':
    case 'po':
    case 'payment':
      return { text: 'APPROVED', color: 'rgba(34,197,94,0.15)' }
    case 'rejected':
      return { text: 'REJECTED', color: 'rgba(239,68,68,0.15)' }
    case 'cancelled':
      return { text: 'CANCELLED', color: 'rgba(107,114,128,0.20)' }
    default:
      return null
  }
})

function displayStatusLabel(status, isProjectLinked = false) {
  switch (status) {
    case 'draft':
      return 'Draft'
    case 'submitted':
      return 'Pending Check'
    case 'verified_own_department':
      return isProjectLinked
        ? 'Pending Project Dept Verify'
        : 'Pending Purchasing Dept Verify'
    case 'verified_project_department':
      return 'Pending Purchasing Dept Verify'
    case 'verified_purchasing_department':
      return 'Pending CEO / GM Approve'
    case 'po':
      return 'PO'
    case 'payment':
      return 'Payment'
    case 'rejected':
      return 'Rejected'
    default:
      return String(status ?? '-').replaceAll('_', ' ')
  }
}

const currentStatusLabel = computed(() =>
  displayStatusLabel(props.request.status, !!props.request.project_id)
)

const stageMap = computed(() => {
  const logs = Array.isArray(props.request.remark_log) ? props.request.remark_log : []
  const map = {}
  for (const row of logs) {
    if (row?.to) map[row.to] = row
  }
  return map
})

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

function signerFromLog(toStatus) {
  const row = stageMap.value[toStatus]
  if (!row) return null
  const key = String(row.user_id ?? '')
  return props.request.remark_signers?.[key] ?? null
}

const signatureStages = computed(() => {
  const stages = [
    {
      key: 'requested',
      label: 'Submitted By',
      name: props.request.requester?.name ?? '-',
      at: props.request.submitted_at ?? '-',
      signature_url: signatureUrl(props.request.requester),
    },
    {
      key: 'verified_own_department',
      label: 'Verified By',
      name: stageMap.value.verified_own_department?.user_name ?? '-',
      at: stageMap.value.verified_own_department?.at ?? '-',
      signature_url: signatureUrl(signerFromLog('verified_own_department')),
    },
  ]

  if (props.request.project_id) {
    stages.push({
      key: 'verified_project_department',
      label: 'Verified By',
      name: stageMap.value.verified_project_department?.user_name ?? '-',
      at: stageMap.value.verified_project_department?.at ?? '-',
      signature_url: signatureUrl(signerFromLog('verified_project_department')),
    })
  }

  stages.push({
    key: 'verified_purchasing_department',
    label: 'Verified By',
    name: stageMap.value.verified_purchasing_department?.user_name ?? '-',
    at: stageMap.value.verified_purchasing_department?.at ?? '-',
    signature_url: signatureUrl(signerFromLog('verified_purchasing_department')),
  })

  // Show approval signer only after PR is actually approved to PO/payment.
  const isApprovedStage = ['po', 'payment'].includes(props.request.status)
  if (isApprovedStage) {
    stages.push({
      key: 'po',
      label: 'Approved By',
      name: stageMap.value.po?.user_name ?? props.request.approver?.name ?? '-',
      at: stageMap.value.po?.at ?? props.request.approved_at ?? '-',
      signature_url: signatureUrl(signerFromLog('po')) || signatureUrl(props.request.approver),
    })
  }

  return stages
})

const displayItems = computed(() => {
  const source = Array.isArray(props.request.items) ? props.request.items : []
  if (!source.length) return []

  const itemsById = new Map(source.map((item) => [Number(item.id), item]))
  const childrenByParent = new Map()

  source.forEach((item) => {
    const parentId = Number(item.parent_id ?? 0)
    if (!parentId) return
    if (!childrenByParent.has(parentId)) childrenByParent.set(parentId, [])
    childrenByParent.get(parentId).push(item)
  })

  for (const children of childrenByParent.values()) {
    children.sort((a, b) => Number(a.id) - Number(b.id))
  }

  const roots = source
    .filter((item) => !Number(item.parent_id ?? 0) || !itemsById.has(Number(item.parent_id)))
    .sort((a, b) => Number(a.id) - Number(b.id))

  const rows = []
  roots.forEach((root, rootIndex) => {
    const baseNo = `${rootIndex + 1}`
    rows.push({ no: baseNo, depth: 0, item: root })

    const directChildren = childrenByParent.get(Number(root.id)) ?? []
    directChildren.forEach((child, childIndex) => {
      rows.push({ no: `${baseNo}.${childIndex + 1}`, depth: 1, item: child })
    })
  })

  return rows
})

const hierarchyAwareTotal = computed(() => {
  const source = Array.isArray(props.request.items) ? props.request.items : []
  if (!source.length) return 0

  const parentIds = new Set(
    source
      .map((item) => Number(item.parent_id ?? 0))
      .filter((id) => id > 0)
  )

  return source.reduce((sum, item) => {
    const itemId = Number(item.id ?? 0)
    const hasChildren = parentIds.has(itemId)
    if (hasChildren) return sum

    return sum + (Number(item.quantity) * Number(item.unit_price))
  }, 0)
})

</script>

<template>
<!-- =====================
     A4 ROOT (PRINT TARGET)
===================== -->
<div
  class="claim-print relative bg-white text-gray-800 mx-auto
         w-[210mm] min-h-[297mm]
         p-[12mm] text-[13px] leading-relaxed
         border border-gray-300"
>

  <!-- =====================
       WATERMARK
  ====================== -->
  <div
    v-if="watermark"
    class="absolute inset-0 flex items-center justify-center
           pointer-events-none select-none z-0"
  >
    <div
      class="text-[110px] font-extrabold rotate-[-25deg]
             -translate-y-[120px]"
      :style="{ color: watermark.color }"
    >
      {{ watermark.text }}
    </div>
  </div>

  <!-- =====================
       HEADER
  ====================== -->
  <div class="flex justify-between items-start border-b-2 border-gray-300 pb-4 mb-6 relative z-10">
    <!-- COMPANY INFO -->
    <div class="flex gap-3 max-w-[65%]">
        <div>
            <div class="font-semibold text-base">
                {{ company.company_name }}
            </div>
            <div class="text-xs text-gray-500">
                Reg No: {{ company.company_reg_no }}
            </div>
            <div class="text-xs text-gray-500">
                {{ company.address }}
            </div>
            <div class="text-xs text-gray-500">
                Tel: {{ company.office_number }}
            </div>
        </div>
    </div>

    <div class="text-right whitespace-nowrap">
      <div class="text-lg font-bold tracking-wide">
          PURCHASE REQUEST
      </div>
      <div class="text-sm">
        PR No: {{ request.code }}
      </div>
      <div class="text-xs mt-1">
          Status:
          <span class="font-semibold">
              {{ currentStatusLabel }}
          </span>
      </div>
    </div>
  </div>

  <!-- =====================
       REQUEST INFO
  ====================== -->
  <div class="grid grid-cols-2 gap-x-40 gap-y-2 mb-6 relative z-10 border border-gray-300 rounded px-4 py-3">
    <div>
      <span class="font-medium">Requester</span>:
      {{ request.requester?.name ?? '-' }}
    </div>

    <div>
      <span class="font-medium">Department</span>:
      {{ request.department?.name }}
    </div>

    <div>
      <span class="font-medium">Title</span>:
      {{ request.title }}
    </div>

    <div>
      <span class="font-medium">Required Date</span>:
      {{ formatDate(request.required_date) }}
    </div>
  </div>

  <!-- =====================
       ITEMS TABLE
  ====================== -->
  <table class="w-full border border-gray-400 mb-6 relative z-10">
    <thead class="bg-gray-100 border-b-2 border-gray-400">
      <tr>
        <th class="border border-gray-400 px-2 py-2 w-[28px] text-center">#</th>
        <th class="border border-gray-400 px-2 py-2 text-left">Item</th>
        <th class="border border-gray-400 px-2 py-2 text-left">Specification</th>
        <th class="border border-gray-400 px-2 py-2 w-[70px] text-center">Qty</th>
        <th class="border border-gray-400 px-2 py-2 w-[110px] text-right">
          Est. Price (RM)
        </th>
        <th class="border border-gray-400 px-2 py-2 w-[120px] text-right">
          Subtotal (RM)
        </th>
      </tr>
    </thead>

    <tbody>
      <tr
        v-for="row in displayItems"
        :key="row.item.id"
      >
        <td class="border border-gray-300 px-2 py-2 text-center">
          {{ row.no }}
        </td>

        <td class="border border-gray-300 px-2 py-2">
          <div :class="row.depth > 0 ? 'pl-4' : ''">
            <span v-if="row.depth > 0" class="mr-1 text-gray-500">↳</span>
            <span class="font-medium">{{ row.item.title }}</span>
            <span v-if="row.depth === 0" class="ml-2 text-[10px] uppercase tracking-wide text-gray-500">(Parent)</span>
            <span v-else class="ml-2 text-[10px] uppercase tracking-wide text-gray-500">(Child)</span>
          </div>
        </td>

        <td class="border border-gray-300 px-2 py-2">
          {{ row.item.specification ?? row.item.description ?? '-' }}
        </td>

        <td class="border border-gray-300 px-2 py-2 text-center">
          {{ row.item.quantity }}
        </td>

        <td class="border border-gray-300 px-2 py-2 text-right tabular-nums">
          {{ formatCurrency(row.item.unit_price) }}
        </td>

        <td class="border border-gray-300 px-2 py-2 text-right tabular-nums">
          {{ formatCurrency(row.item.quantity * row.item.unit_price) }}
        </td>
      </tr>

      <tr v-if="!displayItems.length">
        <td colspan="6" class="border border-gray-300 px-2 py-6 text-center text-gray-400">
          No purchase items
        </td>
      </tr>
    </tbody>
  </table>

  <!-- =====================
       TOTAL
  ====================== -->
  <div class="flex justify-end mb-10 relative z-10">
    <div class="w-[260px] border-t-2 border-gray-400 pt-3">
      <div class="flex justify-between">
        <span class="font-medium">Estimated Total</span>
        <span class="font-bold tabular-nums">
          {{ formatCurrency(hierarchyAwareTotal) }}
        </span>
      </div>
    </div>
  </div>

  <!-- =====================
       REMARK
  ====================== -->
  <div class="mb-1 relative z-10">
    <div class="font-medium text-xs mb-1">Purpose</div>
    <div class="border border-gray-300 px-2 py-1 min-h-[40px] text-xs whitespace-pre-wrap">
      {{ request.purpose ?? '-' }}
    </div>
  </div>

  <div class="mb-1 relative z-10">
    <div class="font-medium text-xs mb-1">Purchasing Verification Details</div>
    <div class="border border-gray-300 px-3 py-2 text-xs space-y-1">
      <div v-if="!request.is_subcon_purchase_request">
        <span class="font-medium">Delivery Period:</span>
        {{ request.delivery_period ? formatDate(request.delivery_period) : '-' }}
      </div>
      <div>
        <span class="font-medium">Site Contact Person:</span>
        {{ request.site_contact?.name ?? '-' }}
      </div>
      <div>
        <span class="font-medium">Terms &amp; Condition:</span>
        <div class="mt-0.5 whitespace-pre-wrap">
          {{ request.payment_terms ?? '-' }}
        </div>
      </div>
    </div>
  </div>

  <div class="mb-4 relative z-10">
    <div class="font-medium text-xs mb-1">Remark</div>
    <div class="border border-gray-300 px-2 py-1 min-h-[40px] text-xs whitespace-pre-wrap">
      {{ request.reviewer_remark ?? '-' }}
    </div>
  </div>


  <!-- =====================
       APPROVAL SIGNATURES
  ====================== -->
  <div
    class="mb-6 relative z-10"
  >
    <div
      class="grid gap-4"
      :style="{ gridTemplateColumns: `repeat(${signatureStages.length}, minmax(0, 1fr))` }"
    >
    <div
      v-for="stage in signatureStages"
      :key="stage.key"
    >
      <div class="h-12 mb-2 flex items-end">
        <template v-if="stage.signature_url">
          <img
            :src="stage.signature_url"
            alt="Signature"
            class="h-10 max-w-[160px] object-contain"
            @error="onSignatureImageError"
          >
          <div class="hidden text-[11px] text-gray-400 italic">No signature</div>
        </template>
        <div v-else class="text-[11px] text-gray-400 italic">No signature</div>
      </div>
      <div class="mb-2 border-b-2 border-gray-300"></div>
      <div>{{ stage.label }}</div>
      <div class="text-xs text-gray-500">
        {{ stage.name }}
      </div>
      <div class="text-xs text-gray-500">
        {{ stage.at && stage.at !== '-' ? formatDate(stage.at) : '-' }}
      </div>
    </div>
    </div>
  </div>

  <!-- =====================
       FOOTER
  ====================== -->
  <div class="mt-16 text-xs text-gray-400 text-center relative z-10">
    Generated by Infinite ERP • {{ new Date().toLocaleString() }}
  </div>

</div>
</template>
