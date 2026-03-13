<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    pr: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        required: true,
    },
})

const emit = defineEmits(['delete', 'view', 'view-pr', 'delivery', 'payable', 'payment-slip'])
</script>

<template>
    <div class="flex justify-center gap-3 text-lg">

        <!-- =====================
             DRAFT ACTIONS
        ====================== -->
        <template v-if="status === 'draft'">

            <Link
                :href="route('purchase-request.edit', pr.uuid)"
                @click.stop
                class="text-blue-600 hover:text-blue-800"
                title="Edit"
            >
                <i class="mdi mdi-pencil"></i>
            </Link>

            <button
                v-if="status === 'draft'"
                @click.stop="emit('delete', pr)"
                class="text-red-600 hover:text-red-800"
                title="Delete"
            >
                <i class="mdi mdi-delete"></i>
            </button>

        </template>

        <!-- =====================
             OWN DEPARTMENT VERIFIED
        ====================== -->
        <template v-else-if="status === 'verified_own_department'">
            <Link
                :href="route('purchase-request.edit', pr.uuid)"
                @click.stop
                class="text-blue-600 hover:text-blue-800"
                title="Edit"
            >
                <i class="mdi mdi-pencil"></i>
            </Link>

            <button
                @click.stop="emit('view', pr)"
                class="text-indigo-600 hover:text-indigo-800"
                title="View"
            >
                <i class="mdi mdi-eye"></i>
            </button>
        </template>

        <!-- =====================
             PROJECT VERIFIED
        ====================== -->
        <template v-else-if="status === 'verified_project_department'">
            <Link
                :href="route('purchase-request.edit', pr.uuid)"
                @click.stop
                class="text-blue-600 hover:text-blue-800"
                title="Edit"
            >
                <i class="mdi mdi-pencil"></i>
            </Link>

            <button
                @click.stop="emit('view', pr)"
                class="text-indigo-600 hover:text-indigo-800"
                title="View"
            >
                <i class="mdi mdi-eye"></i>
            </button>
        </template>

        <!-- =====================
             PO ISSUED
        ====================== -->
        <template v-else-if="status === 'po'">
            <button
                @click.stop="emit('view', pr)"
                class="text-indigo-600 hover:text-indigo-800"
                title="View PO A4"
            >
                <i class="mdi mdi-eye"></i>
            </button>

            <button
                @click.stop="emit('view-pr', pr)"
                class="text-slate-600 hover:text-slate-800"
                title="View PR Document"
            >
                <i class="mdi mdi-file-document-outline"></i>
            </button>

            <button
                @click.stop="emit('delivery', pr)"
                class="text-orange-600 hover:text-orange-800"
                title="Update Delivery"
            >
                <i class="mdi mdi-truck-outline"></i>
            </button>

            <button
                @click.stop="emit('payable', pr)"
                v-if="pr.purchase_order?.confirmed_at && !pr.purchase_order?.ap_invoice"
                class="text-purple-600 hover:text-purple-800"
                title="Create Payable"
            >
                <i class="mdi mdi-receipt-text-plus-outline"></i>
            </button>

            <span
                v-else-if="!pr.purchase_order?.confirmed_at"
                class="text-gray-400"
                title="Create payable is available after PO confirmation"
            >
                <i class="mdi mdi-lock-outline"></i>
            </span>

            <span
                v-else
                class="text-green-600"
                title="AP invoice already created for this PO"
            >
                <i class="mdi mdi-check-circle-outline"></i>
            </span>
        </template>

        <!-- =====================
             PAYMENT
        ====================== -->
        <template v-else-if="status === 'payment'">
            <button
                @click.stop="emit('view', pr)"
                class="text-indigo-600 hover:text-indigo-800"
                title="View PO A4"
            >
                <i class="mdi mdi-eye"></i>
            </button>

            <button
                @click.stop="emit('payment-slip', pr)"
                class="text-emerald-600 hover:text-emerald-800"
                title="Open in Payment Slips"
            >
                <i class="mdi mdi-cash-multiple"></i>
            </button>
        </template>

        <!-- =====================
             NON-DRAFT
        ====================== -->
        <template v-else>

            <!-- 👁 OPEN MODAL -->
            <button
                @click.stop="emit('view', pr)"
                class="text-indigo-600 hover:text-indigo-800"
                title="View"
            >
                <i class="mdi mdi-eye"></i>
            </button>

        </template>

    </div>
</template>
