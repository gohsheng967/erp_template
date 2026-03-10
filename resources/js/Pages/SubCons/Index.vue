<script setup>
import { ref, inject, computed } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateSubConModal from "./Partials/CreateSubConModal.vue";
import EditSubConModal from "./Partials/EditSubConModal.vue";
import DeleteConfirmation from "@/Components/DeleteConfirmation.vue";

const toast = inject("toast");
const showCreate = ref(false);
const showEdit = ref(false);
const showDelete = ref(false);

const selectedSubCon = ref(null);

function openCreate() {
    showCreate.value = true;
}

function openEdit(subCon) {
    selectedSubCon.value = subCon;
    showEdit.value = true;
}

function openDelete(subCon) {
    selectedSubCon.value = subCon;
    showDelete.value = true;
}

function deleteSubCon() {
    router.delete(route("sub-cons.destroy", selectedSubCon.value.uuid), {
        preserveScroll: true,

        onSuccess: () => {
            toast?.value?.show("Sub Con deleted successfully.", "success");

            showDelete.value = false;
            selectedSubCon.value = null;
            refreshList();
        },

        onError: (errors) => {
            if (errors.delete) {
                toast?.value?.show(errors.delete, "error");
            }
        },
    });
}

const page = usePage();

const subCons = computed(() => page.props.subCons);
const filters = computed(() => page.props.filters ?? {});

const search = ref(filters.value.search ?? "");

function applyFilters() {
    router.get(route("sub-cons.index"), {
        search: search.value,
    });
}

function resetFilters() {
    search.value = "";
    applyFilters();
}

function refreshList() {
    router.reload({
        only: ["subCons"],
        preserveScroll: true,
    });
}

function bankSummary(subCon) {
    const accounts = Array.isArray(subCon.bank_accounts) ? subCon.bank_accounts : [];
    if (!accounts.length) return subCon.bank ?? "-";

    const [first] = accounts;
    const firstLabel = [first.bank_name, first.account_no].filter(Boolean).join(" - ");

    if (accounts.length === 1) {
        return firstLabel || "-";
    }

    return `${firstLabel} (+${accounts.length - 1} more)`;
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">Sub Con Management</h2>
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                    @click="openCreate"
                >
                    + Create Sub Con
                </button>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <div class="bg-white p-4 rounded-lg shadow w-full border">
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="flex flex-col w-full md:w-1/3">
                        <label class="block text-sm font-medium text-gray-700">
                            Search
                        </label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Name / Email / Company / Phone / Bank Account"
                            class="border rounded-md px-3 py-2 w-full"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <button
                        class="px-4 py-2 h-10 bg-gray-200 rounded hover:bg-gray-300"
                        @click="applyFilters"
                    >
                        Apply
                    </button>

                    <button
                        class="px-4 py-2 h-10 bg-red-200 rounded hover:bg-red-300"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Name
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Bank Accounts
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="subCon in subCons.data" :key="subCon.uuid">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                {{ subCon.name }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ subCon.company_name ?? "-" }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ subCon.email ?? "-" }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ subCon.phone ?? "-" }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{ bankSummary(subCon) }}
                            </td>

                            <td class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-3">
                                    <Link
                                        :href="route('sub-cons.show', subCon.uuid)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                        title="View"
                                    >
                                        <i class="mdi mdi-eye-outline text-lg"></i>
                                    </Link>

                                    <button
                                        class="text-blue-600 hover:text-blue-800"
                                        title="Edit"
                                        @click="openEdit(subCon)"
                                    >
                                        <i class="mdi mdi-pencil-outline text-lg"></i>
                                    </button>

                                    <button
                                        class="text-red-600 hover:text-red-800"
                                        title="Delete"
                                        @click="openDelete(subCon)"
                                    >
                                        <i class="mdi mdi-delete-outline text-lg"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="subCons.data.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                No sub cons found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex gap-1">
                <Link
                    v-for="link in subCons.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="px-3 py-1 rounded border text-sm"
                    :class="{
                        'bg-indigo-600 text-white': link.active,
                        'text-gray-500 cursor-not-allowed': !link.url,
                    }"
                    :disabled="!link.url"
                />
            </div>
        </div>
    </AuthenticatedLayout>

    <CreateSubConModal
        v-if="showCreate"
        @close="showCreate = false"
        @saved="refreshList"
    />

    <EditSubConModal
        v-if="showEdit"
        :subCon="selectedSubCon"
        @close="showEdit = false"
        @saved="refreshList"
    />

    <DeleteConfirmation
        v-if="showDelete"
        title="Delete Sub Con"
        message="Are you sure you want to delete this sub con?"
        @confirm="deleteSubCon"
        @close="showDelete = false"
    />
</template>
