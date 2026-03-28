<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useFormat } from '@/Composables/useFormat'
import CreateClaimModal from './Partials/CreateClaimModal.vue'

const props = defineProps({
    wallets: {
        type: Array,
        required: true,
    },
    selectedWalletUuid: {
        type: String,
        default: null,
    },
    statement: {
        type: Object,
        default: null,
    },
})

const showCreateClaim = ref(false)
const walletQuery = ref('')
const { formatDate, formatCurrency } = useFormat()
const monthLabel = computed(() => props.statement?.period?.month ?? '-')

const totalWalletBalance = computed(() =>
    props.wallets.reduce((sum, wallet) => sum + Number(wallet.current_balance ?? 0), 0)
)
const activeWalletUuid = computed(() => props.selectedWalletUuid ?? props.statement?.wallet?.uuid ?? null)
const selectedWallet = computed(() =>
    props.wallets.find((wallet) => wallet.uuid === activeWalletUuid.value) ?? props.statement?.wallet ?? null
)
const selectedWalletBalance = computed(() =>
    Number(selectedWallet.value?.current_balance ?? 0)
)
const officeWalletCount = computed(() => props.wallets.filter((wallet) => wallet.context_type === 'office').length)
const projectWalletCount = computed(() => props.wallets.filter((wallet) => wallet.context_type === 'project').length)

const filteredWallets = computed(() => {
    const keyword = walletQuery.value.trim().toLowerCase()
    if (!keyword) return props.wallets

    return props.wallets.filter((wallet) => {
        const name = String(wallet.name ?? '').toLowerCase()
        const type = wallet.context_type === 'office' ? 'office wallet' : 'project wallet'
        return name.includes(keyword) || type.includes(keyword)
    })
})

function shiftMonth(month, diff) {
    const [y, m] = month.split('-').map(Number)
    const d = new Date(y, m - 1 + diff, 1)
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function selectWallet(walletUuid) {
    router.get(
        route('petty-cash.wallets.index'),
        {
            wallet_uuid: walletUuid,
            month: props.statement?.period?.month ?? undefined,
        },
        { preserveScroll: true, replace: true }
    )
}

function prevMonth() {
    if (!props.statement?.wallet?.uuid || !props.statement?.period?.month) return

    router.get(
        route('petty-cash.wallets.index'),
        {
            wallet_uuid: props.statement.wallet.uuid,
            month: shiftMonth(props.statement.period.month, -1),
        },
        { preserveScroll: true, replace: true }
    )
}

function nextMonth() {
    if (!props.statement?.wallet?.uuid || !props.statement?.period?.month) return

    router.get(
        route('petty-cash.wallets.index'),
        {
            wallet_uuid: props.statement.wallet.uuid,
            month: shiftMonth(props.statement.period.month, 1),
        },
        { preserveScroll: true, replace: true }
    )
}

function openCreateClaim() {
    showCreateClaim.value = true
}

function closeCreateClaim() {
    showCreateClaim.value = false
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">
                    Balance Statements
                </h2>
                <Link
                    :href="route('petty-cash.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    &larr; Back
                </Link>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-600">Wallet Selector</div>
                    <div class="flex flex-wrap items-center gap-1.5 text-xs font-semibold text-slate-600">
                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-indigo-700">
                            {{ selectedWallet?.name ?? 'Selected Wallet' }} {{ formatCurrency(selectedWalletBalance) }}
                        </span>
                        <span class="rounded-full bg-white px-2 py-0.5">Total {{ formatCurrency(totalWalletBalance) }}</span>
                        <span class="rounded-full bg-white px-2 py-0.5">Office {{ officeWalletCount }}</span>
                        <span class="rounded-full bg-white px-2 py-0.5">Project {{ projectWalletCount }}</span>
                    </div>
                </div>

                <div class="mt-2 flex flex-col gap-2 lg:flex-row lg:items-center">
                    <input
                        v-model="walletQuery"
                        type="text"
                        placeholder="Search wallet"
                        class="h-8 w-full rounded-md border border-slate-300 bg-white px-3 text-sm lg:w-64"
                    />

                    <div class="min-w-0 flex-1 overflow-x-auto">
                        <div class="flex min-w-max gap-1.5">
                            <button
                                v-for="wallet in filteredWallets"
                                :key="wallet.id"
                                type="button"
                                @click="selectWallet(wallet.uuid)"
                                class="inline-flex items-center gap-2 rounded-md border px-2.5 py-1 text-sm transition"
                                :class="selectedWalletUuid === wallet.uuid
                                    ? 'border-indigo-300 bg-indigo-100 text-indigo-700'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-200'"
                            >
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                    {{ wallet.context_type === 'office' ? 'O' : 'P' }}
                                </span>
                                <span class="max-w-[220px] truncate font-medium">{{ wallet.name }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="walletQuery && !filteredWallets.length"
                class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
            >
                No wallet found for "{{ walletQuery }}".
            </div>

            <div
                v-if="!wallets.length"
                class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-12 text-center text-gray-400"
            >
                No petty cash wallets available.
            </div>

            <template v-if="statement">
                <div class="rounded-xl border border-slate-200 bg-white">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ statement.wallet.name }}</h3>
                            <p class="text-sm text-slate-500">Balance Statement</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                v-if="!statement.period.is_locked"
                                @click="openCreateClaim"
                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                            >
                                + Create Claim
                            </button>

                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="statement.period.is_locked ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ statement.period.is_locked ? 'LOCKED' : 'OPEN' }}
                            </span>
                        </div>
                    </div>

                    <div class="border-b border-slate-200 p-3">
                        <div class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                            <button
                                @click="prevMonth"
                                class="rounded px-2 py-1 text-sm font-medium text-slate-600 hover:bg-white hover:text-slate-900"
                            >
                                &larr; Previous
                            </button>

                            <div class="text-sm font-semibold text-slate-900">{{ monthLabel }}</div>

                            <button
                                @click="nextMonth"
                                class="rounded px-2 py-1 text-sm font-medium text-slate-600 hover:bg-white hover:text-slate-900"
                            >
                                Next &rarr;
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 border-b border-slate-200 p-4 md:grid-cols-4">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Opening</div>
                            <div class="mt-1 text-lg font-semibold text-slate-900">{{ formatCurrency(statement.summary.opening) }}</div>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total In</div>
                            <div class="mt-1 text-lg font-semibold text-emerald-700">{{ formatCurrency(statement.summary.total_in) }}</div>
                        </div>
                        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-red-700">Total Out</div>
                            <div class="mt-1 text-lg font-semibold text-red-700">{{ formatCurrency(statement.summary.total_out) }}</div>
                        </div>
                        <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Closing</div>
                            <div class="mt-1 text-lg font-semibold text-indigo-700">{{ formatCurrency(statement.summary.closing) }}</div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-100 text-slate-700">
                                <tr>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-left">Transaction No</th>
                                    <th class="px-4 py-3 text-left">Reference No</th>
                                    <th class="px-4 py-3 text-left">Title</th>
                                    <th class="px-4 py-3 text-left">Description</th>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-right">In</th>
                                    <th class="px-4 py-3 text-right">Out</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="tx in statement.transactions"
                                    :key="tx.id"
                                    class="border-t border-slate-200 hover:bg-slate-50"
                                >
                                    <td class="px-4 py-2 text-slate-700">{{ formatDate(tx.date) }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-slate-700">{{ tx.code ?? '-' }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-slate-700">{{ tx.reference ?? '-' }}</td>
                                    <td class="px-4 py-2 text-slate-700">{{ tx.title ?? '-' }}</td>
                                    <td class="px-4 py-2 text-slate-500">{{ tx.description ?? '-' }}</td>
                                    <td class="px-4 py-2 capitalize text-slate-700">{{ tx.type }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-emerald-700">
                                        {{ tx.amount_in ? formatCurrency(tx.amount_in) : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium text-red-700">
                                        {{ tx.amount_out ? formatCurrency(tx.amount_out) : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold text-slate-900">
                                        {{ formatCurrency(tx.balance_after) }}
                                    </td>
                                </tr>

                                <tr v-if="!statement.transactions.length">
                                    <td colspan="9" class="px-4 py-10 text-center text-slate-400">
                                        No transactions recorded for this month.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <CreateClaimModal
                    :show="showCreateClaim"
                    :wallet="statement.wallet"
                    :claim-types="statement.claimTypes"
                    @close="closeCreateClaim"
                    @success="router.reload({ preserveScroll: true })"
                />
            </template>
        </div>
    </AuthenticatedLayout>
</template>
