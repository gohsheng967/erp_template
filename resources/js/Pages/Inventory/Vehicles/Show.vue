<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import AllocateForm from './Partials/AllocateForm.vue'
import ComplianceTab from './Partials/ComplianceTab.vue'

const props = defineProps({
    vehicle: Object,
})

const { capitalize, formatDate } = useFormat()
const tab = ref('details')

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
</script>

<template>
    <AuthenticatedLayout>

        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">
                        Vehicle Asset
                    </h2>
                    <p class="text-sm text-gray-500">
                        Inventory / Vehicles
                    </p>
                </div>

                <div class="flex gap-2">
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
        <div class="bg-gray-50 rounded-xl p-6 mb-6 border">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- LEFT -->
                <div class="lg:col-span-2">
                    <h3 class="text-2xl font-semibold text-gray-900">
                        {{ vehicle.brand }} {{ vehicle.model }}
                    </h3>

                    <div class="mt-1 text-sm text-gray-500 font-mono">
                        {{ vehicle.vehicle?.plate_number ?? '-' }}
                        <span class="mx-2">•</span>
                        {{ vehicle.vehicle?.engine_cc ?? '-' }} CC
                    </div>

                    <div class="flex flex-wrap gap-2 mt-4">
                        <span
                            v-if="vehicle.active_allocation"
                            class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700"
                        >
                            Allocated
                        </span>
                        <span
                            v-else
                            class="px-2 py-1 text-xs rounded-full bg-gray-200 text-gray-600"
                        >
                            Unallocated
                        </span>
                    </div>

                    <!-- CURRENT ALLOCATION -->
                    <div class="mt-4 text-sm flex items-center gap-2">
                        <span class="text-gray-500">Current Allocation:</span>

                        <span v-if="vehicle.active_allocation" class="font-medium">
                            {{ vehicle.active_allocation.allocatable_name
                                || vehicle.active_allocation.allocatable?.name
                                || 'Office' }}
                        </span>

                        <span v-else class="text-gray-400">-</span>

                        <button
                            v-if="vehicle.status === 'active'"
                            class="text-indigo-600 hover:text-indigo-800"
                            title="Allocate to other"
                            @click="openAllocate"
                        >
                            <i class="mdi mdi-swap-horizontal"></i>
                        </button>
                    </div>

                    <!-- ================= ALLOCATION HISTORY ================= -->
                    <div class="mt-6">
                        <div class="text-sm font-semibold text-gray-700 mb-3">
                            Allocation History
                        </div>

                        <div class="max-h-64 overflow-y-auto space-y-4">

                            <div
                                v-for="(alloc, index) in vehicle.allocations"
                                :key="alloc.id"
                                class="flex gap-4"
                            >
                                <!-- TIMELINE -->
                                <div class="relative flex flex-col items-center">
                                    <span
                                        v-if="index !== 0"
                                        class="absolute top-0 h-4 w-px bg-gray-300"
                                    ></span>

                                    <span
                                        class="relative z-10 w-3 h-3 rounded-full"
                                        :class="alloc.to_date
                                            ? 'bg-gray-300'
                                            : 'bg-indigo-600'"
                                    ></span>

                                    <span
                                        v-if="index !== vehicle.allocations.length - 1"
                                        class="absolute top-3 bottom-0 w-px bg-gray-300"
                                    ></span>
                                </div>

                                <!-- CARD -->
                                <div
                                    class="flex-1 bg-white border rounded-lg p-3 text-sm"
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

                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ formatDate(alloc.from_date) }}
                                        <span v-if="alloc.to_date">
                                            → {{ formatDate(alloc.to_date) }}
                                        </span>
                                        <span v-else>
                                            → Present
                                        </span>
                                    </div>

                                    <div
                                        v-if="alloc.location"
                                        class="text-xs text-gray-500 mt-1"
                                    >
                                        📍 {{ alloc.location }}
                                    </div>

                                    <div
                                        v-if="alloc.remark"
                                        class="text-xs text-gray-600 mt-1 italic"
                                    >
                                        “{{ alloc.remark }}”
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="!vehicle.allocations?.length"
                                class="text-sm text-gray-400"
                            >
                                No allocation history.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT -->
                <div class="space-y-4">
                    <div class="bg-white rounded-lg p-4 border text-center">
                        <div class="text-xs text-gray-500 mb-2">
                            Asset QR Code
                        </div>
                        <img
                            :src="route('inventory.vehicles.qr', vehicle.uuid)"
                            class="mx-auto w-40 h-40"
                        />
                    </div>

                    <div class="bg-white rounded-lg p-4 border space-y-3 text-sm">
                        <div>
                            <div class="text-xs text-gray-500">Insurance</div>
                            <div v-if="vehicle.insurances?.length">
                                <span
                                    class="font-medium"
                                    :class="isExpiring(vehicle.insurances[0].expiry_date)
                                        ? 'text-yellow-500'
                                        : 'text-green-500'"
                                >
                                    {{ formatDate(vehicle.insurances[0].expiry_date) }}
                                </span>
                            </div>
                            <span v-else class="text-red-600 animate-pulse">
                                Missing
                            </span>
                        </div>

                        <div>
                            <div class="text-xs text-gray-500">Roadtax</div>
                            <div v-if="vehicle.roadtaxes?.length">
                                <span
                                    class="font-medium"
                                    :class="isExpiring(vehicle.roadtaxes[0].expiry_date)
                                        ? 'text-yellow-500'
                                        : 'text-green-500'"
                                >
                                    {{ formatDate(vehicle.roadtaxes[0].expiry_date) }}
                                </span>
                            </div>
                            <span v-else class="text-red-600 animate-pulse">
                                Missing
                            </span>
                        </div>

                        <div>
                            <div class="text-xs text-gray-500">Unpaid Saman</div>
                            <span
                                v-if="vehicle.unpaid_saman_total > 0"
                                class="text-red-700 font-semibold"
                            >
                                RM {{ vehicle.unpaid_saman_total }}
                            </span>
                            <span v-else class="text-green-700">None</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TABS ================= -->
        <div class="bg-white rounded-xl border">
            <div class="flex gap-6 px-6 border-b">
                <button
                    v-for="t in ['details', 'compliance', 'saman']"
                    :key="t"
                    class="py-4 text-sm font-medium capitalize"
                    :class="tab === t
                        ? 'border-b-2 border-indigo-600 text-indigo-600'
                        : 'text-gray-500 hover:text-gray-700'"
                    @click="tab = t"
                >
                    {{ t }}
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
                </div>
                <div v-else-if="tab === 'compliance'">
                    <ComplianceTab
                        :vehicle-uuid="vehicle.uuid"
                    />
                </div>
                <div v-else class="text-gray-400 text-sm">
                    This section will be implemented next.
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

    </AuthenticatedLayout>
</template>
