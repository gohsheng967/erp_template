<script setup>
import { ref, inject, onMounted, watch } from 'vue'
import axios from 'axios'
import { useForm, router } from '@inertiajs/vue3'
import { useFormat } from '@/Composables/useFormat'
import CreatePurchaseRequestModal from '@/Pages/Transactions/PurchaseRequest/Partials/CreatePurchaseRequestModal.vue'

const { formatCurrency } = useFormat()
const toast = inject('toast', null)
const showCreateModal = ref(false)

function openCreateModal() {
    showCreateModal.value = true
}

function closeCreateModal() {
    showCreateModal.value = false
}

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
    pending_approval: 0,
    total_po_amount: 0,
})

const pendingRequests = ref([])

/* ===============================
   LOAD SUMMARY
================================ */
async function loadSummary() {
    if (!props.projectId) return

    loading.value = true

    try {
        const res = await axios.get(
            route('projects.pr.summary', props.projectId)
        )

        summary.value = res.data.summary
        pendingRequests.value = res.data.pending_requests

    } catch (e) {
        console.error(e)
        toast?.value?.show('Failed to load purchase request data', 'error')
    } finally {
        loading.value = false
    }
}

onMounted(loadSummary)
watch(() => props.projectId, loadSummary)


</script>

<template>
<div class="space-y-8">

    <!-- ===============================
         CREATE PR
    =============================== -->
    <div class="bg-gray-50 rounded-lg p-4 border flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-gray-700">
                Purchase Requests
            </h3>
            <p class="text-sm text-gray-500">
                Create a new request for this project
            </p>
        </div>

        <button
            @click="openCreateModal"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md
                hover:bg-indigo-700"
        >
            New Purchase Request
        </button>
    </div>


    <!-- ===============================
         LOADING
    =============================== -->
    <div v-if="loading" class="text-sm text-gray-400">
        Loading purchase request summary…
    </div>

    <!-- ===============================
         SUMMARY
    =============================== -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="bg-yellow-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">Pending Approval</div>
            <div class="text-2xl font-semibold text-yellow-700">
                {{ summary.pending_approval }}
            </div>
        </div>

        <div class="bg-indigo-50 border rounded-lg p-4">
            <div class="text-xs text-gray-500">PO Committed</div>
            <div class="text-xl font-semibold text-indigo-700">
                {{ formatCurrency(summary.total_po_amount) }}
            </div>
        </div>

    </div>

    <!-- ===============================
         PENDING PR TABLE
    =============================== -->
    <div v-if="!loading">
        <h3 class="font-semibold text-gray-700 mb-2">
            Pending Purchase Requests
        </h3>

        <table class="min-w-full text-sm divide-y divide-gray-200 bg-white rounded-lg shadow">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Title</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Date</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
                <tr
                    v-for="pr in pendingRequests"
                    :key="pr.id"
                    @click="openDraftPR(pr)"
                    :class="[
                        'hover:bg-gray-50',
                        pr.status === 'draft'
                            ? 'cursor-pointer'
                            : 'cursor-default opacity-75'
                    ]"
                >
                    <td class="px-3 py-2">{{ pr.title }}</td>
                    <td class="px-3 py-2 capitalize font-semibold">
                        {{ pr.status }}
                    </td>
                    <td class="px-3 py-2">
                        {{ pr.created_at }}
                    </td>
                </tr>

                <tr v-if="!pendingRequests.length">
                    <td colspan="3"
                        class="px-3 py-6 text-center text-gray-400">
                        No pending purchase requests
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
            :href="route('purchase-request.index', { project_id: projectId })"
            class="text-sm text-indigo-600 hover:underline font-medium"
        >
            View all purchase requests →
        </a>
    </div>

</div>

<CreatePurchaseRequestModal
    :show="showCreateModal"
    @close="closeCreateModal"
    @created="() => {
        closeCreateModal()
        loadSummary()
    }"
/>

</template>
