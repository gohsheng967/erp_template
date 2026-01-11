<script setup>
import { ref, computed, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import WarehouseFormModal from './Partials/WarehouseFormModal.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'

const page = usePage()
const toast = inject('toast', null)

const warehouses = computed(() => page.props.warehouses)

/* =====================
   UI STATE
===================== */
const showForm = ref(false)
const editingWarehouse = ref(null)
const showDelete = ref(false)
const deletingWarehouse = ref(null)

/* =====================
   ACTIONS
===================== */
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
    router.delete(route('warehouses.destroy', deletingWarehouse.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Warehouse disabled', 'success')
            showDelete.value = false
        },
    })
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- HEADER -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">
                    Warehouses / Office
                </h2>

                <button
                    @click="createWarehouse"
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                >
                    + Add Warehouse / Office
                </button>
            </div>
        </template>

        <!-- CONTENT -->
        <div class="bg-white rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">
                            Warehouse / Office
                        </th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">
                            Location
                        </th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-500">
                            Status
                        </th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    <tr
                        v-for="warehouse in warehouses"
                        :key="warehouse.id"
                    >
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ warehouse.title }}
                        </td>

                        <td class="px-4 py-3 text-gray-600">
                            {{ warehouse.address }}
                        </td>

                        <td class="px-4 py-3 text-center">
                            <span
                                class="px-2 py-1 text-xs rounded"
                                :class="warehouse.status === 1
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-gray-200 text-gray-500'"
                            >
                                {{ warehouse.status === 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>

                        <td class="px-4 py-3 text-right space-x-3">
                            <!-- EDIT -->
                            <button
                                @click="editWarehouse(warehouse)"
                                class="inline-flex items-center text-indigo-600 hover:text-indigo-800"
                                title="Edit Warehouse"
                            >
                                <i class="mdi mdi-pencil text-lg"></i>
                            </button>

                            <!-- DISABLE -->
                            <button
                                v-if="warehouse.status === 1"
                                @click="confirmDisable(warehouse)"
                                class="inline-flex items-center text-red-600 hover:text-red-800"
                                title="Disable Warehouse"
                            >
                                <i class="mdi mdi-delete-outline text-lg"></i>
                            </button>
                        </td>
                    </tr>

                    <tr v-if="warehouses.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-6 text-center text-gray-500"
                        >
                            No warehouses found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- MODALS -->
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
