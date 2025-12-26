<script setup>
import { ref } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();

const projects = page.props.projects;
const filters = page.props.filters;
const clients = page.props.clients;

// UI State
const search = ref(filters.search ?? "");
const statusFilter = ref(filters.status ?? "");
const clientFilter = ref(filters.client ?? "");
const dateFrom = ref(filters.date_from ?? "");
const dateTo = ref(filters.date_to ?? "");

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
            <div class="bg-white p-4 rounded-lg shadow w-full border">

                <div class="flex flex-wrap gap-4 items-end">

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
                            @change="applyFilters"
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
                            @change="applyFilters"
                        >
                            <option value="">All</option>
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- DATE FROM -->
                    <div class="flex flex-col w-40">
                        <label class="block text-sm font-medium text-gray-700">Start From</label>
                        <input
                            type="date"
                            v-model="dateFrom"
                            class="border rounded-md px-3 py-2"
                            @change="applyFilters"
                        />
                    </div>

                    <!-- DATE TO -->
                    <div class="flex flex-col w-40">
                        <label class="block text-sm font-medium text-gray-700">Start To</label>
                        <input
                            type="date"
                            v-model="dateTo"
                            class="border rounded-md px-3 py-2"
                            @change="applyFilters"
                        />
                    </div>

                    <!-- APPLY BUTTON -->
                    <button
                        class="px-4 py-2 h-10 bg-gray-200 rounded hover:bg-gray-300"
                        @click="applyFilters"
                    >
                        Apply
                    </button>

                    <!-- RESET BUTTON -->
                    <button
                        class="px-4 py-2 h-10 bg-red-200 rounded hover:bg-red-300"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- PROJECTS TABLE -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">End</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="prj in projects.data" :key="prj.id">

                            <td class="px-4 py-3 text-sm">
                                {{ prj.code }}
                            </td>

                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                {{ prj.name }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ prj.client?.name ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ prj.start_date ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ prj.end_date ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="px-2 py-1 rounded text-xs"
                                    :class="{
                                        'bg-gray-200 text-gray-700': prj.status === 'draft',
                                        'bg-blue-100 text-blue-700': prj.status === 'active',
                                        'bg-yellow-100 text-yellow-800': prj.status === 'on_hold',
                                        'bg-green-100 text-green-700': prj.status === 'completed',
                                        'bg-red-100 text-red-700': prj.status === 'cancelled'
                                    }"
                                >
                                    {{ $capitalize(prj.status) }}
                                </span>
                            </td>

                            <!-- ACTIONS -->
                            <td class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-3">

                                    <!-- VIEW -->
                                    <Link
                                        :href="route('projects.show', prj.id)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        View
                                    </Link>

                                    <!-- EDIT -->
                                    <Link
                                        :href="route('projects.edit', prj.id)"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        Edit
                                    </Link>

                                </div>
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div class="mt-4 flex gap-1">
                <Link
                    v-for="link in projects.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="px-3 py-1 rounded border text-sm"
                    :class="{
                        'bg-indigo-600 text-white': link.active,
                        'text-gray-500 cursor-not-allowed': !link.url
                    }"
                    :disabled="!link.url"
                />
            </div>

        </div>

    </AuthenticatedLayout>
</template>
