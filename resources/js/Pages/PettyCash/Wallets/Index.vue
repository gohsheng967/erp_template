<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
    wallets: {
        type: Array,
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
                    Balance Statements
                </h2>
                <Link
                    :href="route('petty-cash.wallets.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    ← Back
                </Link>
            </div>
        </template>

        <div class="p-6 space-y-6">

            <!-- ================= WALLET LIST ================= -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <div
                    v-for="wallet in wallets"
                    :key="wallet.id"
                    class="bg-white rounded-lg border shadow-sm p-5 flex flex-col justify-between"
                >
                    <!-- INFO -->
                    <div>
                        <div class="text-sm text-gray-500">
                            {{ wallet.context_type === 'office'
                                ? 'Office Wallet'
                                : 'Project Wallet'
                            }}
                        </div>

                        <h3 class="mt-1 text-lg font-semibold text-gray-800">
                            {{ wallet.name }}
                        </h3>

                        <div class="mt-3 text-sm text-gray-500">
                            Current Balance
                        </div>

                        <div class="text-2xl font-semibold">
                            {{ Number(wallet.current_balance).toFixed(2) }}
                        </div>

                        <!-- LATEST STATEMENT -->
                        <div
                            v-if="wallet.latest_statement"
                            class="mt-3 flex items-center gap-2 text-xs"
                        >
                            <span class="text-gray-500">
                                Latest:
                                {{ wallet.latest_statement.month }}
                            </span>

                            <span
                                class="px-2 py-0.5 rounded-full"
                                :class="wallet.latest_statement.is_locked
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-yellow-100 text-yellow-700'"
                            >
                                {{ wallet.latest_statement.is_locked ? 'LOCKED' : 'OPEN' }}
                            </span>
                        </div>
                    </div>

                    <!-- ACTION -->
                    <div class="mt-5">
                        <Link
                            :href="route('petty-cash.wallets.show', wallet.uuid)"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                        >
                            <i class="mdi mdi-file-document-outline mr-2"></i>
                            View Statement
                        </Link>
                    </div>
                </div>

            </div>

            <!-- EMPTY STATE -->
            <div
                v-if="!wallets.length"
                class="text-center text-gray-400 py-12"
            >
                No petty cash wallets available.
            </div>

        </div>
    </AuthenticatedLayout>
</template>
