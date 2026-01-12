<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

const attention = computed(() => page.props.attention ?? {
    critical: [],
    warning: [],
})
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <!-- HEADER -->
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- =============================
                     ATTENTION REQUIRED
                ============================== -->
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-800">
                            Attention Required
                        </h3>
                    </div>

                    <div class="p-6 space-y-6">

                        <!-- CRITICAL -->
                        <section v-if="attention.critical.length">
                            <h4 class="mb-3 text-sm font-semibold text-red-600 uppercase tracking-wide">
                                Critical
                            </h4>

                            <ul class="space-y-2">
                                <li
                                    v-for="(item, index) in attention.critical"
                                    :key="'critical-' + index"
                                    class="flex items-center justify-between rounded-md border border-red-100 bg-red-50 px-4 py-3"
                                >
                                    <p class="text-sm text-red-800">
                                        {{ item.message }}
                                    </p>

                                    <Link
                                        :href="route(item.route, item.params ?? {})"
                                        class="text-sm font-medium text-red-700 hover:text-red-900"
                                    >
                                        View
                                    </Link>
                                </li>
                            </ul>
                        </section>

                        <!-- WARNING -->
                        <section v-if="attention.warning.length">
                            <h4 class="mb-3 text-sm font-semibold text-yellow-600 uppercase tracking-wide">
                                Warning
                            </h4>

                            <ul class="space-y-2">
                                <li
                                    v-for="(item, index) in attention.warning"
                                    :key="'warning-' + index"
                                    class="flex items-center justify-between rounded-md border border-yellow-100 bg-yellow-50 px-4 py-3"
                                >
                                    <p class="text-sm text-yellow-800">
                                        {{ item.message }}
                                    </p>

                                    <Link
                                        :href="route(item.route, item.params ?? {})"
                                        class="text-sm font-medium text-yellow-700 hover:text-yellow-900"
                                    >
                                        View
                                    </Link>
                                </li>
                            </ul>
                        </section>

                        <!-- EMPTY -->
                        <div
                            v-if="!attention.critical.length && !attention.warning.length"
                            class="rounded-md border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700"
                        >
                            No pending actions.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
