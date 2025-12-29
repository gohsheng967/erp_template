<script setup>
import { ref, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SupplierForm from './Partials/SupplierForm.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'
import { useFormat } from '@/Composables/useFormat'

const { capitalize } = useFormat()

/* =========================
   PAGE DATA
========================= */
const page = usePage()
const suppliers = page.props.suppliers
const filters = page.props.filters ?? {}

/* =========================
   INJECTIONS
========================= */
const toast = inject('toast', null)

/* =========================
   FILTER STATE
========================= */
const search = ref(filters.search ?? '')

/* =========================
   MODAL STATE
========================= */
const showForm = ref(false)
const mode = ref('create') // create | edit
const selectedSupplier = ref(null)

/* =========================
   DELETE CONFIRMATION STATE
========================= */
const showDelete = ref(false)
const deletingSupplier = ref(null)

/* =========================
   FILTER ACTIONS
========================= */
function applyFilters() {
    router.get(
        route('suppliers.index'),
        { search: search.value },
        {
            preserveState: false,
            replace: true,
        }
    )
}

function resetFilters() {
    search.value = ''

    router.get(
        route('suppliers.index'),
        {},
        {
            preserveState: false,
            replace: true,
        }
    )
}

/* =========================
   MODAL ACTIONS
========================= */
function openCreate() {
    mode.value = 'create'
    selectedSupplier.value = null
    showForm.value = true
}

function openEdit(supplier) {
    mode.value = 'edit'
    selectedSupplier.value = supplier
    showForm.value = true
}

function closeForm() {
    showForm.value = false
}

/* =========================
   DELETE FLOW
========================= */
function confirmDelete(supplier) {
    deletingSupplier.value = supplier
    showDelete.value = true
}

function destroyConfirmed() {
    if (!deletingSupplier.value) return

    router.delete(
        route('suppliers.destroy', deletingSupplier.value.uuid),
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Supplier deleted', 'success')
                showDelete.value = false
                deletingSupplier.value = null
                refresh()
            },
            onError: () => {
                toast?.value?.show('Failed to delete supplier', 'error')
            },
        }
    )
}

/* =========================
   REFRESH AFTER SAVE
========================= */
function refresh() {
    router.get(
        route('suppliers.index'),
        { search: search.value },
        {
            preserveState: false,
            replace: true,
        }
    )
}
</script>

<template>
    <AuthenticatedLayout>
        <!-- PAGE HEADER -->
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800">
                    Suppliers
                </h2>

                <button
                    @click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                >
                    + Create Supplier
                </button>
            </div>
        </template>

        <!-- FILTERS -->
        <div class="bg-white p-4 rounded-lg shadow w-full border mb-4">
            <div class="flex flex-wrap gap-4 items-end">

                <!-- SEARCH -->
                <div class="flex flex-col w-full md:w-1/3">
                    <label class="block text-sm font-medium text-gray-700">
                        Search
                    </label>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Company / Contact"
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

        <!-- TABLE -->
        <div class="bg-white rounded-xl shadow border overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <!-- ROW 1 -->
                    <tr>
                        <th rowspan="2" class="px-4 py-3 text-left align-middle">
                            Company
                        </th>
                        <th rowspan="2" class="px-4 py-3 text-left align-middle">
                            Contact
                        </th>
                        <th rowspan="2" class="px-4 py-3 text-left align-middle">
                            Status
                        </th>
                        <th rowspan="2" class="px-4 py-3 text-center align-middle">
                            Quotation
                        </th>
                        <th rowspan="2" class="px-4 py-3 text-center align-middle">
                            Ongoing Order
                        </th>
                        <th colspan="2" class="px-4 py-3 text-center">
                            Unpaid Invoice
                        </th>
                        <th rowspan="2" class="px-4 py-3 text-center align-middle">
                            Action
                        </th>
                    </tr>

                    <!-- ROW 2 -->
                    <tr>
                        <th class="px-4 py-2 text-center text-xs font-medium">
                            Qty
                        </th>
                        <th class="px-4 py-2 text-center text-xs font-medium">
                            Amount
                        </th>
                    </tr>
                </thead>


                <tbody>
                    <tr
                        v-for="supplier in suppliers.data"
                        :key="supplier.id"
                        class="border-t hover:bg-gray-50"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">
                                {{ supplier.company_name }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ supplier.registration_no || '-' }}
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div>{{ supplier.contact_person || '-' }}</div>
                            <div class="text-xs text-gray-500">
                                {{ supplier.contact_phone || '-' }}
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <span
                                class="px-2 py-1 text-xs rounded-full"
                                :class="{
                                    'bg-green-100 text-green-700': supplier.status === 'active',
                                    'bg-gray-100 text-gray-600': supplier.status === 'inactive',
                                    'bg-red-100 text-red-700': supplier.status === 'blacklisted',
                                }"
                            >
                                {{ capitalize(supplier.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            {{ supplier.quotation_count ?? 0 }}
                        </td>

                        <td class="px-4 py-3 text-center">
                            0
                        </td>
                        <td class="px-4 py-3 text-center">
                            0
                        </td>
                        <td class="px-4 py-3 text-center">
                            0.00
                        </td>
                        <td class="px-4 py-3 text-center space-x-3">
                            <!-- VIEW -->
                            <button
                                @click="router.visit(route('suppliers.show', supplier.uuid))"
                                class="text-indigo-600 hover:text-indigo-800"
                                title="View"
                            >
                                <i class="mdi mdi-eye-outline text-lg"></i>
                            </button>

                            <!-- EDIT -->
                            <button
                                @click="openEdit(supplier)"
                                class="text-blue-600 hover:text-blue-800"
                                title="Edit"
                            >
                                <i class="mdi mdi-pencil-outline text-lg"></i>
                            </button>

                            <!-- DELETE -->
                            <button
                                @click="confirmDelete(supplier)"
                                class="text-red-600 hover:text-red-800"
                                title="Delete"
                            >
                                <i class="mdi mdi-delete-outline text-lg"></i>
                            </button>
                        </td>
                    </tr>

                    <tr v-if="!suppliers.data.length">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                            No suppliers found
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- CREATE / EDIT MODAL -->
        <SupplierForm
            v-if="showForm"
            :supplier="selectedSupplier"
            :mode="mode"
            @close="closeForm"
            @saved="refresh"
        />

        <!-- DELETE CONFIRMATION -->
        <DeleteConfirmation
            v-if="showDelete"
            title="Delete Supplier"
            :message="`Are you sure you want to delete supplier '${deletingSupplier?.company_name}'?`"
            @confirm="destroyConfirmed"
            @close="
                showDelete = false;
                deletingSupplier = null
            "
        />

    </AuthenticatedLayout>
</template>
