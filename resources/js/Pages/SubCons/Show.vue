<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    subCon: {
        type: Object,
        required: true,
    },
    taskStats: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const stats = page.props.taskStats ?? {};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">Sub Con Details</h2>
                    <p class="text-sm text-gray-500">
                        {{ subCon.name }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('projects.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm"
                    >
                        <i class="mdi mdi-plus"></i>
                        Add Task
                    </Link>
                    <Link
                        :href="route('sub-cons.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm"
                    >
                        <i class="mdi mdi-arrow-left text-base"></i>
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Total Tasks</div>
                    <div class="text-2xl font-semibold text-gray-800">
                        {{ stats.total ?? 0 }}
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Submitted</div>
                    <div class="text-2xl font-semibold text-amber-700">
                        {{ stats.submitted ?? 0 }}
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Verified</div>
                    <div class="text-2xl font-semibold text-indigo-700">
                        {{ stats.verified ?? 0 }}
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Paid</div>
                    <div class="text-2xl font-semibold text-emerald-700">
                        {{ stats.paid ?? 0 }}
                    </div>
                </div>
            </div>

            <div class="bg-white border rounded-2xl p-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-start gap-6">
                    <div
                        class="w-14 h-14 rounded-xl bg-indigo-100 flex items-center justify-center
                               text-indigo-600 font-bold text-lg shrink-0"
                    >
                        {{ subCon.name?.charAt(0) || "S" }}
                    </div>

                    <div class="flex-1 space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">
                                {{ subCon.name }}
                            </h2>
                        </div>

                        <p class="text-sm text-gray-500">
                            Company:
                            <span class="text-gray-700 font-medium">
                                {{ subCon.company_name || "-" }}
                            </span>
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm mt-4">
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="font-medium text-gray-800 break-all">
                                    {{ subCon.email || "-" }}
                                </p>
                            </div>

                            <div>
                                <p class="text-gray-500">Phone</p>
                                <p class="font-medium text-gray-800">
                                    {{ subCon.phone || "-" }}
                                </p>
                            </div>

                            <div>
                                <p class="text-gray-500">Bank</p>
                                <p class="font-medium text-gray-800">
                                    {{ subCon.bank || "-" }}
                                </p>
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t">
                            <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                                Address
                            </p>
                            <p class="text-sm text-gray-700 whitespace-pre-line">
                                {{ subCon.address || "-" }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>




