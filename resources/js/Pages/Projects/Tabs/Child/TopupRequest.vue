<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { useFormat } from '@/Composables/useFormat'
import TopupTable from '@/Pages/PettyCash/Topups/Partials/TopupsTable.vue'
import CreateTopupModal from '@/Pages/PettyCash/Topups/Partials/CreateTopupModal.vue'
import TopupApprovalModal from '@/Pages/PettyCash/Topups/Partials/TopupApprovalModal.vue'
import PayTopupModal from '@/Pages/PettyCash/Topups/Partials/PayTopupModal.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'

const props = defineProps({
    projectId: {
        type: [Number, String],
        required: true,
    },
    projectName: {
        type: String,
        required: true,
    },
})

const toast = inject('toast', null)
const { formatCurrency } = useFormat()

const tabs = [
    { key: 'requested', label: 'Requested', badge: true },
    { key: 'approved', label: 'Approved', badge: true },
    { key: 'rejected', label: 'Rejected', badge: true },
    { key: 'paid', label: 'Paid', badge: false },
]

const activeTab = ref('requested')
const loading = ref(true)
const topups = ref({ data: [], links: [] })
const counts = ref({})
const walletCount = ref(0)
const walletSummaries = ref([])

const showCreateModal = ref(false)
const showApproveModal = ref(false)
const showPayModal = ref(false)
const showDeleteConfirm = ref(false)

const approvingTopup = ref(null)
const payingTopup = ref(null)
const deletingTopup = ref(null)

const projectList = computed(() => [
    { id: props.projectId, name: props.projectName },
])

async function loadTopups(url = null) {
    if (!props.projectId) return

    loading.value = true

    try {
        const response = await axios.get(
            url ?? route('projects.topups.index', props.projectId),
            {
                params: {
                    status: activeTab.value,
                },
            }
        )

        topups.value = response.data.topups
        counts.value = response.data.counts ?? {}
        walletCount.value = response.data.wallet_count ?? 0
        walletSummaries.value = response.data.wallet_summaries ?? []
    } catch (error) {
        console.error(error)
        toast?.value?.show('Failed to load top-up requests', 'error')
    } finally {
        loading.value = false
    }
}

function switchTab(tab) {
    if (activeTab.value === tab) return
    activeTab.value = tab
}

function confirmApprove(topup) {
    approvingTopup.value = topup
    showApproveModal.value = true
}

function openPay(topup) {
    payingTopup.value = topup
    showPayModal.value = true
}

function askDelete(topup) {
    deletingTopup.value = topup
    showDeleteConfirm.value = true
}

function closeApprove() {
    showApproveModal.value = false
    approvingTopup.value = null
}

function onApproved() {
    closeApprove()
    loadTopups()
}

function closePay() {
    showPayModal.value = false
    payingTopup.value = null
    loadTopups()
}

function closeDelete() {
    showDeleteConfirm.value = false
    deletingTopup.value = null
}

function doDelete() {
    if (!deletingTopup.value) return

    router.delete(
        route('petty-cash.topups.destroy', deletingTopup.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Top-up request deleted')
                closeDelete()
                loadTopups()
            },
        }
    )
}

function onCreated() {
    showCreateModal.value = false
    loadTopups()
}

onMounted(loadTopups)
watch(() => props.projectId, () => loadTopups())
watch(activeTab, () => loadTopups())
</script>

<template>
    <div class="space-y-6">
        <div class="bg-gray-50 rounded-lg p-4 border flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-700">
                    Project Top-Up Requests
                </h3>
                <p class="text-sm text-gray-500">
                    Submit and track petty cash top-ups for this project
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ walletCount }} wallet{{ walletCount === 1 ? '' : 's' }} bound to this project
                </p>
            </div>

            <button
                @click="showCreateModal = true"
                class="px-4 py-2 bg-green-600 text-white rounded-md
                    hover:bg-green-700"
            >
                New Top-Up Request
            </button>
        </div>

        <div v-if="walletSummaries.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div
                v-for="wallet in walletSummaries"
                :key="wallet.id"
                class="bg-white rounded border p-3"
            >
                <div class="text-xs text-gray-500">Wallet #{{ wallet.id }}</div>
                <div class="text-sm font-semibold text-gray-800 mt-1">
                    Balance: {{ formatCurrency(wallet.current_balance) }}
                </div>
                <div class="text-xs text-gray-600 mt-1">
                    Holding: {{ wallet.holder_name ?? 'Unassigned' }}
                </div>
            </div>
        </div>

        <div class="bg-white rounded shadow border px-4">
            <nav class="flex gap-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="switchTab(tab.key)"
                    class="py-3 font-medium flex items-center gap-2"
                    :class="{
                        'border-b-2 border-green-600 text-green-600': activeTab === tab.key,
                        'text-gray-500': activeTab !== tab.key
                    }"
                >
                    {{ tab.label }}

                    <span
                        v-if="tab.badge && counts[tab.key] > 0"
                        class="px-2 py-0.5 text-xs rounded-full"
                        :class="activeTab === tab.key
                            ? 'bg-green-600 text-white'
                            : 'bg-gray-200 text-gray-700'"
                    >
                        {{ counts[tab.key] }}
                    </span>
                </button>
            </nav>
        </div>

        <div v-if="loading" class="text-sm text-gray-400">
            Loading top-up requests...
        </div>

        <TopupTable
            v-else
            :topups="topups"
            :status="activeTab"
            @approve="confirmApprove"
            @pay="openPay"
            @delete="askDelete"
        />

        <div v-if="topups.links?.length" class="flex gap-1">
            <button
                v-for="link in topups.links"
                :key="link.label"
                :disabled="!link.url"
                v-html="link.label"
                class="px-3 py-1 border rounded text-sm"
                :class="{ 'text-gray-400 cursor-not-allowed': !link.url }"
                @click="link.url && loadTopups(link.url)"
            />
        </div>
    </div>

    <CreateTopupModal
        :show="showCreateModal"
        :projects="projectList"
        default-context-type="project"
        :default-project-id="projectId"
        :lock-context-type="true"
        :lock-project="true"
        @close="showCreateModal = false"
        @created="onCreated"
    />

    <TopupApprovalModal
        :show="showApproveModal"
        :topup="approvingTopup"
        @approved="onApproved"
        @close="closeApprove"
    />

    <PayTopupModal
        :show="showPayModal"
        :topup="payingTopup"
        @close="closePay"
    />

    <DeleteConfirmation
        v-if="showDeleteConfirm"
        title="Delete Top-Up"
        message="This action cannot be undone. Are you sure?"
        @confirm="doDelete"
        @close="closeDelete"
    />
</template>
