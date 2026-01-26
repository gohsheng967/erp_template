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
      return { text: 'APPROVED', color: 'rgba(34,197,94,0.15)' }
    case 'rejected':
      return { text: 'REJECTED', color: 'rgba(239,68,68,0.15)' }
    case 'cancelled':
      return { text: 'CANCELLED', color: 'rgba(107,114,128,0.20)' }
    default:
      return null
  }
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
          <span class="font-semibold uppercase">
              {{ request.status }}
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
        v-for="(item, index) in request.items ?? []"
        :key="item.id"
      >
        <td class="border border-gray-300 px-2 py-2 text-center">
          {{ index + 1 }}
        </td>

        <td class="border border-gray-300 px-2 py-2">
          {{ item.title }}
        </td>

        <td class="border border-gray-300 px-2 py-2">
          {{ item.specification ?? '-' }}
        </td>

        <td class="border border-gray-300 px-2 py-2 text-center">
          {{ item.quantity }}
        </td>

        <td class="border border-gray-300 px-2 py-2 text-right tabular-nums">
          {{ formatCurrency(item.unit_price) }}
        </td>

        <td class="border border-gray-300 px-2 py-2 text-right tabular-nums">
          {{ formatCurrency(item.quantity * item.unit_price) }}
        </td>
      </tr>

      <tr v-if="!request.items?.length">
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
          {{ formatCurrency(request.total_amount) }}
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

  <div class="mb-4 relative z-10">
    <div class="font-medium text-xs mb-1">Remark</div>
    <div class="border border-gray-300 px-2 py-1 min-h-[40px] text-xs whitespace-pre-wrap">
      {{ request.requester_remark ?? '-' }}
    </div>
  </div>


  <!-- =====================
       APPROVAL SIGNATURES
  ====================== -->
    <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-6 relative z-10">

    <div>
      <div class="mb-8 border-b-2 border-gray-300"></div>
      <div>Requested By</div>
      <div class="text-xs text-gray-500">
        {{ request.requester?.name ?? '-' }}
      </div>
      <div class="text-xs text-gray-500">
        {{ formatDate(request.submitted_at) }}
      </div>
    </div>

    <div>
      <div class="mb-8 border-b-2 border-gray-300"></div>
      <div>Approved By</div>
      <div class="text-xs text-gray-500">
        {{ request.approver?.name ?? '-' }}
      </div>
      <div class="text-xs text-gray-500">
        {{ formatDate(request.approved_at) }}
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
