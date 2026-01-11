<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue"
import { Link } from "@inertiajs/vue3"

defineProps({
    wallets: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <AuthenticatedLayout>
        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">
                    Petty Cash
                </h2>
            </div>
        </template>

        <div class="p-6 space-y-6">

            <!-- ================= STATS ================= -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <!-- THIS MONTH USAGE -->
                <div class="bg-white rounded-lg border p-4 shadow-sm">
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <i class="mdi mdi-cash-minus text-red-500"></i>
                        This Month Usage
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-red-600">
                        {{ Number(stats.usage_this_month).toFixed(2) }}
                    </div>
                </div>

                <!-- THIS MONTH TOP-UP -->
                <div class="bg-white rounded-lg border p-4 shadow-sm">
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <i class="mdi mdi-cash-plus text-green-600"></i>
                        This Month Top-Up
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-green-600">
                        {{ Number(stats.topup_this_month).toFixed(2) }}
                    </div>
                </div>

                <!-- TOTAL BALANCE -->
                <div class="bg-white rounded-lg border p-4 shadow-sm">
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <i class="mdi mdi-wallet-outline text-indigo-600"></i>
                        Total Balance
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{
                            wallets.reduce(
                                (sum, w) => sum + Number(w.current_balance),
                                0
                            ).toFixed(2)
                        }}
                    </div>
                </div>

            </div>

            <!-- ================= WALLET SUMMARY ================= -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div
                    v-for="wallet in wallets"
                    :key="wallet.id"
                    class="bg-white rounded-lg border shadow-sm p-4"
                >
                    <div class="text-sm text-gray-500">
                        {{ wallet.context_type === 'office'
                            ? 'Office Wallet'
                            : 'Project Wallet'
                        }}
                    </div>

                    <div class="mt-2 text-2xl font-semibold">
                        {{ Number(wallet.current_balance).toFixed(2) }}
                    </div>

                    <div class="text-xs text-gray-400">
                        Current Balance
                    </div>
                </div>
            </div>

            <!-- ================= MAIN ACTIONS ================= -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- TOP-UP CARD -->
                <div class="bg-white rounded-lg border shadow p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <i class="mdi mdi-cash-plus text-2xl text-green-600"></i>
                            <h3 class="text-lg font-semibold">
                                Top-Up Request
                            </h3>
                        </div>

                        <p class="mt-3 text-sm text-gray-500">
                            Request additional petty cash funding.
                            Approval is required before finance releases cash.
                        </p>
                    </div>

                    <div class="mt-6">
                        <Link
                            :href="route('petty-cash.topups.index')"
                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                        >
                            <i class="mdi mdi-arrow-right mr-2"></i>
                            Go to Top-Up
                        </Link>
                    </div>
                </div>

                <!-- BALANCE STATEMENT CARD -->
                <div class="bg-white rounded-lg border shadow p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <i class="mdi mdi-wallet-outline text-2xl text-indigo-600"></i>
                            <h3 class="text-lg font-semibold">
                                Balance Statement
                            </h3>
                        </div>

                        <p class="mt-3 text-sm text-gray-500">
                            View transactions, upload monthly cut-off statements,
                            and reconcile petty cash balance.
                        </p>
                    </div>

                    <div class="mt-6">
                        <Link
                            :href="route('petty-cash.wallets.index')"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                        >
                            <i class="mdi mdi-wallet-outline mr-2"></i>
                            View Balance Statement
                        </Link>
                    </div>
                </div>

            </div>

        </div>
    </AuthenticatedLayout>
</template>
