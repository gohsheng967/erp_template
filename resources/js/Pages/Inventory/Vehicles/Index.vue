<script setup>
import { ref, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import VehicleForm from './Partials/VehicleForm.vue'
import AllocateForm from './Partials/AllocateForm.vue'

const { capitalize } = useFormat()

/* =========================
   MODAL STATE
========================= */
const showForm = ref(false)
const mode = ref('create') // create | edit
const selectedVehicle = ref(null)

const showAllocate = ref(false)
const allocateVehicle = ref(null)

function openAllocate(vehicle) {
    allocateVehicle.value = vehicle
    showAllocate.value = true
}

function onAllocated() {
    toast?.value?.show('Vehicle allocated successfully', 'success')
    showAllocate.value = false
    refresh()
}

function openCreate() {
    mode.value = 'create'
    selectedVehicle.value = null
    showForm.value = true
}

function openEdit(vehicle) {
    mode.value = 'edit'
    selectedVehicle.value = vehicle
    showForm.value = true
}

function onSaved() {
    toast?.value?.show(
        mode.value === 'create'
            ? 'Vehicle created successfully'
            : 'Vehicle updated successfully',
        'success'
    )
    showForm.value = false
    refresh()
}

/* =========================
   PAGE DATA
========================= */
const page = usePage()
const vehicles = page.props.vehicles
const filters = page.props.filters ?? {}

/* =========================
   INJECTIONS
========================= */
const toast = inject('toast', null)

/* =========================
   FILTER STATE
========================= */
const search = ref(filters.search ?? '')
const status = ref(filters.status ?? '')
const hasUnpaidSaman = ref(filters.has_unpaid_saman ?? false)
const insuranceExpiring = ref(filters.insurance_expiring ?? false)
const roadtaxExpiring = ref(filters.roadtax_expiring ?? false)

/* =========================
   FILTER ACTIONS
========================= */
function applyFilters() {
    router.get(
        route('inventory.vehicles.index'),
        {
            search: search.value,
            status: status.value,
            has_unpaid_saman: hasUnpaidSaman.value,
            insurance_expiring: insuranceExpiring.value,
            roadtax_expiring: roadtaxExpiring.value,
        },
        {
            preserveState: false,
            replace: true,
        }
    )
}

function resetFilters() {
    search.value = ''
    status.value = ''
    hasUnpaidSaman.value = false
    insuranceExpiring.value = false
    roadtaxExpiring.value = false

    router.get(
        route('inventory.vehicles.index'),
        {},
        {
            preserveState: false,
            replace: true,
        }
    )
}

/* =========================
   REFRESH AFTER SAVE
========================= */
function refresh() {
    router.get(
        route('inventory.vehicles.index'),
        {
            search: search.value,
            status: status.value,
            has_unpaid_saman: hasUnpaidSaman.value,
            insurance_expiring: insuranceExpiring.value,
            roadtax_expiring: roadtaxExpiring.value,
        },
        {
            preserveState: false,
            replace: true,
        }
    )
}

/* =========================
   HELPERS
========================= */
function isExpiring(date) {
    if (!date) return false
    const expiry = new Date(date)
    const threshold = new Date()
    threshold.setDate(threshold.getDate() + 30)
    return expiry <= threshold
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800">
                    Vehicles
                </h2>

                <button
                    @click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                >
                    + Create Vehicle
                </button>
            </div>
        </template>

        <!-- ================= FILTERS ================= -->
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
                        placeholder="Plate / Brand / Model"
                        class="border rounded-md px-3 py-2 w-full"
                        @keyup.enter="applyFilters"
                    />
                </div>

                <!-- STATUS -->
                <div class="flex flex-col w-full md:w-1/6">
                    <label class="block text-sm font-medium text-gray-700">
                        Status
                    </label>
                    <select
                        v-model="status"
                        class="border rounded-md px-3 py-2 w-full"
                    >
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="disposed">Disposed</option>
                    </select>
                </div>

                <!-- CHECKBOXES -->
                <div class="flex items-center gap-4 pt-6">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="hasUnpaidSaman" />
                        Unpaid Saman
                    </label>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="insuranceExpiring" />
                        Insurance Expiring
                    </label>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="roadtaxExpiring" />
                        Roadtax Expiring
                    </label>
                </div>

                <!-- ACTIONS -->
                <div class="flex gap-2 pt-6">
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
        </div>

        <!-- ================= TABLE ================= -->
        <div class="bg-white rounded-xl shadow border overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Vehicle</th>
                        <th class="px-4 py-3 text-left">Plate</th>
                        <th class="px-4 py-3 text-left">Allocation</th>
                        <th class="px-4 py-3 text-center">Insurance</th>
                        <th class="px-4 py-3 text-center">Roadtax</th>
                        <th class="px-4 py-3 text-center">Saman</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="item in vehicles.data"
                        :key="item.id"
                        class="border-t hover:bg-gray-50"
                    >
                        <!-- VEHICLE -->
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">
                                {{ item.brand }} {{ item.model }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ item.vehicle?.engine_cc }} CC
                            </div>
                        </td>

                        <!-- PLATE -->
                        <td class="px-4 py-3 font-mono">
                            {{ item.vehicle?.plate_number }}
                        </td>

                        <!-- ALLOCATION -->
                        <td class="px-4 py-3">
                            <div v-if="item.active_allocation">
                                <div class="font-medium">
                                    {{ item.active_allocation.allocatable_name
                                        || item.active_allocation.allocatable?.name }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ item.active_allocation.location }}
                                </div>
                            </div>
                            <span v-else class="text-gray-400">
                                Unallocated
                            </span>
                        </td>

                        <!-- INSURANCE -->
                        <td class="px-4 py-3 text-center">
                            <span
                                v-if="item.latest_insurance"
                                class="px-2 py-1 text-xs rounded-full"
                                :class="isExpiring(item.latest_insurance.expiry_date)
                                    ? 'bg-yellow-100 text-yellow-700'
                                    : 'bg-green-100 text-green-700'"
                            >
                                {{ item.latest_insurance.expiry_date }}
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </td>

                        <!-- ROADTAX -->
                        <td class="px-4 py-3 text-center">
                            <span
                                v-if="item.latest_roadtax"
                                class="px-2 py-1 text-xs rounded-full"
                                :class="isExpiring(item.latest_roadtax.expiry_date)
                                    ? 'bg-yellow-100 text-yellow-700'
                                    : 'bg-green-100 text-green-700'"
                            >
                                {{ item.latest_roadtax.expiry_date }}
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </td>

                        <!-- SAMAN -->
                        <td class="px-4 py-3 text-center">
                            <span
                                v-if="item.unpaid_saman_total > 0"
                                class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700"
                            >
                                RM {{ item.unpaid_saman_total }}
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </td>

                        <!-- STATUS -->
                        <td class="px-4 py-3 text-center">
                            <span
                                class="px-2 py-1 text-xs rounded-full"
                                :class="{
                                    'bg-green-100 text-green-700': item.status === 'active',
                                    'bg-gray-100 text-gray-600': item.status !== 'active',
                                }"
                            >
                                {{ capitalize(item.status) }}
                            </span>
                        </td>

                        <!-- ACTION -->
                        <td class="px-4 py-3 text-center space-x-3">
                            <button
                                @click="router.visit(route('inventory.vehicles.show', item.uuid))"
                                class="text-indigo-600 hover:text-indigo-800"
                                title="View"
                            >
                                <i class="mdi mdi-eye-outline text-lg"></i>
                            </button>
                            <button
                                @click="openEdit(item)"
                                class="text-blue-600 hover:text-blue-800"
                                title="Edit"
                            >
                                <i class="mdi mdi-pencil-outline text-lg"></i>
                            </button>

                            <button
                                @click="openAllocate(item)"
                                class="text-blue-600 hover:text-blue-800"
                                title="Transfer"
                            >
                                <i class="mdi mdi-swap-horizontal text-lg"></i>
                            </button>
                        </td>
                    </tr>

                    <tr v-if="!vehicles.data.length">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                            No vehicles found
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ================= CREATE / EDIT MODAL ================= -->
        <VehicleForm
            v-if="showForm"
            :vehicle="selectedVehicle"
            :mode="mode"
            @close="showForm = false"
            @saved="onSaved"
        />

        <AllocateForm
            v-if="showAllocate"
            :vehicle="allocateVehicle"
            @close="showAllocate = false"
            @allocated="onAllocated"
        />


    </AuthenticatedLayout>
</template>
