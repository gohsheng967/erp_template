<script setup>
import { computed, ref } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();
const project = page.props.project;

const clients = page.props.clients;
const managers = page.props.managers;
const sites = page.props.sites ?? [];
const branchManager = page.props.branchManager;

const form = useForm({
    name: project.name,
    code: project.code,
    client_id: project.client_id,
    start_date: project.start_date,
    end_date: project.end_date,
    extension_date: project.extension_date ?? "",
    budget: project.budget,
    project_value: project.project_value ?? "",
    manager_id: project.manager_id ?? "",
    site_ids: page.props.selectedSiteIds ?? [],
    description: project.description,
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

const siteSearch = ref("");

const selectedSiteIds = computed(() =>
    (form.site_ids ?? []).map((id) => Number(id)).filter((id) => Number.isFinite(id))
);

const selectedSiteIdSet = computed(() => new Set(selectedSiteIds.value));

const filteredSites = computed(() => {
    const keyword = siteSearch.value.trim().toLowerCase();

    if (!keyword) {
        return sites;
    }

    return sites.filter((site) =>
        String(site.site_name ?? "").toLowerCase().includes(keyword)
    );
});

const selectedSites = computed(() =>
    sites.filter((site) => selectedSiteIdSet.value.has(Number(site.id)))
);

function isSiteSelected(siteId) {
    return selectedSiteIdSet.value.has(Number(siteId));
}

function toggleSite(siteId) {
    const normalizedId = Number(siteId);
    const next = new Set(selectedSiteIdSet.value);

    if (next.has(normalizedId)) {
        next.delete(normalizedId);
    } else {
        next.add(normalizedId);
    }

    form.site_ids = Array.from(next);
}

function removeSite(siteId) {
    form.site_ids = selectedSiteIds.value.filter((id) => id !== Number(siteId));
}

function clearSites() {
    form.site_ids = [];
}

function selectAllFilteredSites() {
    const next = new Set(selectedSiteIds.value);
    filteredSites.value.forEach((site) => next.add(Number(site.id)));
    form.site_ids = Array.from(next);
}
</script>

<template>
    <Head title="Edit Project" />

    <AuthenticatedLayout>

        <div class="p-6 max-w-5xl mx-auto">

            <!-- HEADER -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-semibold text-gray-800">Edit Project</h2>

                <Link
                    :href="route('projects.index')"
                    class="rounded-md bg-gray-200 px-3 py-1.5 text-xs font-semibold hover:bg-gray-300"
                >
                    Back
                </Link>
            </div>

            <!-- FORM -->
            <div class="bg-white shadow rounded-lg p-6 space-y-6">

                <!-- GRID -->
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
                            Project Code
                        </label>
                        <input
                            v-model="form.code"
                            type="text"
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
                                <div class="flex h-9 overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                                    <span class="inline-flex items-center border-r border-slate-200 bg-slate-50 px-3 text-xs font-medium text-slate-500">
                                        RM
                                    </span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        inputmode="decimal"
                                        v-model="form.budget"
                                        class="!h-full w-full !border-0 !rounded-none !bg-transparent !px-2.5 !text-xs focus:!ring-0"
                                        placeholder="0.00"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">
                                    Project Value
                                </label>
                                <div class="flex h-9 overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                                    <span class="inline-flex items-center border-r border-slate-200 bg-slate-50 px-3 text-xs font-medium text-slate-500">
                                        RM
                                    </span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        inputmode="decimal"
                                        v-model="form.project_value"
                                        class="!h-full w-full !border-0 !rounded-none !bg-transparent !px-2.5 !text-xs focus:!ring-0"
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

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">
                            Sites
                        </label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 space-y-3">
                            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <input
                                    v-model="siteSearch"
                                    type="text"
                                    placeholder="Search site..."
                                    class="w-full rounded border px-3 py-1.5 text-sm md:max-w-sm"
                                />
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded border bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                                        @click="selectAllFilteredSites"
                                    >
                                        Select Filtered
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded border bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                                        @click="clearSites"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <div class="max-h-40 overflow-y-auto rounded border bg-white">
                                <label
                                    v-for="site in filteredSites"
                                    :key="site.id"
                                    class="flex cursor-pointer items-center justify-between gap-3 border-b px-3 py-2 text-sm last:border-b-0 hover:bg-gray-50"
                                >
                                    <span class="truncate">{{ site.site_name }}</span>
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                                        :checked="isSiteSelected(site.id)"
                                        @change="toggleSite(site.id)"
                                    />
                                </label>
                                <div v-if="!filteredSites.length" class="px-3 py-3 text-xs text-gray-500">
                                    No sites match your search.
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="site in selectedSites"
                                    :key="site.id"
                                    class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700"
                                >
                                    {{ site.site_name }}
                                    <button
                                        type="button"
                                        class="rounded-full px-1 text-indigo-700 hover:bg-indigo-100"
                                        @click="removeSite(site.id)"
                                    >
                                        ×
                                    </button>
                                </span>
                                <span v-if="!selectedSites.length" class="text-xs text-gray-500">
                                    No site selected.
                                </span>
                            </div>
                        </div>
                        <div v-if="form.errors.site_ids" class="mt-1 text-xs text-red-500">
                            {{ form.errors.site_ids }}
                        </div>
                    </div>

                </div>

                <!-- Description -->
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
                        @click="form.put(route('projects.update', project.id))"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-indigo-700"
                        :disabled="form.processing"
                    >
                        Save Changes
                    </button>
                </div>

            </div>
        </div>

    </AuthenticatedLayout>
</template>
