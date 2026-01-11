<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import CreateClaimModal from './Partials/CreateClaimModal.vue'

/* =========================
   PROPS
========================= */
const props = defineProps({
    wallet: Object,
    period: Object,
    summary: Object,
    transactions: Array,
    canLock: Boolean,
    claimTypes: Object,
})

/* =========================
   FORMAT
========================= */
const { capitalize, formatDate } = useFormat()
const monthLabel = computed(() => props.period.month)

/* =========================
   MONTH NAV
========================= */
function shiftMonth(month, diff) {
    const [y, m] = month.split('-').map(Number)
    const d = new Date(y, m - 1 + diff, 1)
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function prevMonth() {
    router.get(
        route('petty-cash.wallets.show', props.wallet.uuid),
        { month: shiftMonth(props.period.month, -1) },
        { preserveScroll: true }
    )
}

function nextMonth() {
    router.get(
        route('petty-cash.wallets.show', props.wallet.uuid),
        { month: shiftMonth(props.period.month, 1) },
        { preserveScroll: true }
    )
}

/* =========================
   LOCK
========================= */
function lockMonth() {
    if (!confirm('Lock this month? This action cannot be undone.')) return

    router.post(
        route('petty-cash.statements.lock', props.wallet.uuid),
        { month: props.period.month }
    )
}

/* =========================
   CREATE CLAIM MODAL
========================= */
const showCreateClaim = ref(false)

function openCreateClaim() {
    showCreateClaim.value = true
}

function closeCreateClaim() {
    showCreateClaim.value = false
    claimForm.reset()
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('petty-cash.wallets.index')"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        ← Back
                    </Link>

                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">
                            {{ wallet.name }}
                        </h2>
                        <p class="text-sm text-gray-500">
                            Balance Statement
                        </p>
                    </div>
                </div>

                <span
                    class="px-3 py-1 text-xs rounded-full"
                    :class="period.is_locked
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700'"
                >
                    {{ period.is_locked ? 'LOCKED' : 'OPEN' }}
                </span>
            </div>
        </template>

        <div class="p-6 space-y-6">

            <!-- ================= PERIOD ================= -->
            <div class="flex items-center justify-between bg-white p-4 rounded border">
                <button @click="prevMonth" class="text-sm text-gray-600 hover:text-gray-900">
                    ◀ Previous
                </button>

                <div class="text-sm font-semibold">
                    {{ monthLabel }}
                </div>

                <button @click="nextMonth" class="text-sm text-gray-600 hover:text-gray-900">
                    Next ▶
                </button>
            </div>

            <!-- ================= SUMMARY ================= -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded border">
                    <div class="text-sm text-gray-500">Opening</div>
                    <div class="text-lg font-semibold">
                        {{ summary.opening.toFixed(2) }}
                    </div>
                </div>

                <div class="bg-white p-4 rounded border">
                    <div class="text-sm text-gray-500">Total In</div>
                    <div class="text-lg font-semibold text-green-600">
                        +{{ summary.total_in.toFixed(2) }}
                    </div>
                </div>

                <div class="bg-white p-4 rounded border">
                    <div class="text-sm text-gray-500">Total Out</div>
                    <div class="text-lg font-semibold text-red-600">
                        -{{ summary.total_out.toFixed(2) }}
                    </div>
                </div>

                <div class="bg-white p-4 rounded border">
                    <div class="text-sm text-gray-500">Closing</div>
                    <div class="text-lg font-semibold">
                        {{ summary.closing.toFixed(2) }}
                    </div>
                </div>
            </div>

            <!-- ================= LEDGER ================= -->
            <div class="bg-white rounded-lg border overflow-x-auto shadow-sm">

                <!-- HEADER -->
                <div class="flex justify-between items-center px-4 py-3 border-b bg-gray-50">
                    <div class="text-sm text-gray-600">
                        Ledger for <span class="font-semibold">{{ monthLabel }}</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            v-if="!period.is_locked"
                            @click="openCreateClaim"
                            class="px-3 py-1.5 text-sm rounded bg-indigo-600 text-white hover:bg-indigo-700"
                        >
                            + Create Claim
                        </button>

                        <span
                            class="px-2.5 py-1 text-xs rounded-full font-medium"
                            :class="period.is_locked
                                ? 'bg-green-100 text-green-700'
                                : 'bg-yellow-100 text-yellow-700'"
                        >
                            {{ period.is_locked ? 'LOCKED' : 'OPEN' }}
                        </span>
                    </div>
                </div>

                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Transaction No</th>
                            <th class="px-4 py-3 text-left">Reference No</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-right">In</th>
                            <th class="px-4 py-3 text-right">Out</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="tx in transactions"
                            :key="tx.id"
                            class="border-t even:bg-gray-50 hover:bg-indigo-50/40"
                        >
                            <td class="px-4 py-2">{{ formatDate(tx.date) }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ tx.code ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ tx.reference ?? '—' }}</td>
                            <td class="px-4 py-2 capitalize">{{ tx.type }}</td>
                            <td class="px-4 py-2 text-right text-green-600">
                                {{ tx.amount_in ? tx.amount_in.toFixed(2) : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right text-red-600">
                                {{ tx.amount_out ? tx.amount_out.toFixed(2) : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right font-semibold">
                                {{ tx.balance_after.toFixed(2) }}
                            </td>
                        </tr>

                        <tr v-if="!transactions.length">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                No transactions recorded for this month
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ================= ACTION ================= -->
            <div class="flex justify-end">
                <button
                    v-if="canLock && !period.is_locked"
                    @click="lockMonth"
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                >
                    Lock Month
                </button>
            </div>
        </div>

        <CreateClaimModal
            :show="showCreateClaim"
            :wallet="wallet"
            :claim-types="claimTypes"
            @close="closeCreateClaim"
            @success="router.reload({ preserveScroll: true })"
        />

    </AuthenticatedLayout>
</template>
