<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    wallets: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
})

const totalBalance = computed(() =>
    Number(props.wallets.reduce((sum, w) => sum + Number(w.current_balance ?? 0), 0)).toFixed(2)
)
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Petty Cash</h2>
                    <p class="text-sm text-gray-500">Manage claims, top-ups and wallet statements in one place.</p>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-xl border border-red-100 bg-red-50 p-4">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-red-700">
                        <i class="mdi mdi-cash-minus text-base"></i>
                        This Month Usage
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-red-700">
                        {{ Number(stats.usage_this_month).toFixed(2) }}
                    </div>
                </div>

                <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                        <i class="mdi mdi-cash-plus text-base"></i>
                        This Month Top-Up
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-emerald-700">
                        {{ Number(stats.topup_this_month).toFixed(2) }}
                    </div>
                </div>

                <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                        <i class="mdi mdi-wallet-outline text-base"></i>
                        Total Balance
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-indigo-700">
                        {{ totalBalance }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Link
                    :href="route('petty-cash.claims.index')"
                    class="group rounded-xl border bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Petty Cash Claims</div>
                            <p class="mt-1 text-xs text-gray-500">
                                Review and track petty cash claim workflow separately from regular claims.
                            </p>
                        </div>
                        <i class="mdi mdi-file-document-outline text-xl text-indigo-600"></i>
                    </div>
                    <div class="mt-4 inline-flex items-center text-xs font-semibold text-indigo-600 group-hover:text-indigo-700">
                        Open Claims
                        <i class="mdi mdi-arrow-right ml-1"></i>
                    </div>
                </Link>

                <Link
                    :href="route('petty-cash.topups.index')"
                    class="group rounded-xl border bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Top-Up Request</div>
                            <p class="mt-1 text-xs text-gray-500">
                                Request additional petty cash funding with review and payment tracking.
                            </p>
                        </div>
                        <i class="mdi mdi-cash-plus text-xl text-emerald-600"></i>
                    </div>
                    <div class="mt-4 inline-flex items-center text-xs font-semibold text-emerald-600 group-hover:text-emerald-700">
                        Open Top-Ups
                        <i class="mdi mdi-arrow-right ml-1"></i>
                    </div>
                </Link>

                <Link
                    :href="route('petty-cash.wallets.index')"
                    class="group rounded-xl border bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Balance Statement</div>
                            <p class="mt-1 text-xs text-gray-500">
                                View wallet transactions and reconcile monthly petty cash balances.
                            </p>
                        </div>
                        <i class="mdi mdi-wallet-outline text-xl text-slate-700"></i>
                    </div>
                    <div class="mt-4 inline-flex items-center text-xs font-semibold text-slate-700 group-hover:text-slate-900">
                        View Wallets
                        <i class="mdi mdi-arrow-right ml-1"></i>
                    </div>
                </Link>
            </div>

            <div class="rounded-xl border bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">Active Wallet Snapshot</h3>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                        {{ wallets.length }} wallet(s)
                    </span>
                </div>

                <div v-if="wallets.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                    <div
                        v-for="wallet in wallets"
                        :key="wallet.id"
                        class="rounded-lg border border-slate-200 bg-slate-50 p-3"
                    >
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ wallet.context_type === 'office' ? 'Office Wallet' : 'Project Wallet' }}
                        </div>
                        <div class="mt-1 text-lg font-semibold text-slate-900">
                            {{ Number(wallet.current_balance).toFixed(2) }}
                        </div>
                        <div class="mt-2 text-xs text-slate-500">
                            Current Balance
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                    No active petty cash wallets available.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
