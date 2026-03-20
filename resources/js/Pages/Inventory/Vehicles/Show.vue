<script setup>
import { ref, inject, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import AllocateForm from './Partials/AllocateForm.vue'
import ComplianceTab from './Partials/ComplianceTab.vue'
import LogbookTab from './Partials/LogbookTab.vue'

const props = defineProps({
    vehicle: Object,
})

const { capitalize, formatDate } = useFormat()
const initialTab = typeof window !== 'undefined'
    ? (new URLSearchParams(window.location.search).get('tab') || 'details')
    : 'details'
const tab = ref(initialTab)
const toast = inject('toast', null)

const lastServiceDate = computed(() => {
    const svc = props.vehicle?.services?.[0]
    return svc?.service_date ? formatDate(svc.service_date) : '-'
})

const mileageLogs = computed(() => {
    const logs = props.vehicle?.vehicle_logs ?? []
    return [...logs]
        .filter((log) => log?.mileage !== null && log?.mileage !== undefined && log?.mileage !== '')
        .sort((a, b) => new Date(b.trip_date) - new Date(a.trip_date))
})

const currentMileage = computed(() => {
    return mileageLogs.value.length ? Number(mileageLogs.value[0].mileage) : null
})

const lastMileageLog = computed(() => {
    return props.vehicle?.latest_vehicle_mileage_log ?? mileageLogs.value[0] ?? null
})

const mileageSummary = computed(() => {
    const allLogs = props.vehicle?.vehicle_logs ?? []
    const count = mileageLogs.value.length
    if (!count) {
        return {
            totalLogs: allLogs.length,
            mileageLogs: 0,
            min: null,
            max: null,
            distance: 0,
            latestTripDate: null,
        }
    }

    const values = mileageLogs.value.map((log) => Number(log.mileage)).filter((v) => !Number.isNaN(v))
    const min = Math.min(...values)
    const max = Math.max(...values)
    const latestTripDate = mileageLogs.value[0]?.trip_date ?? null

    return {
        totalLogs: allLogs.length,
        mileageLogs: count,
        min,
        max,
        distance: Math.max(0, max - min),
        latestTripDate,
    }
})

const quickLogbookHref = computed(() => {
    return props.vehicle?.quick_logbook_url
        ?? (route('inventory.vehicles.show', props.vehicle.uuid) + '?tab=logbook')
})

const tabItems = [
    { key: 'details', label: 'Details', icon: 'mdi-card-text-outline' },
    { key: 'service', label: 'Service', icon: 'mdi-wrench-outline' },
    { key: 'compliance', label: 'Compliance', icon: 'mdi-shield-check-outline' },
    { key: 'logbook', label: 'Logbook', icon: 'mdi-notebook-outline' },
    { key: 'saman', label: 'Saman', icon: 'mdi-alert-circle-outline' },
]

/* =========================
   ALLOCATE MODAL
========================= */
const showAllocate = ref(false)

function openAllocate() {
    showAllocate.value = true
}

function onAllocated() {
    showAllocate.value = false
    router.reload({ preserveScroll: true })
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

function setTab(nextTab) {
    tab.value = nextTab

    if (typeof window === 'undefined') return
    const url = new URL(window.location.href)
    url.searchParams.set('tab', nextTab)
    window.history.replaceState({}, '', url)
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

const showServiceModal = ref(false)
const showEditServiceModal = ref(false)
const selectedService = ref(null)

const serviceForm = ref({
    service_date: '',
    item_parts: '',
    cost: '',
    vendor: '',
    notes: '',
})

const editServiceForm = ref({
    service_date: '',
    item_parts: '',
    cost: '',
    vendor: '',
    notes: '',
})

function openAddService() {
    serviceForm.value = {
        service_date: '',
        item_parts: '',
        cost: '',
        vendor: '',
        notes: '',
    }
    showServiceModal.value = true
}

function submitService() {
    router.post(
        route('inventory.vehicles.services.store', props.vehicle.uuid),
        serviceForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Service record added.', 'success')
                showServiceModal.value = false
                router.reload({ preserveScroll: true })
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] ?? 'Failed to add service record.'
                toast?.value?.show(msg, 'error')
            },
        }
    )
}

function openEditService(service) {
    selectedService.value = service
    editServiceForm.value = {
        service_date: service.service_date ?? '',
        item_parts: service.item_parts ?? '',
        cost: service.cost ?? '',
        vendor: service.vendor ?? '',
        notes: service.notes ?? '',
    }
    showEditServiceModal.value = true
}

function submitEditService() {
    router.patch(
        route('inventory.vehicles.services.update', {
            uuid: props.vehicle.uuid,
            service: selectedService.value.id,
        }),
        editServiceForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Service record updated.', 'success')
                showEditServiceModal.value = false
                selectedService.value = null
                router.reload({ preserveScroll: true })
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] ?? 'Failed to update service record.'
                toast?.value?.show(msg, 'error')
            },
        }
    )
}

function deleteService(service) {
    if (!confirm('Delete this service record?')) return
    router.delete(
        route('inventory.vehicles.services.destroy', {
            uuid: props.vehicle.uuid,
            service: service.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Service record deleted.', 'success')
                router.reload({ preserveScroll: true })
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] ?? 'Failed to delete service record.'
                toast?.value?.show(msg, 'error')
            },
        }
    )
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">
                        Vehicle Asset
                    </h2>
                    <p class="text-sm text-gray-500">
                        Inventory / Vehicles
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="vehicle.status === 'active'"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 flex items-center gap-2"
                        @click="openAllocate"
                    >
                        <i class="mdi mdi-swap-horizontal"></i>
                        Allocate
                    </button>

                    <button
                        class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300"
                        @click="router.visit(route('inventory.vehicles.index'))"
                    >
                        Back
                    </button>
                </div>
            </div>
        </template>

        <!-- ================= IMAGE HERO ================= -->
        <div class="relative w-full h-64 rounded-xl overflow-hidden mb-6 border bg-gray-100">
            <img
                v-if="vehicle.attachment"
                :src="vehicle.attachment.url"
                class="w-full h-full object-cover"
            />

            <div
                v-else
                class="w-full h-full flex items-center justify-center text-gray-400 text-sm"
            >
                No vehicle image
            </div>

            <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>

            <div class="absolute top-4 left-4 flex gap-2 z-10">
                <span
                    class="px-2 py-1 text-xs rounded-full bg-white/90 backdrop-blur"
                    :class="vehicle.status === 'active'
                        ? 'text-green-700'
                        : 'text-gray-600'"
                >
                    {{ capitalize(vehicle.status) }}
                </span>

                <span
                    class="px-2 py-1 text-xs rounded-full bg-white/90 backdrop-blur text-indigo-700"
                >
                    {{ capitalize(vehicle.ownership_type) }}
                </span>
            </div>
        </div>

        <!-- ================= INVENTORY CARD ================= -->
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm md:p-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <!-- LEFT -->
                <div class="lg:col-span-2 space-y-5">
                    <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-900">
                                    {{ vehicle.brand }} {{ vehicle.model }}
                                </h3>
                                <div class="mt-1 flex items-center gap-2 text-sm text-gray-500">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 font-mono ring-1 ring-gray-200">
                                        <i class="mdi mdi-card-text-outline text-gray-400"></i>
                                        {{ vehicle.vehicle?.plate_number ?? '-' }}
                                    </span>
                                    <span>&bull;</span>
                                    <span>{{ vehicle.vehicle?.engine_cc ?? '-' }} CC</span>
                                </div>
                            </div>

                            <span
                                v-if="vehicle.active_allocation"
                                class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700"
                            >
                                <i class="mdi mdi-check-circle-outline"></i>
                                Allocated
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600"
                            >
                                <i class="mdi mdi-alert-circle-outline"></i>
                                Unallocated
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div class="rounded-lg border border-gray-200 bg-white p-3">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Current Allocation</div>
                                <div class="mt-1 text-sm font-semibold text-gray-800">
                                    {{ vehicle.active_allocation?.allocatable_name
                                        || vehicle.active_allocation?.allocatable?.name
                                        || 'Unallocated' }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-3">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Last Mileage</div>
                                <div class="mt-1 text-sm font-semibold text-gray-800">{{ formatMileage(lastMileageLog?.mileage) }}</div>
                                <div class="text-[11px] text-gray-500">
                                    {{ lastMileageLog?.trip_date ? formatDate(lastMileageLog.trip_date) : '-' }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-3">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Current Mileage</div>
                                <div class="mt-1 text-sm font-semibold text-gray-800">{{ formatMileage(currentMileage) }}</div>
                                <div class="text-[11px] text-gray-500">From latest completed log</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Vehicle Summary</div>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">
                                Live status
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Insurance</div>
                                <div v-if="vehicle.insurances?.length" class="mt-1 text-sm font-semibold" :class="isExpiring(vehicle.insurances[0].expiry_date) ? 'text-amber-700' : 'text-green-700'">
                                    {{ formatDate(vehicle.insurances[0].expiry_date) }}
                                </div>
                                <div v-else class="mt-1 text-sm font-semibold text-red-600">Missing</div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Roadtax</div>
                                <div v-if="vehicle.roadtaxes?.length" class="mt-1 text-sm font-semibold" :class="isExpiring(vehicle.roadtaxes[0].expiry_date) ? 'text-amber-700' : 'text-green-700'">
                                    {{ formatDate(vehicle.roadtaxes[0].expiry_date) }}
                                </div>
                                <div v-else class="mt-1 text-sm font-semibold text-red-600">Missing</div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Unpaid Saman</div>
                                <div v-if="vehicle.unpaid_saman_total > 0" class="mt-1 text-sm font-semibold text-red-700">
                                    RM {{ formatMoney(vehicle.unpaid_saman_total) }}
                                </div>
                                <div v-else class="mt-1 text-sm font-semibold text-green-700">None</div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Last Service</div>
                                <div class="mt-1 text-sm font-semibold text-gray-800">{{ lastServiceDate }}</div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Last Mileage</div>
                                <div class="mt-1 text-sm font-semibold text-gray-800">{{ formatMileage(lastMileageLog?.mileage) }}</div>
                                <div class="text-[11px] text-gray-500">
                                    {{ lastMileageLog?.trip_date ? formatDate(lastMileageLog.trip_date) : '-' }}
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Current Mileage</div>
                                <div class="mt-1 text-sm font-semibold text-gray-800">{{ formatMileage(currentMileage) }}</div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 sm:col-span-2">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Mileage Summary</div>
                                <div class="mt-1 text-sm font-semibold text-gray-800">
                                    {{ formatMileage(mileageSummary.distance) }} tracked
                                </div>
                                <div class="text-[11px] text-gray-500">
                                    {{ mileageSummary.mileageLogs }} with mileage / {{ mileageSummary.totalLogs }} total logs
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CURRENT ALLOCATION -->
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="flex items-center gap-2 text-sm">
                            <i class="mdi mdi-map-marker-path text-indigo-500"></i>
                            <span class="text-gray-500">Current Allocation:</span>

                            <span v-if="vehicle.active_allocation" class="font-medium text-gray-800">
                                {{ vehicle.active_allocation.allocatable_name
                                    || vehicle.active_allocation.allocatable?.name
                                    || 'Office' }}
                            </span>

                            <span v-else class="text-gray-400">-</span>
                        </div>

                        <button
                            v-if="vehicle.status === 'active'"
                            class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                            title="Allocate to other"
                            @click="openAllocate"
                        >
                            <i class="mdi mdi-swap-horizontal"></i>
                            Re-allocate
                        </button>
                    </div>

                    <!-- ================= ALLOCATION HISTORY ================= -->
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="mb-3 flex items-center justify-between text-sm font-semibold text-gray-700">
                            Allocation History
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                {{ vehicle.allocations?.length ?? 0 }} records
                            </span>
                        </div>

                        <div class="max-h-44 space-y-2 overflow-y-auto pr-1">

                            <div
                                v-for="(alloc, index) in vehicle.allocations"
                                :key="alloc.id"
                                class="flex gap-3"
                            >
                                <!-- TIMELINE -->
                                <div class="relative flex flex-col items-center">
                                    <span v-if="index !== 0" class="absolute top-0 h-3 w-px bg-gray-300"></span>

                                    <span
                                        class="relative z-10 h-2.5 w-2.5 rounded-full"
                                        :class="alloc.to_date
                                            ? 'bg-gray-300'
                                            : 'bg-indigo-600'"
                                    ></span>

                                    <span
                                        v-if="index !== vehicle.allocations.length - 1"
                                        class="absolute bottom-0 top-2.5 w-px bg-gray-300"
                                    ></span>
                                </div>

                                <!-- CARD -->
                                <div
                                    class="flex-1 rounded-lg border bg-white p-2.5 text-sm"
                                    :class="!alloc.to_date
                                        ? 'border-indigo-300 bg-indigo-50'
                                        : ''"
                                >
                                    <div class="flex justify-between items-start gap-2">
                                        <div class="font-medium text-gray-800">
                                            {{ alloc.allocatable_name
                                                || alloc.allocatable?.name
                                                || 'Office' }}
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <span
                                                v-if="!alloc.to_date"
                                                class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700"
                                            >
                                                Current
                                            </span>

                                            <button
                                                v-if="!alloc.to_date && vehicle.status === 'active'"
                                                class="text-indigo-600 hover:text-indigo-800"
                                                title="Allocate to other"
                                                @click="openAllocate"
                                            >
                                                <i class="mdi mdi-swap-horizontal"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-0.5 text-xs text-gray-500">
                                        {{ formatDate(alloc.from_date) }}
                                        <span v-if="alloc.to_date">
                                            &rarr; {{ formatDate(alloc.to_date) }}
                                        </span>
                                        <span v-else>
                                            &rarr; Present
                                        </span>
                                    </div>

                                    <div
                                        v-if="alloc.location"
                                        class="mt-0.5 text-xs text-gray-500"
                                    >
                                        <i class="mdi mdi-map-marker text-gray-400"></i> {{ alloc.location }}
                                    </div>

                                    <div
                                        v-if="alloc.remark"
                                        class="mt-0.5 text-xs italic text-gray-600"
                                    >
                                        "{{ alloc.remark }}"
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="!vehicle.allocations?.length"
                                class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-400"
                            >
                                <i class="mdi mdi-timeline-outline text-lg"></i>
                                <div class="mt-1">No allocation history yet.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT -->
                <div class="space-y-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Asset QR Code
                        </div>
                        <img
                            :src="vehicle.qr_image"
                            class="mx-auto h-40 w-40 rounded-lg border border-gray-100 bg-white p-1"
                        />
                    </div>

                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 text-center shadow-sm">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                            Quick Logbook QR
                        </div>
                        <img
                            :src="vehicle.qr_logbook_image"
                            class="mx-auto h-40 w-40 rounded-lg border border-indigo-100 bg-white p-1"
                        />
                        <div class="mt-2 text-[11px] text-gray-500">
                            Scan to open logbook form directly
                        </div>
                        <a
                            :href="quickLogbookHref"
                            class="mt-1 inline-block break-all rounded-md bg-white px-2 py-1 text-[11px] text-indigo-600 ring-1 ring-indigo-100 hover:text-indigo-700 hover:underline"
                        >
                            {{ quickLogbookHref }}
                        </a>
                        <div
                            v-if="vehicle.quick_logbook_pin"
                            class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800"
                        >
                            <div class="font-medium">Temporary vehicle PIN</div>
                            <div class="mt-0.5 font-mono text-sm font-bold tracking-widest">{{ vehicle.quick_logbook_pin }}</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ================= TABS ================= -->
        <div class="bg-white rounded-xl border">
            <div class="flex flex-wrap gap-2 border-b bg-gray-50 p-3">
                <button
                    v-for="item in tabItems"
                    :key="item.key"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
                    :class="tab === item.key
                        ? 'bg-indigo-600 text-white'
                        : 'text-gray-600 hover:bg-white hover:text-gray-800'"
                    @click="setTab(item.key)"
                >
                    <i :class="`mdi ${item.icon}`"></i>
                    {{ item.label }}
                </button>
            </div>

            <div class="p-6">
                <div
                    v-if="tab === 'details'"
                    class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm"
                >
                    <div>
                        <div class="text-xs text-gray-500">Brand</div>
                        <div class="font-medium">{{ vehicle.brand }}</div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Model</div>
                        <div class="font-medium">{{ vehicle.model }}</div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Engine CC</div>
                        <div class="font-medium">
                            {{ vehicle.vehicle?.engine_cc ?? '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Ownership</div>
                        <div class="font-medium">
                            {{ capitalize(vehicle.ownership_type) }}
                            <span v-if="vehicle.owner_name">
                                ({{ vehicle.owner_name }})
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Current Mileage</div>
                        <div class="font-medium">{{ formatMileage(currentMileage) }}</div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Distance Tracked</div>
                        <div class="font-medium">{{ formatMileage(mileageSummary.distance) }}</div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Last Mileage Update</div>
                        <div class="font-medium">
                            {{ mileageSummary.latestTripDate ? formatDate(mileageSummary.latestTripDate) : '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Mileage Log Entries</div>
                        <div class="font-medium">
                            {{ mileageSummary.mileageLogs }} / {{ mileageSummary.totalLogs }}
                        </div>
                    </div>
                </div>
                <div v-else-if="tab === 'compliance'">
                    <ComplianceTab
                        :vehicle-uuid="vehicle.uuid"
                    />
                </div>
                <div v-else-if="tab === 'service'">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Service History</h3>
                        <button
                            class="px-3 py-2 bg-indigo-600 text-white rounded text-sm"
                            @click="openAddService"
                        >
                            + Add Service
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm border">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border px-3 py-2 text-left">Date</th>
                                    <th class="border px-3 py-2 text-left">Item / Parts</th>
                                    <th class="border px-3 py-2 text-right">Cost</th>
                                    <th class="border px-3 py-2 text-left">Vendor</th>
                                    <th class="border px-3 py-2 text-left">Notes</th>
                                    <th class="border px-3 py-2 text-center w-24">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="svc in vehicle.services" :key="svc.id">
                                    <td class="border px-3 py-2">
                                        {{ formatDate(svc.service_date) }}
                                    </td>
                                    <td class="border px-3 py-2">
                                        {{ svc.item_parts }}
                                    </td>
                                    <td class="border px-3 py-2 text-right">
                                        {{ Number(svc.cost ?? 0).toFixed(2) }}
                                    </td>
                                    <td class="border px-3 py-2">
                                        {{ svc.vendor ?? '-' }}
                                    </td>
                                    <td class="border px-3 py-2">
                                        {{ svc.notes ?? '-' }}
                                    </td>
                                    <td class="border px-3 py-2 text-center">
                                        <button
                                            class="text-blue-600 hover:text-blue-800 mr-2"
                                            @click="openEditService(svc)"
                                        >
                                            <i class="mdi mdi-pencil-outline"></i>
                                        </button>
                                        <button
                                            class="text-red-600 hover:text-red-800"
                                            @click="deleteService(svc)"
                                        >
                                            <i class="mdi mdi-delete-outline"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!vehicle.services?.length">
                                    <td colspan="6" class="border px-3 py-6 text-center text-gray-400">
                                        No service records.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-else-if="tab === 'logbook'">
                    <LogbookTab :vehicle="vehicle" />
                </div>
                <div v-else-if="tab === 'saman'">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Saman Records</h3>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600">
                            {{ vehicle.samans?.length ?? 0 }} records
                        </span>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Date</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Details</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Location</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600">Amount</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="saman in vehicle.samans"
                                    :key="saman.id"
                                    class="border-t border-gray-100"
                                >
                                    <td class="px-3 py-2 text-gray-700">
                                        {{ formatDate(saman.issue_date || saman.date || saman.created_at) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-800">
                                            {{ saman.offence || saman.description || saman.reference_no || 'Saman Record' }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ saman.reference_no || saman.saman_no || '-' }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">
                                        {{ saman.location || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-800">
                                        RM {{ formatMoney(saman.amount) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-medium"
                                            :class="saman.status === 'unpaid'
                                                ? 'bg-red-100 text-red-700'
                                                : 'bg-green-100 text-green-700'"
                                        >
                                            {{ capitalize(saman.status || 'paid') }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!vehicle.samans?.length">
                                    <td colspan="5" class="px-3 py-8 text-center text-gray-400">
                                        No saman records.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= ALLOCATE MODAL ================= -->
    <AllocateForm
        v-if="showAllocate"
        :vehicle="vehicle"
        @close="showAllocate = false"
        @allocated="onAllocated"
    />

    <!-- Add Service Modal -->
    <div v-if="showServiceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Add Service Record</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showServiceModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm text-gray-600">Service Date</label>
                    <input v-model="serviceForm.service_date" type="date" class="mt-1 w-full border rounded-md px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Item / Parts</label>
                    <textarea v-model="serviceForm.item_parts" rows="3" class="mt-1 w-full border rounded-md px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Cost</label>
                    <input v-model="serviceForm.cost" type="number" min="0" step="0.01" class="mt-1 w-full border rounded-md px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Vendor</label>
                    <input v-model="serviceForm.vendor" type="text" class="mt-1 w-full border rounded-md px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Notes</label>
                    <textarea v-model="serviceForm.notes" rows="2" class="mt-1 w-full border rounded-md px-3 py-2"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300" @click="showServiceModal = false">Cancel</button>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700" @click="submitService">Save</button>
            </div>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div v-if="showEditServiceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Edit Service Record</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showEditServiceModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm text-gray-600">Service Date</label>
                    <input v-model="editServiceForm.service_date" type="date" class="mt-1 w-full border rounded-md px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Item / Parts</label>
                    <textarea v-model="editServiceForm.item_parts" rows="3" class="mt-1 w-full border rounded-md px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Cost</label>
                    <input v-model="editServiceForm.cost" type="number" min="0" step="0.01" class="mt-1 w-full border rounded-md px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Vendor</label>
                    <input v-model="editServiceForm.vendor" type="text" class="mt-1 w-full border rounded-md px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Notes</label>
                    <textarea v-model="editServiceForm.notes" rows="2" class="mt-1 w-full border rounded-md px-3 py-2"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300" @click="showEditServiceModal = false">Cancel</button>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700" @click="submitEditService">Update</button>
            </div>
        </div>
    </div>

    </AuthenticatedLayout>
</template>
