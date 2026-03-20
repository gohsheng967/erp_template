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
const portal = ref(filters.value.portal ?? "all");
const bankAccount = ref(filters.value.bank_account ?? "all");
const sort = ref(filters.value.sort ?? "latest");

const totalSubCons = computed(() => subCons.value.total ?? subCons.value.data?.length ?? 0);
const hasAnyFilter = computed(() => {
    return (
        search.value.trim() !== "" ||
        portal.value !== "all" ||
        bankAccount.value !== "all" ||
        sort.value !== "latest"
    );
});

const activeFilters = computed(() => {
    const chips = [];

    if (search.value.trim() !== "") {
        chips.push({
            key: "search",
            label: `Search: ${search.value.trim()}`,
        });
    }

    if (portal.value !== "all") {
        const portalLabel = {
            with_portal: "Portal: Has account",
            active_portal: "Portal: Active login",
            inactive_portal: "Portal: Inactive login",
            no_portal: "Portal: No account",
        }[portal.value];

        if (portalLabel) chips.push({ key: "portal", label: portalLabel });
    }

    if (bankAccount.value !== "all") {
        chips.push({
            key: "bank_account",
            label: bankAccount.value === "yes" ? "Bank account: Added" : "Bank account: Missing",
        });
    }

    if (sort.value !== "latest") {
        const sortLabel = {
            name_asc: "Sort: Name A-Z",
            name_desc: "Sort: Name Z-A",
            company_asc: "Sort: Company A-Z",
        }[sort.value];

        if (sortLabel) chips.push({ key: "sort", label: sortLabel });
    }

    return chips;
});

function applyFilters() {
    router.get(route("sub-cons.index"), {
        search: search.value,
        portal: portal.value,
        bank_account: bankAccount.value,
        sort: sort.value,
    }, {
        preserveState: true,
        replace: true,
    });
}

function resetFilters() {
    search.value = "";
    portal.value = "all";
    bankAccount.value = "all";
    sort.value = "latest";
    applyFilters();
}

function clearFilter(filterKey) {
    if (filterKey === "search") search.value = "";
    if (filterKey === "portal") portal.value = "all";
    if (filterKey === "bank_account") bankAccount.value = "all";
    if (filterKey === "sort") sort.value = "latest";
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
                    class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                    @click="openCreate"
                >
                    + Create Sub Con
                </button>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <div class="bg-white p-4 md:p-5 rounded-xl shadow w-full border space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Filter Sub Cons</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ totalSubCons }} result{{ totalSubCons === 1 ? "" : "s" }}
                        </p>
                    </div>

                    <button
                        v-if="hasAnyFilter"
                        class="text-xs text-red-600 hover:text-red-700 font-medium"
                        @click="resetFilters"
                    >
                        Clear all
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                    <div class="xl:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Search
                        </label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Name / company / email / phone / bank"
                            class="border border-gray-300 rounded-lg px-3 py-2.5 w-full text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Portal Account
                        </label>
                        <select
                            v-model="portal"
                            class="border border-gray-300 rounded-lg px-3 py-2.5 w-full text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                        >
                            <option value="all">All</option>
                            <option value="with_portal">Has Account</option>
                            <option value="active_portal">Active Login</option>
                            <option value="inactive_portal">Inactive Login</option>
                            <option value="no_portal">No Account</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Bank Account
                        </label>
                        <select
                            v-model="bankAccount"
                            class="border border-gray-300 rounded-lg px-3 py-2.5 w-full text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                        >
                            <option value="all">All</option>
                            <option value="yes">Added</option>
                            <option value="no">Missing</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-full md:w-56">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Sort By
                        </label>
                        <select
                            v-model="sort"
                            class="border border-gray-300 rounded-lg px-3 py-2.5 w-full text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                        >
                            <option value="latest">Latest</option>
                            <option value="name_asc">Name (A-Z)</option>
                            <option value="name_desc">Name (Z-A)</option>
                            <option value="company_asc">Company (A-Z)</option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2 ml-auto">
                        <button
                            class="px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-600 text-white hover:bg-indigo-700"
                            @click="applyFilters"
                        >
                            Apply Filters
                        </button>
                        <button
                            class="px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-700 hover:bg-gray-200"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div v-if="activeFilters.length" class="flex flex-wrap gap-2 pt-1">
                    <button
                        v-for="filter in activeFilters"
                        :key="filter.key"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-medium hover:bg-indigo-100"
                        @click="clearFilter(filter.key)"
                    >
                        <span>{{ filter.label }}</span>
                        <span class="text-indigo-500">x</span>
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Portal
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
                                <div class="font-medium text-gray-800">
                                    {{ bankSummary(subCon) }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ subCon.bank_accounts_count ?? 0 }} account{{ (subCon.bank_accounts_count ?? 0) === 1 ? "" : "s" }}
                                </div>
                            </td>

                            <td class="px-4 py-3 text-sm">
                                <span
                                    v-if="subCon.portal_user"
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                    :class="subCon.portal_user.status === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                >
                                    {{ subCon.portal_user.status === 1 ? "Active" : "Inactive" }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600"
                                >
                                    No account
                                </span>
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
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">
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
