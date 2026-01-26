<script setup>
import { ref,inject } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateClientModal from "./Partials/CreateClientModal.vue";
import EditClientModal from "./Partials/EditClientModal.vue";
import DeleteConfirmation from "@/Components/DeleteConfirmation.vue";
import { useFormat } from "@/Composables/useFormat";

const toast = inject("toast")
const showCreate = ref(false);
const showEdit = ref(false);
const showDelete = ref(false);
const { formatCurrency } = useFormat();

const selectedClient = ref(null);

function openCreate() {
    showCreate.value = true;
}

function openEdit(client) {
    selectedClient.value = client;
    showEdit.value = true;
}

function openDelete(client) {
    selectedClient.value = client;
    showDelete.value = true;
}

function deleteClient() {
    router.delete(route("clients.destroy", selectedClient.value.uuid), {
        preserveScroll: true,

        onSuccess: () => {
            toast?.value?.show("Client deleted successfully.", "success")

            showDelete.value = false
            selectedClient.value = null
        },

        onError: (errors) => {
            if (errors.delete) {
                toast?.value?.show(errors.delete, "error")
            }
        },
    })
}

const page = usePage();

const clients = page.props.clients;
const filters = page.props.filters ?? {};

// UI State
const search = ref(filters.search ?? "");
const statusFilter = ref(filters.status ?? "");

// Apply Filters
function applyFilters() {
    router.get(route("clients.index"), {
        search: search.value,
    });
}

function resetFilters() {
    search.value = "";
    statusFilter.value = "";
    applyFilters();
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- PAGE HEADER -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">Client Management</h2>
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                    @click="openCreate"
                >
                    + Create Client
                </button>

            </div>
        </template>

        <!-- PAGE CONTENT -->
        <div class="p-6 space-y-6">

            <!-- FILTERS -->
            <div class="bg-white p-4 rounded-lg shadow w-full border">
                <div class="flex flex-wrap gap-4 items-end">

                    <!-- SEARCH -->
                    <div class="flex flex-col w-full md:w-1/3">
                        <label class="block text-sm font-medium text-gray-700">
                            Search
                        </label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Client Name / Email / Company"
                            class="border rounded-md px-3 py-2 w-full"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- APPLY -->
                    <button
                        class="px-4 py-2 h-10 bg-gray-200 rounded hover:bg-gray-300"
                        @click="applyFilters"
                    >
                        Apply
                    </button>

                    <!-- RESET -->
                    <button
                        class="px-4 py-2 h-10 bg-red-200 rounded hover:bg-red-300"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- CLIENTS TABLE -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Client
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Company
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Email
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Phone
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                Outstanding (AR)
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="client in clients.data" :key="client.uuid">

                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                {{ client.name }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ client.company_name ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ client.email ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ client.phone ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-sm text-right tabular-nums">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="{
                                        'bg-red-100 text-red-700':
                                            Number(client.ar_total ?? 0) - Number(client.ar_received ?? 0) > 0,
                                        'bg-emerald-100 text-emerald-700':
                                            Number(client.ar_total ?? 0) - Number(client.ar_received ?? 0) <= 0,
                                    }"
                                >
                                    {{
                                        formatCurrency(
                                            Math.max(
                                                Number(client.ar_total ?? 0) -
                                                Number(client.ar_received ?? 0),
                                                0
                                            )
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- ACTIONS -->
                            <td class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-3">

                                    <!-- VIEW -->
                                    <Link
                                        :href="route('clients.show', client.uuid)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                        title="View"
                                    >
                                        <i class="mdi mdi-eye-outline text-lg"></i>
                                    </Link>

                                    <!-- EDIT -->
                                    <button
                                        class="text-blue-600 hover:text-blue-800"
                                        title="Edit"
                                        @click="openEdit(client)"
                                    >
                                        <i class="mdi mdi-pencil-outline text-lg"></i>
                                    </button>

                                    <!-- DELETE -->
                                    <button
                                        class="text-red-600 hover:text-red-800"
                                        title="Delete"
                                        @click="openDelete(client)"
                                    >
                                        <i class="mdi mdi-delete-outline text-lg"></i>
                                    </button>

                                </div>
                            </td>

                        </tr>

                        <!-- EMPTY STATE -->
                        <tr v-if="clients.data.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                No clients found.
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div class="mt-4 flex gap-1">
                <Link
                    v-for="link in clients.links"
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

    <CreateClientModal
        v-if="showCreate"
        @close="showCreate = false"
    />

    <EditClientModal
        v-if="showEdit"
        :client="selectedClient"
        @close="showEdit = false"
    />

    <DeleteConfirmation
        v-if="showDelete"
        title="Delete Client"
        message="Are you sure you want to delete this client?"
        @confirm="deleteClient"
        @close="showDelete = false"
    />


</template>
