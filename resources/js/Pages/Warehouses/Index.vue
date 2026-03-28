<script setup>
import { ref, computed, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import WarehouseFormModal from './Partials/WarehouseFormModal.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'

const page = usePage()
const toast = inject('toast', null)

const warehouses = computed(() => Array.isArray(page.props.warehouses) ? page.props.warehouses : [])

const showForm = ref(false)
const editingWarehouse = ref(null)
const showDelete = ref(false)
const deletingWarehouse = ref(null)

const search = ref('')
const statusFilter = ref('all')
const sortBy = ref('title_asc')

const totalCount = computed(() => warehouses.value.length)
const activeCount = computed(() => warehouses.value.filter((w) => Number(w.status) === 1).length)
const inactiveCount = computed(() => warehouses.value.filter((w) => Number(w.status) !== 1).length)

const filteredWarehouses = computed(() => {
    let rows = [...warehouses.value]

    const keyword = search.value.trim().toLowerCase()
    if (keyword) {
        rows = rows.filter((warehouse) => {
            const title = String(warehouse.title ?? '').toLowerCase()
            const address = String(warehouse.address ?? '').toLowerCase()
            return title.includes(keyword) || address.includes(keyword)
        })
    }

    if (statusFilter.value === 'active') {
        rows = rows.filter((warehouse) => Number(warehouse.status) === 1)
    } else if (statusFilter.value === 'inactive') {
        rows = rows.filter((warehouse) => Number(warehouse.status) !== 1)
    }

    if (sortBy.value === 'title_desc') {
        rows.sort((a, b) => String(b.title ?? '').localeCompare(String(a.title ?? '')))
    } else if (sortBy.value === 'status') {
        rows.sort((a, b) => Number(b.status) - Number(a.status) || String(a.title ?? '').localeCompare(String(b.title ?? '')))
    } else {
        rows.sort((a, b) => String(a.title ?? '').localeCompare(String(b.title ?? '')))
    }

    return rows
})

function resetFilters() {
    search.value = ''
    statusFilter.value = 'all'
    sortBy.value = 'title_asc'
}

function createWarehouse() {
    editingWarehouse.value = null
    showForm.value = true
}

function editWarehouse(warehouse) {
    editingWarehouse.value = warehouse
    showForm.value = true
}

function confirmDisable(warehouse) {
    deletingWarehouse.value = warehouse
    showDelete.value = true
}

function disableWarehouse() {
    if (!deletingWarehouse.value?.id) return

    router.delete(route('warehouses.destroy', deletingWarehouse.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Warehouse / Office disabled', 'success')
            showDelete.value = false
            deletingWarehouse.value = null
        },
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">Warehouses / Office</h2>
                    <p class="text-sm text-slate-500">Manage active and inactive warehouse / office locations.</p>
                </div>

                <button
                    @click="createWarehouse"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                >
                    <i class="mdi mdi-plus mr-1"></i>
                    Add Warehouse / Office
                </button>
            </div>
        </template>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ totalCount }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Active</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ activeCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Inactive</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-700">{{ inactiveCount }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-6">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search warehouse or location"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        />
                    </div>

                    <div class="lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <select
                            v-model="statusFilter"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            <option value="all">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sort</label>
                        <select
                            v-model="sortBy"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            <option value="title_asc">Name (A-Z)</option>
                            <option value="title_desc">Name (Z-A)</option>
                            <option value="status">Status</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <p class="text-sm text-slate-500">Showing {{ filteredWarehouses.length }} result{{ filteredWarehouses.length === 1 ? '' : 's' }}</p>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="text-sm font-medium text-slate-600 hover:text-slate-900"
                    >
                        Reset filters
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Warehouse / Office</th>
                                <th class="px-4 py-3 text-left font-semibold">Location</th>
                                <th class="px-4 py-3 text-center font-semibold">Status</th>
                                <th class="px-4 py-3 text-right font-semibold">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="warehouse in filteredWarehouses"
                                :key="warehouse.id"
                                class="border-t border-slate-200 hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 font-medium text-slate-800">
                                    {{ warehouse.title }}
                                </td>

                                <td class="px-4 py-3 text-slate-600">
                                    {{ warehouse.address }}
                                </td>

                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="Number(warehouse.status) === 1
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-slate-200 text-slate-600'"
                                    >
                                        {{ Number(warehouse.status) === 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            @click="editWarehouse(warehouse)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-indigo-600 transition hover:border-indigo-200 hover:bg-indigo-50"
                                            title="Edit Warehouse / Office"
                                        >
                                            <i class="mdi mdi-pencil"></i>
                                        </button>

                                        <button
                                            v-if="Number(warehouse.status) === 1"
                                            @click="confirmDisable(warehouse)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-red-600 transition hover:border-red-200 hover:bg-red-50"
                                            title="Disable Warehouse / Office"
                                        >
                                            <i class="mdi mdi-delete-outline"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!filteredWarehouses.length">
                                <td colspan="4" class="px-4 py-10 text-center text-slate-500">
                                    No warehouses / offices matched your filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <WarehouseFormModal
            :show="showForm"
            :warehouse="editingWarehouse"
            @close="showForm = false"
        />

        <DeleteConfirmation
            v-if="showDelete"
            title="Disable Warehouse"
            message="Are you sure you want to disable this warehouse / office?"
            @confirm="disableWarehouse"
            @close="showDelete = false"
        />
    </AuthenticatedLayout>
</template>
