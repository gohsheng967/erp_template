<script setup>
import { ref, inject, onMounted, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { useFormat } from '@/Composables/useFormat'
const { formatCurrency } = useFormat()
import { router } from '@inertiajs/vue3'

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
   TOAST
================================ */

const toast = inject('toast', null)

/* ===============================
   STATE
================================ */

const loading = ref(true)

const summary = ref({
    pending_approval: 0,
    pending_payment: 0,
    total_approved_amount: 0,
    total_paid_amount: 0,
})

const pendingClaims = ref([])

/* ===============================
   LOAD CLAIM SUMMARY
================================ */

async function loadClaimSummary() {
    if (!props.projectId) return

    loading.value = true

    try {
        const res = await axios.get(
            route('projects.claims.summary', props.projectId)
        )

        summary.value = res.data.summary
        pendingClaims.value = res.data.pending_claims

    } catch (e) {
        console.error(e)
        toast?.value?.show('Failed to load claim data', 'error')
    } finally {
        loading.value = false
    }
}

onMounted(loadClaimSummary)

// reload if project changes
watch(() => props.projectId, loadClaimSummary)


const claimForm = useForm({
    project_id: props.projectId,
    title: '',
    total_amount: '',
})

function createClaim() {
    if (!claimForm.title || !claimForm.total_amount) {
        toast?.value?.show('Please fill in all fields', 'error')
        return
    }

    claimForm.post(route('claims.store'), {
        onSuccess: () => {
        },
    })
}

function resetClaim() {
    claimForm.reset()
    claimForm.clearErrors()
}

function openDraftClaim(claim) {
    if (claim.status !== 'draft') return

    router.visit(route('claims.edit', claim.uuid))
}
</script>

<template>
<div class="space-y-8">

    <!-- ===============================
         CREATE NEW CLAIM
    =============================== -->
    <div class="bg-gray-50 rounded-lg p-4 space-y-4 border">
        <h3 class="font-semibold text-gray-700">
            Submit New Claim
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-gray-700 text-sm mb-1">Title</label>
                <input
                    v-model="claimForm.title"
                    type="text"
                    class="border px-3 py-2 rounded-md w-full"
                    placeholder="Travel to client / Tools purchase"
                />
                <div v-if="claimForm.errors.title"
                     class="text-sm text-red-500">
                    {{ claimForm.errors.title }}
                </div>
            </div>

            <div>
                <label class="block text-gray-700 text-sm mb-1">
                    Total Amount (RM)
                </label>
                <input
                    v-model="claimForm.total_amount"
                    type="number"
                    step="0.01"
                    class="border px-3 py-2 rounded-md w-full"
                    placeholder="0.00"
                />
                <div v-if="claimForm.errors.total_amount"
                     class="text-sm text-red-500">
                    {{ claimForm.errors.total_amount }}
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button
                @click="createClaim"
                :disabled="claimForm.processing"
                class="px-4 py-2 bg-green-600 text-white rounded-md
                       hover:bg-green-700 disabled:opacity-50"
            >
                Create Claim
            </button>

            <button
                type="button"
                @click="resetClaim"
                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md
                       hover:bg-gray-300"
            >
                Reset
            </button>
        </div>
    </div>

    <!-- ===============================
         LOADING
    =============================== -->
    <div v-if="loading" class="text-sm text-gray-400">
        Loading claim summary…
    </div>

    <!-- ===============================
         SUMMARY
    =============================== -->
    <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">

        <div class="bg-yellow-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">Pending Approval</div>
            <div class="text-2xl font-semibold text-yellow-700">
                {{ summary.pending_approval }}
            </div>
        </div>

        <div class="bg-orange-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">Pending Payment</div>
            <div class="text-2xl font-semibold text-orange-700">
                {{ summary.pending_payment }}
            </div>
        </div>

        <div class="bg-green-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">Total Approved</div>
            <div class="text-xl font-semibold text-green-700">
                {{ formatCurrency(summary.total_approved_amount) }}
            </div>
        </div>

        <div class="bg-indigo-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">Total Paid</div>
            <div class="text-xl font-semibold text-indigo-700">
                {{ formatCurrency(summary.total_paid_amount) }}
            </div>
        </div>

    </div>

    <!-- ===============================
         PENDING CLAIMS TABLE
    =============================== -->
    <div v-if="!loading">
        <h3 class="font-semibold text-gray-700 mb-2">
            Pending Claims
        </h3>

        <table class="min-w-full text-sm divide-y divide-gray-200 bg-white rounded-lg shadow">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Title</th>
                    <th class="px-3 py-2 text-left">Amount</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Date</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
                <tr
                    v-for="c in pendingClaims"
                    :key="c.id"
                    @click="openDraftClaim(c)"
                    :class="[
                        'hover:bg-gray-50',
                        c.status === 'draft'
                            ? 'cursor-pointer'
                            : 'cursor-default opacity-75'
                    ]"
                >
                    <td class="px-3 py-2">{{ c.title }}</td>
                    <td class="px-3 py-2">
                        {{ formatCurrency(c.total_amount) }}
                    </td>
                    <td class="px-3 py-2 capitalize">
                        <span
                            :class="{
                                'text-gray-600': c.status === 'draft',
                                'text-yellow-600': c.status === 'submitted',
                                'text-orange-600': c.status === 'approved',
                            }"
                            class="font-semibold"
                        >
                            {{ c.status }}
                        </span>
                    </td>
                    <td class="px-3 py-2">
                        {{ c.created_at }}
                    </td>
                </tr>

                <tr v-if="!pendingClaims.length">
                    <td colspan="4"
                        class="px-3 py-6 text-center text-gray-400">
                        No pending claims
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ===============================
         TRANSACTIONS LINK
    =============================== -->
    <div class="text-right">
        <a
            :href="route('claims.index', { project_id: projectId })"
            class="text-sm text-indigo-600 hover:underline font-medium"
        >
            View all claim transactions →
        </a>
    </div>

</div>
</template>
