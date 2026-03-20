<script setup>
import { ref, inject, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import VehicleForm from './Partials/VehicleForm.vue'
import AllocateForm from './Partials/AllocateForm.vue'

const { capitalize, formatDate } = useFormat()

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

const rows = computed(() => vehicles?.data ?? [])

const stats = computed(() => {
    const total = rows.value.length
    const active = rows.value.filter((item) => item.status === 'active').length
    const warning = rows.value.filter((item) => {
        return (
            isExpiring(item.latest_insurance?.expiry_date) ||
            isExpiring(item.latest_roadtax?.expiry_date)
        )
    }).length
    const unpaidSaman = rows.value.filter((item) => Number(item.unpaid_saman_total ?? 0) > 0).length

    return { total, active, warning, unpaidSaman }
})

const activeFilterCount = computed(() => {
    let count = 0
    if (search.value) count++
    if (status.value) count++
    if (hasUnpaidSaman.value) count++
    if (insuranceExpiring.value) count++
    if (roadtaxExpiring.value) count++
    return count
})

const paginationLinks = computed(() => {
    const links = vehicles?.links ?? []
    return links.filter((link) => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;')
})

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

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

function formatMileage(value) {
    if (value === null || value === undefined || value === '') return '-'
    return `${Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    })} km`
}

function cleanLabel(label) {
    if (!label) return ''
    return String(label)
        .replace('&laquo;', '')
        .replace('&raquo;', '')
        .trim()
}

function visitPage(url) {
    if (!url) return
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Vehicles</h2>
                    <p class="text-sm text-gray-500">Track allocation, compliance, and saman status in one view.</p>
                </div>

                <button
                    @click="openCreate"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                >
                    <i class="mdi mdi-plus"></i>
                    Register Vehicle
                </button>
            </div>
        </template>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Listed</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ stats.total }}</p>
                </div>
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-green-700">Active</p>
                    <p class="mt-2 text-2xl font-semibold text-green-900">{{ stats.active }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Expiring Soon</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-900">{{ stats.warning }}</p>
                </div>
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-red-700">Unpaid Saman</p>
                    <p class="mt-2 text-2xl font-semibold text-red-900">{{ stats.unpaidSaman }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">Filters</h3>
                        <span
                            v-if="activeFilterCount"
                            class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700"
                        >
                            {{ activeFilterCount }} active
                        </span>
                    </div>

                    <div class="flex gap-2">
                        <button
                            class="inline-flex h-8 items-center rounded-md bg-gray-100 px-2.5 text-xs font-medium text-gray-700 transition hover:bg-gray-200"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                        <button
                            class="inline-flex h-8 items-center rounded-md bg-indigo-600 px-2.5 text-xs font-medium text-white transition hover:bg-indigo-700"
                            @click="applyFilters"
                        >
                            Apply
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-5">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Search</label>
                        <div class="relative">
                            <i class="mdi mdi-magnify pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Plate / Brand / Model"
                                class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
                        <select
                            v-model="status"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        >
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="disposed">Disposed</option>
                        </select>
                    </div>

                    <div class="flex flex-wrap gap-3 lg:col-span-5 lg:items-end">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:border-gray-300">
                            <input type="checkbox" v-model="hasUnpaidSaman" class="rounded border-gray-300 text-indigo-600" />
                            Unpaid Saman
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:border-gray-300">
                            <input type="checkbox" v-model="insuranceExpiring" class="rounded border-gray-300 text-indigo-600" />
                            Insurance Expiring
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:border-gray-300">
                            <input type="checkbox" v-model="roadtaxExpiring" class="rounded border-gray-300 text-indigo-600" />
                            Roadtax Expiring
                        </label>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Vehicle</th>
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
                                v-for="item in rows"
                                :key="item.id"
                                class="border-t border-gray-100 transition hover:bg-gray-50"
                            >
                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-start gap-3">
                                        <img
                                            v-if="item.attachment?.url"
                                            :src="item.attachment.url"
                                            :alt="`${item.brand} ${item.model}`"
                                            class="h-12 w-12 rounded-lg border border-gray-200 object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex h-12 w-12 items-center justify-center rounded-lg border border-gray-200 bg-gray-100 text-gray-400"
                                        >
                                            <i class="mdi mdi-car"></i>
                                        </div>

                                        <div>
                                            <div class="font-semibold text-gray-900">
                                                {{ item.brand }} {{ item.model }}
                                            </div>
                                            <div class="font-mono text-xs text-gray-500">
                                                {{ item.vehicle?.plate_number ?? '-' }}
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ item.vehicle?.engine_cc ?? '-' }} CC
                                            </div>
                                            <div class="mt-1 text-xs text-indigo-700">
                                                Last mileage:
                                                <span class="font-semibold">
                                                    {{ formatMileage(item.latest_vehicle_mileage_log?.mileage) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <div v-if="item.active_allocation">
                                        <div class="font-medium text-gray-800">
                                            {{ item.active_allocation.allocatable_name || item.active_allocation.allocatable?.name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ item.active_allocation.location || 'No location' }}
                                        </div>
                                    </div>
                                    <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500">
                                        Unallocated
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center align-top">
                                    <span
                                        v-if="item.latest_insurance"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="isExpiring(item.latest_insurance.expiry_date)
                                            ? 'bg-amber-100 text-amber-700'
                                            : 'bg-green-100 text-green-700'"
                                    >
                                        {{ formatDate(item.latest_insurance.expiry_date) }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>

                                <td class="px-4 py-3 text-center align-top">
                                    <span
                                        v-if="item.latest_roadtax"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="isExpiring(item.latest_roadtax.expiry_date)
                                            ? 'bg-amber-100 text-amber-700'
                                            : 'bg-green-100 text-green-700'"
                                    >
                                        {{ formatDate(item.latest_roadtax.expiry_date) }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>

                                <td class="px-4 py-3 text-center align-top">
                                    <span
                                        v-if="item.unpaid_saman_total > 0"
                                        class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700"
                                    >
                                        RM {{ formatMoney(item.unpaid_saman_total) }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>

                                <td class="px-4 py-3 text-center align-top">
                                    <span
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="{
                                            'bg-green-100 text-green-700': item.status === 'active',
                                            'bg-gray-100 text-gray-600': item.status === 'inactive',
                                            'bg-red-100 text-red-700': item.status === 'disposed',
                                        }"
                                    >
                                        {{ capitalize(item.status) }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center align-top">
                                    <div class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white p-1">
                                        <button
                                            @click="router.visit(route('inventory.vehicles.show', item.uuid))"
                                            class="rounded-md p-2 text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-700"
                                            title="View"
                                        >
                                            <i class="mdi mdi-eye-outline text-lg"></i>
                                        </button>
                                        <button
                                            @click="openEdit(item)"
                                            class="rounded-md p-2 text-blue-600 transition hover:bg-blue-50 hover:text-blue-700"
                                            title="Edit"
                                        >
                                            <i class="mdi mdi-pencil-outline text-lg"></i>
                                        </button>
                                        <button
                                            @click="openAllocate(item)"
                                            class="rounded-md p-2 text-sky-600 transition hover:bg-sky-50 hover:text-sky-700"
                                            title="Transfer"
                                        >
                                            <i class="mdi mdi-swap-horizontal text-lg"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-10 text-center">
                                    <i class="mdi mdi-car-off text-3xl text-gray-300"></i>
                                    <p class="mt-2 text-sm text-gray-500">No vehicles found for the selected filters.</p>
                                    <button
                                        v-if="activeFilterCount"
                                        class="mt-3 inline-flex h-8 items-center rounded-md bg-gray-100 px-2.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                        @click="resetFilters"
                                    >
                                        Clear Filters
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="vehicles?.links?.length > 3"
                    class="flex flex-col gap-3 border-t border-gray-100 px-4 py-3 text-sm md:flex-row md:items-center md:justify-between"
                >
                    <p class="text-xs text-gray-500">
                        Showing {{ vehicles.from ?? 0 }}-{{ vehicles.to ?? 0 }} of {{ vehicles.total ?? 0 }} vehicles
                    </p>

                    <div class="flex flex-wrap items-center gap-1">
                        <button
                            class="rounded-md border px-3 py-1.5 text-xs font-medium"
                            :class="vehicles.prev_page_url ? 'border-gray-300 text-gray-700 hover:bg-gray-50' : 'cursor-not-allowed border-gray-200 text-gray-300'"
                            :disabled="!vehicles.prev_page_url"
                            @click="visitPage(vehicles.prev_page_url)"
                        >
                            Previous
                        </button>

                        <button
                            v-for="link in paginationLinks"
                            :key="link.label"
                            class="rounded-md border px-3 py-1.5 text-xs font-medium"
                            :class="link.active
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : link.url
                                    ? 'border-gray-300 text-gray-700 hover:bg-gray-50'
                                    : 'cursor-not-allowed border-gray-200 text-gray-300'"
                            :disabled="!link.url || link.active"
                            @click="visitPage(link.url)"
                        >
                            {{ cleanLabel(link.label) }}
                        </button>

                        <button
                            class="rounded-md border px-3 py-1.5 text-xs font-medium"
                            :class="vehicles.next_page_url ? 'border-gray-300 text-gray-700 hover:bg-gray-50' : 'cursor-not-allowed border-gray-200 text-gray-300'"
                            :disabled="!vehicles.next_page_url"
                            @click="visitPage(vehicles.next_page_url)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

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
