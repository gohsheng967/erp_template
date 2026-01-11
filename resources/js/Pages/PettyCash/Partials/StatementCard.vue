<script setup>
import { Link } from "@inertiajs/vue3"
</script>

<template>
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
                :href="route('petty-cash.wallets.show', wallets[0].id)"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
            >
                <i class="mdi mdi-file-document-outline mr-2"></i>
                View Statement
            </Link>
        </div>
    </div>
</template>
