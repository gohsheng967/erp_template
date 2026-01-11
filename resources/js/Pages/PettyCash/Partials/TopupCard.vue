<script setup>
import { Link } from "@inertiajs/vue3"
</script>

<template>
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
</template>
