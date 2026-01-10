<script setup>
import { ref, onMounted, inject, computed } from 'vue'
import axios from 'axios'
import { useFormat } from '@/Composables/useFormat'

import InsuranceFormModal from './InsuranceFormModal.vue'
import RoadtaxFormModal from './RoadtaxFormModal.vue'

/* =========================
   PROPS
========================= */
const props = defineProps({
    vehicleUuid: {
        type: String,
        required: true,
    },
})

/* =========================
   DEPENDENCIES
========================= */
const toast = inject('toast', null)
const { formatDate, formatCurrency, capitalize } = useFormat()

/* =========================
   STATE
========================= */
const loading = ref(true)

const insurance = ref(null)
const insuranceHistory = ref([])
const roadtax = ref(null)

/* =========================
   MODALS
========================= */
const showInsuranceForm = ref(false)
const insuranceMode = ref('add') // add | edit | renew
const selectedInsurance = ref(null)

const showRoadtaxForm = ref(false)
const roadtaxMode = ref('add') // add | renew

/* =========================
   LOAD
========================= */
async function loadCompliance() {
    loading.value = true

    try {
        const res = await axios.get(
            route('inventory.vehicles.compliance', props.vehicleUuid)
        )

        insurance.value = res.data.insurance
        insuranceHistory.value = res.data.insurance_history || []
        roadtax.value = res.data.roadtax

    } catch (e) {
        console.error(e)
        toast?.value?.show('Failed to load compliance data', 'error')
    } finally {
        loading.value = false
    }
}

onMounted(loadCompliance)

/* =========================
   HELPERS
========================= */
const isInsuranceExpired = computed(() => {
    if (!insurance.value?.expiry_date) return false
    return new Date(insurance.value.expiry_date) < new Date()
})

const isRoadtaxExpired = computed(() => {
    if (!roadtax.value?.expiry_date) return false
    return new Date(roadtax.value.expiry_date) < new Date()
})

/* =========================
   ACTIONS
========================= */
function addInsurance() {
    insuranceMode.value = 'add'
    selectedInsurance.value = null
    showInsuranceForm.value = true
}

function editInsurance() {
    insuranceMode.value = 'edit'
    selectedInsurance.value = insurance.value
    showInsuranceForm.value = true
}

function renewInsurance() {
    insuranceMode.value = 'renew'
    selectedInsurance.value = insurance.value
    showInsuranceForm.value = true
}

function addRoadtax() {
    roadtaxMode.value = 'add'
    showRoadtaxForm.value = true
}

function renewRoadtax() {
    roadtaxMode.value = 'renew'
    showRoadtaxForm.value = true
}

function editRoadtax() {
    roadtaxMode.value = 'edit'
    showRoadtaxForm.value = true
}
</script>

<template>
    <div>

        <!-- ================= LOADING ================= -->
        <div
            v-if="loading"
            class="p-6 text-sm text-gray-400 flex items-center gap-2"
        >
            <i class="mdi mdi-loading mdi-spin"></i>
            Loading compliance...
        </div>

        <!-- ================= CONTENT ================= -->
        <div v-else class="space-y-8">

            <!-- ================= TOP ROW ================= -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- ================= INSURANCE ================= -->
                <section class="bg-white border rounded-xl p-6 h-full flex flex-col">

                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold flex items-center gap-2">
                            <i class="mdi mdi-shield-outline"></i>
                            Insurance
                        </h3>

                        <span
                            v-if="insurance"
                            class="px-2 py-1 text-xs rounded"
                            :class="isInsuranceExpired
                                ? 'bg-red-100 text-red-700'
                                : 'bg-green-100 text-green-700'"
                        >
                            {{ isInsuranceExpired ? 'Expired' : 'Active' }}
                        </span>
                    </div>

                    <!-- EMPTY -->
                    <div
                        v-if="!insurance"
                        class="border border-dashed rounded-lg p-6 text-center text-gray-400 flex-1"
                    >
                        No insurance recorded

                        <div class="mt-3">
                            <button
                                class="btn-primary"
                                @click="addInsurance"
                            >
                                Add Insurance
                            </button>
                        </div>
                    </div>

                    <!-- DETAILS -->
                    <div
                        v-else
                        class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm"
                    >
                        <div>
                            <label class="text-gray-500">Provider</label>
                            <div>{{ insurance.provider || '-' }}</div>
                        </div>

                        <div>
                            <label class="text-gray-500">Policy</label>
                            <div>{{ insurance.policy_number || '-' }}</div>
                        </div>

                        <div>
                            <label class="text-gray-500">Expiry</label>
                            <div>{{ formatDate(insurance.expiry_date) }}</div>
                        </div>

                        <div>
                            <label class="text-gray-500">Coverage</label>
                            <div>
                                {{ insurance.coverage_amount
                                    ? formatCurrency(insurance.coverage_amount)
                                    : '-' }}
                            </div>
                        </div>

                        <div>
                            <label class="text-gray-500">Premium</label>
                            <div>
                                {{ insurance.premium_amount
                                    ? formatCurrency(insurance.premium_amount)
                                    : '-' }}
                            </div>
                        </div>
                    </div>

                    <!-- ACTIONS -->
                    <div
                        v-if="insurance"
                        class="mt-auto flex justify-end gap-2 pt-6"
                    >
                        <button
                            class="btn-outline"
                            @click="renewInsurance"
                        >
                            <i class="mdi mdi-refresh"></i>
                            Renew
                        </button>

                        <button
                            class="btn-outline"
                            @click="editInsurance"
                        >
                            <i class="mdi mdi-pencil"></i>
                            Edit
                        </button>
                    </div>
                </section>

                <!-- ================= ROADTAX ================= -->
                <section class="bg-white border rounded-xl p-6 h-full flex flex-col">

                    <!-- HEADER -->
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold flex items-center gap-2">
                            <i class="mdi mdi-card-text-outline"></i>
                            Roadtax
                        </h3>

                        <span
                            v-if="roadtax"
                            class="px-2 py-1 text-xs rounded flex items-center gap-1"
                            :class="isRoadtaxExpired
                                ? 'bg-red-100 text-red-700'
                                : 'bg-green-100 text-green-700'"
                        >
                            <i
                                class="mdi"
                                :class="isRoadtaxExpired ? 'mdi-alert-circle-outline' : 'mdi-check-circle-outline'"
                            ></i>
                            {{ isRoadtaxExpired ? 'Expired' : 'Active' }}
                        </span>
                    </div>

                    <!-- EMPTY STATE -->
                    <div
                        v-if="!roadtax"
                        class="border border-dashed rounded-lg p-6 text-center text-gray-400 flex-1 flex flex-col items-center justify-center"
                    >
                        <i class="mdi mdi-file-remove-outline text-3xl mb-2"></i>
                        <div>No roadtax recorded</div>

                        <button
                            class="btn-primary mt-4"
                            @click="addRoadtax"
                        >
                            <i class="mdi mdi-plus"></i>
                            Add Roadtax
                        </button>
                    </div>

                    <!-- DETAILS -->
                    <div v-else class="text-sm space-y-4">

                        <!-- EXPIRY -->
                        <div>
                            <label class="text-gray-500">Expiry Date</label>
                            <div
                                class="font-medium"
                                :class="isRoadtaxExpired ? 'text-red-600' : 'text-gray-800'"
                            >
                                {{ formatDate(roadtax.expiry_date) }}
                            </div>
                        </div>

                        <!-- DOCUMENT -->
                        <div v-if="roadtax.attachment">
                            <a
                                :href="roadtax.attachment?.url"
                                target="_blank"
                                class="mt-1 inline-flex items-center gap-2
                                    px-3 py-2 rounded-lg border text-xs
                                    text-indigo-600 bg-indigo-50
                                    hover:bg-indigo-100 transition"
                            >
                                <i class="mdi mdi-file-document-outline text-lg"></i>
                                View Roadtax Document
                                <i class="mdi mdi-open-in-new ml-1"></i>
                            </a>
                        </div>
                        <div v-else>
                            No Document Upload
                        </div>
                    </div>

                    <!-- ACTION -->
                    <div
                        v-if="roadtax"
                        class="mt-auto flex justify-end gap-2 pt-6"
                    >
                        <button
                            class="btn-outline flex items-center gap-1"
                            @click="renewRoadtax"
                        >
                            <i class="mdi mdi-refresh"></i>
                            Renew
                        </button>

                        <button
                            class="btn-outline flex items-center gap-1"
                            @click="editRoadtax"
                        >
                            <i class="mdi mdi-pencil"></i>
                            Edit
                        </button>
                    </div>

                </section>
            </div>

            <!-- ================= INSURANCE HISTORY (FULL WIDTH) ================= -->
            <section
                v-if="insuranceHistory.length > 1"
                class="bg-white border rounded-xl p-6"
            >
                <h4 class="font-medium mb-4 flex items-center gap-2">
                    <i class="mdi mdi-history"></i>
                    Insurance History
                </h4>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left">Provider</th>
                                <th class="px-3 py-2 text-left">Policy</th>
                                <th class="px-3 py-2 text-left">Expiry Date</th>
                                <th class="px-3 py-2 text-right">Premium</th>
                                <th class="px-3 py-2 text-center">Doc</th>
                                <th class="px-3 py-2 text-center">Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="row in insuranceHistory"
                                :key="row.id"
                                class="border-t"
                            >
                                <td class="px-3 py-2">{{ row.provider || '-' }}</td>
                                <td class="px-3 py-2">{{ row.policy_number || '-' }}</td>
                                <td class="px-3 py-2">
                                    {{ formatDate(row.expiry_date) }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    {{ row.premium_amount
                                        ? formatCurrency(row.premium_amount)
                                        : '-' }}
                                </td>

                                <!-- DOCUMENT ICON -->
                                <td class="px-3 py-2 text-center">
                                    <a
                                        v-if="row.attachment"
                                        :href="row.attachment?.url"
                                        target="_blank"
                                        class="inline-flex items-center justify-center
                                            text-indigo-600 hover:text-indigo-800"
                                        title="View policy document"
                                    >
                                        <i class="mdi mdi-file-document-outline text-lg"></i> 
                                    </a>

                                    <span v-else class="text-gray-300">
                                        -
                                    </span>
                                </td>

                                <!-- STATUS -->
                                <td class="px-3 py-2 text-center">
                                    <span
                                        class="px-2 py-1 text-xs rounded"
                                        :class="{
                                            'bg-green-100 text-green-700': row.status === 'active',
                                            'bg-gray-100 text-gray-600': row.status === 'expired',
                                            'bg-red-100 text-red-700': row.status === 'cancelled',
                                        }"
                                    >
                                        {{ capitalize(row.status) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>

        <!-- ================= MODALS ================= -->
        <InsuranceFormModal
            v-if="showInsuranceForm"
            :vehicle-uuid="vehicleUuid"
            :mode="insuranceMode"
            :insurance="selectedInsurance"
            @close="showInsuranceForm = false"
            @saved="loadCompliance"
        />

        <RoadtaxFormModal
            v-if="showRoadtaxForm"
            :vehicle-uuid="vehicleUuid"
            :mode="roadtaxMode"
            :roadtax="roadtax"
            @close="showRoadtaxForm = false"
            @saved="loadCompliance"
        />
    </div>
</template>
