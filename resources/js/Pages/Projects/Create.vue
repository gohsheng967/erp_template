<script setup>
import { computed } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();
const clients = page.props.clients;
const managers = page.props.managers;
const branchManager = page.props.branchManager;

const form = useForm({
    name: "",
    code: "",
    client_id: "",
    start_date: "",
    end_date: "",
    extension_date: "",
    budget: "",
    project_value: "",
    manager_id: "",
    description: "",
});

const currencyFormatter = new Intl.NumberFormat("en-MY", {
    style: "currency",
    currency: "MYR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function toAmount(value) {
    const parsed = Number(value ?? 0);
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatAmount(value) {
    return currencyFormatter.format(toAmount(value));
}

const budgetAmount = computed(() => toAmount(form.budget));
const projectValueAmount = computed(() => toAmount(form.project_value));
const valueVariance = computed(() => projectValueAmount.value - budgetAmount.value);
const varianceTone = computed(() =>
    valueVariance.value >= 0
        ? "text-emerald-700 bg-emerald-50 border-emerald-200"
        : "text-rose-700 bg-rose-50 border-rose-200"
);
const varianceLabel = computed(() =>
    valueVariance.value >= 0 ? "Projected Surplus" : "Budget Shortfall"
);
</script>

<template>
    <Head title="Create Project" />

    <AuthenticatedLayout>

        <div class="p-6 max-w-5xl mx-auto">

            <!-- PAGE HEADER -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Create New Project</h2>

                <Link
                    :href="route('projects.index')"
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                    Back
                </Link>
            </div>

            <!-- FORM CARD -->
            <div class="bg-white shadow rounded-lg p-6 space-y-6">

                <!-- FORM GRID -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Project Name -->
                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Project Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- Project Code -->
                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Project Code / Contract Code
                        </label>
                        <input
                            v-model="form.code"
                            type="text"
                            placeholder=""
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- Client -->
                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Client
                        </label>
                        <select
                            v-model="form.client_id"
                            class="w-full border rounded px-3 py-2"
                        >
                            <option value="">Select Client</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Start Date
                        </label>
                        <input
                            type="date"
                            v-model="form.start_date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- End Date -->
                    <div>
                        <label class="block text-sm font-medium mb-1">
                            End Date
                        </label>
                        <input
                            type="date"
                            v-model="form.end_date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- Extension Date -->
                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Extension Date
                        </label>
                        <input
                            type="date"
                            v-model="form.extension_date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- Accounting Snapshot -->
                    <div class="md:col-span-2 border border-slate-200 rounded-lg p-4 bg-slate-50 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-800 tracking-wide uppercase">
                                Accounting
                            </h3>
                            <span class="text-xs text-slate-500">
                                Project Value vs Budget
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">
                                    Budget
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 text-sm">
                                        RM
                                    </span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        inputmode="decimal"
                                        v-model="form.budget"
                                        class="w-full border rounded pl-12 pr-3 py-2 bg-white"
                                        placeholder="0.00"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">
                                    Project Value
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 text-sm">
                                        RM
                                    </span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        inputmode="decimal"
                                        v-model="form.project_value"
                                        class="w-full border rounded pl-12 pr-3 py-2 bg-white"
                                        placeholder="0.00"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                            <div class="border border-slate-200 rounded-md bg-white p-3">
                                <div class="text-slate-500">Project Value</div>
                                <div class="font-semibold text-slate-800">
                                    {{ formatAmount(projectValueAmount) }}
                                </div>
                            </div>
                            <div class="border border-slate-200 rounded-md bg-white p-3">
                                <div class="text-slate-500">Budget</div>
                                <div class="font-semibold text-slate-800">
                                    {{ formatAmount(budgetAmount) }}
                                </div>
                            </div>
                            <div class="border rounded-md p-3" :class="varianceTone">
                                <div>{{ varianceLabel }}</div>
                                <div class="font-semibold">
                                    {{ formatAmount(valueVariance) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Branch Manager -->
                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Branch Manager (GM)
                        </label>
                        <input
                            type="text"
                            :value="branchManager?.name ?? 'No General Manager configured for this branch'"
                            disabled
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- Project Manager -->
                    <div>
                        <label class="block text-sm font-medium mb-1">
                            Project Manager
                        </label>
                        <select
                            v-model="form.manager_id"
                            class="w-full border rounded px-3 py-2"
                        >
                            <option value="">Select Project Manager</option>
                            <option v-for="m in managers" :key="m.id" :value="m.id">
                                {{ m.name }}
                            </option>
                        </select>
                    </div>

                </div>

                <!-- DESCRIPTION -->
                <div>
                    <label class="block text-sm font-medium mb-1">
                        Description
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="w-full border rounded px-3 py-2"
                    ></textarea>
                </div>

                <!-- SUBMIT -->
                <div class="flex justify-end">
                    <button
                        @click="form.post(route('projects.store'))"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                        :disabled="form.processing"
                    >
                        Create Project
                    </button>
                </div>

            </div>
        </div>

    </AuthenticatedLayout>
</template>
