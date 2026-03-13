<script setup>
import { computed, ref } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useFormat } from "@/Composables/useFormat";
import StandardFilterBar from "@/Components/Filters/StandardFilterBar.vue";

const page = usePage();
const { formatCurrency } = useFormat();

const projects = page.props.projects;
const filters = page.props.filters;
const clients = page.props.clients;

// UI State
const search = ref(filters.search ?? "");
const statusFilter = ref(filters.status ?? "");
const clientFilter = ref(filters.client ?? "");
const dateFrom = ref(filters.date_from ?? "");
const dateTo = ref(filters.date_to ?? "");

const kanbanColumns = [
    { key: "incoming", label: "Incoming", headerClass: "bg-slate-100 text-slate-700" },
    { key: "on_going", label: "On Going", headerClass: "bg-blue-100 text-blue-700" },
    { key: "late", label: "Late", headerClass: "bg-amber-100 text-amber-800" },
    { key: "extended", label: "Extended", headerClass: "bg-violet-100 text-violet-700" },
    { key: "finished", label: "Finished", headerClass: "bg-green-100 text-green-700" },
];

const initialColumnLimit = 20;
const visibleCounts = ref(
    Object.fromEntries(kanbanColumns.map((column) => [column.key, initialColumnLimit]))
);

const groupedProjects = computed(() => {
    const groups = Object.fromEntries(kanbanColumns.map((column) => [column.key, []]));

    for (const project of projects ?? []) {
        if (groups[project.status]) {
            groups[project.status].push(project);
        }
    }

    return groups;
});

// Apply Filters
function applyFilters() {
    $inertia.get(route("projects.index"), {
        search: search.value,
        status: statusFilter.value,
        client: clientFilter.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    });
}

function resetFilters() {
    search.value = "";
    statusFilter.value = "";
    clientFilter.value = "";
    dateFrom.value = "";
    dateTo.value = "";

    applyFilters();
}

function formatStatus(value) {
    return String(value ?? "")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function statusClass(status) {
    if (status === "incoming") return "bg-slate-100 text-slate-700";
    if (status === "on_going") return "bg-blue-100 text-blue-700";
    if (status === "late") return "bg-amber-100 text-amber-800";
    if (status === "extended") return "bg-violet-100 text-violet-700";
    return "bg-green-100 text-green-700";
}

function visibleProjects(statusKey) {
    return (groupedProjects.value[statusKey] ?? []).slice(0, visibleCounts.value[statusKey] ?? initialColumnLimit);
}

function canLoadMore(statusKey) {
    return (groupedProjects.value[statusKey] ?? []).length > (visibleCounts.value[statusKey] ?? initialColumnLimit);
}

function loadMore(statusKey) {
    visibleCounts.value[statusKey] = (visibleCounts.value[statusKey] ?? initialColumnLimit) + initialColumnLimit;
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- PAGE HEADER -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">Project Management</h2>

                <Link
                    :href="route('projects.create')"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                >
                    + Create Project
                </Link>
            </div>
        </template>

        <!-- PAGE CONTENT -->
        <div class="p-6 space-y-6">

            <!-- FILTERS -->
            <StandardFilterBar
                title="Filters"
                description="Filter projects by keyword, client, status, and date."
                @apply="applyFilters"
                @reset="resetFilters"
            >

                    <!-- SEARCH -->
                    <div class="flex flex-col w-full md:w-1/3">
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Project Name / Code"
                            class="border rounded-md px-3 py-2 w-full"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- CLIENT -->
                    <div class="flex flex-col w-40">
                        <label class="block text-sm font-medium text-gray-700">Client</label>
                        <select
                            v-model="clientFilter"
                            class="border rounded-md px-3 py-2 w-full"
                        >
                            <option value="">All</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <!-- STATUS -->
                    <div class="flex flex-col w-40">
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select
                            v-model="statusFilter"
                            class="border rounded-md px-3 py-2 w-full"
                        >
                            <option value="">All</option>
                            <option value="incoming">Incoming</option>
                            <option value="on_going">On Going</option>
                            <option value="late">Late</option>
                            <option value="extended">Extended</option>
                            <option value="finished">Finished</option>
                        </select>
                    </div>

                    <!-- DATE FROM -->
                    <div class="flex flex-col w-40">
                        <label class="block text-sm font-medium text-gray-700">Start From</label>
                        <input
                            type="date"
                            v-model="dateFrom"
                            class="border rounded-md px-3 py-2"
                        />
                    </div>

                    <!-- DATE TO -->
                    <div class="flex flex-col w-40">
                        <label class="block text-sm font-medium text-gray-700">Start To</label>
                        <input
                            type="date"
                            v-model="dateTo"
                            class="border rounded-md px-3 py-2"
                        />
                    </div>
            </StandardFilterBar>

            <!-- PROJECTS KANBAN -->
            <div class="overflow-x-auto pb-2">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 min-w-[1120px] xl:min-w-0">
                    <div
                        v-for="column in kanbanColumns"
                        :key="column.key"
                        class="bg-gray-50 border rounded-lg p-3 space-y-3"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="px-2 py-1 rounded text-xs font-semibold"
                                :class="column.headerClass"
                            >
                                {{ column.label }}
                            </span>
                            <span class="text-xs text-gray-500">
                                {{ groupedProjects[column.key]?.length ?? 0 }}
                            </span>
                        </div>

                        <div
                            v-if="!(groupedProjects[column.key]?.length)"
                            class="text-xs text-gray-400 py-4 text-center border border-dashed rounded-md bg-white"
                        >
                            No project
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="prj in visibleProjects(column.key)"
                                :key="prj.id"
                                class="bg-white border rounded-lg p-3 shadow-sm space-y-2"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-500 truncate">{{ prj.code || "-" }}</p>
                                        <p class="text-sm font-semibold text-gray-900 truncate">{{ prj.name }}</p>
                                    </div>
                                    <span class="px-2 py-1 rounded text-[10px] font-medium whitespace-nowrap" :class="statusClass(prj.status)">
                                        {{ formatStatus(prj.status) }}
                                    </span>
                                </div>

                                <p class="text-xs text-gray-600 truncate">Client: {{ prj.client?.name ?? "-" }}</p>
                                <p class="text-xs text-gray-600">Start: {{ prj.start_date ?? "-" }}</p>
                                <p class="text-xs text-gray-600">End: {{ prj.end_date ?? "-" }}</p>
                                <p class="text-xs font-medium text-gray-800">
                                    Value: {{ formatCurrency(prj.project_value ?? 0) }}
                                </p>

                                <div class="flex items-center justify-end gap-3 pt-1">
                                    <Link
                                        :href="route('projects.show', prj.uuid)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                        title="View"
                                    >
                                        <i class="mdi mdi-eye text-[18px] leading-none"></i>
                                    </Link>
                                    <Link
                                        :href="route('projects.edit', prj.uuid)"
                                        class="text-blue-600 hover:text-blue-800"
                                        title="Edit"
                                    >
                                        <i class="mdi mdi-pencil text-[18px] leading-none"></i>
                                    </Link>
                                </div>
                            </div>

                            <button
                                v-if="canLoadMore(column.key)"
                                type="button"
                                class="w-full text-xs px-3 py-2 border border-dashed rounded-md text-gray-600 hover:bg-gray-100"
                                @click="loadMore(column.key)"
                            >
                                Load more
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </AuthenticatedLayout>
</template>
