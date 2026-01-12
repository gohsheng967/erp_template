<script setup>
import { ref, inject, watch, onMounted } from "vue"
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'

import Claim from './Child/Claim.vue'
import PurchaseRequest from './Child/PurchaseRequest.vue'

/* ===============================
   PROPS / EMITS
================================ */
const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['budget-updated'])
const toast = inject("toast", null)

/* ===============================
   BUDGET
================================ */
const currentBudget = ref(props.project.budget ?? 0)

const budgetForm = useForm({
    budget: currentBudget.value,
})

function saveBudget() {
    budgetForm.patch(
        route('projects.update-budget', props.project.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                const saved = Number(budgetForm.budget)
                budgetForm.defaults({ budget: saved })
                budgetForm.reset()
                currentBudget.value = saved
                toast?.value?.show('Budget updated', 'success')
            },
        }
    )
}

watch(
    () => props.project.budget,
    val => {
        const normalized = Number(val ?? 0)
        currentBudget.value = normalized
        budgetForm.defaults({ budget: normalized })
        budgetForm.reset()
    }
)

/* ===============================
   COLLAPSE STATES
================================ */
const showBudget = ref(true)
const showClaim = ref(false)
const showPurchase = ref(false)
const showInvoice = ref(false)

/* ===============================
   AR SUMMARY
================================ */
const arLoading = ref(false)
const arSummary = ref(null)

async function loadArSummary() {
    if (!props.project?.id) return

    arLoading.value = true

    try {
        const res = await axios.get(
            route('projects.ar.summary', props.project.id)
        )

        arSummary.value = res.data.summary

    } catch (e) {
        console.error(e)
        toast?.value?.show('Failed to load AR summary', 'error')
    } finally {
        arLoading.value = false
    }
}

onMounted(loadArSummary)
watch(() => props.project.id, loadArSummary)
</script>




<template>
    <div class="space-y-8">


        <!-- =============================== -->
        <!-- 1) EDIT BUDGET -->
        <!-- =============================== -->
        <div class="bg-white rounded-xl shadow-md border">
            <button
                @click="showBudget = !showBudget"
                class="w-full flex justify-between items-center p-4 text-left"
            >
                <span class="text-lg font-semibold">Edit Budget</span>
                <svg
                    :class="showBudget ? 'rotate-180' : ''"
                    class="h-5 w-5 transition"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-show="showBudget" class="p-6 border-t space-y-4">
                <p class="text-gray-600">
                    Current Budget:
                    <span class="font-semibold ml-1">
                        {{ $formatCurrency(currentBudget) }}
                    </span>

                    <span
                        v-if="budgetForm.isDirty"
                        class="text-xs text-orange-600 ml-2"
                    >
                        Unsaved
                    </span>
                </p>

                <div class="flex gap-4 items-center">
                    <input
                        v-model="budgetForm.budget"
                        type="number"
                        step="0.01"
                        class="border px-3 py-2 rounded-md w-64"
                        placeholder="Enter new budget"
                    />

                    <button
                        @click="saveBudget"
                        :disabled="budgetForm.processing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md
                            hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Save Budget
                    </button>
                </div>

                <div v-if="budgetForm.errors.budget" class="text-sm text-red-500">
                    {{ budgetForm.errors.budget }}
                </div>
            </div>
        </div>




        <!-- =============================== -->
        <!-- 2) CREATE CLAIM + HISTORY -->
        <!-- =============================== -->
        <div class="bg-white rounded-xl shadow-md border">
            <button @click="showClaim = !showClaim" class="w-full flex justify-between items-center p-4 text-left">
                <span class="text-lg font-semibold">Claim Expense</span>
                <svg :class="showClaim ? 'rotate-180' : ''" class="h-5 w-5 transition"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-show="showClaim" class="p-6 border-t space-y-6">

                <Claim
                    :project-id="project.id"
                />
            </div>
        </div>




        <!-- =============================== -->
        <!-- 3) PURCHASE REQUEST + HISTORY -->
        <!-- =============================== -->
        <div class="bg-white rounded-xl shadow-md border">
            <button @click="showPurchase = !showPurchase"
                    class="w-full flex justify-between items-center p-4 text-left">
                <span class="text-lg font-semibold">Purchase Request</span>
                <svg :class="showPurchase ? 'rotate-180' : ''" class="h-5 w-5 transition"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-show="showPurchase" class="p-6 border-t space-y-6">
                <PurchaseRequest :project-id="project.id" />
            </div>
        </div>

        <!-- =============================== -->
        <!-- 4) ACCOUNTS RECEIVABLE -->
        <!-- =============================== -->
        <div class="bg-white rounded-xl shadow-md border">
            <button
                @click="showInvoice = !showInvoice"
                class="w-full flex justify-between items-center p-4 text-left"
            >
                <span class="text-lg font-semibold">Accounts Receivable</span>
                <svg
                    :class="showInvoice ? 'rotate-180' : ''"
                    class="h-5 w-5 transition"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-show="showInvoice" class="p-6 border-t space-y-6">

                <div v-if="arLoading" class="text-sm text-gray-400">
                    Loading accounts receivable…
                </div>

                <div v-else-if="arSummary" class="grid grid-cols-1 md:grid-cols-4 gap-4">

                    <div class="bg-indigo-50 border rounded-lg p-4">
                        <div class="text-xs text-gray-500">Total Invoiced</div>
                        <div class="text-xl font-semibold text-indigo-700">
                            {{ $formatCurrency(arSummary.total_amount) }}
                        </div>
                    </div>

                    <div class="bg-green-50 border rounded-lg p-4">
                        <div class="text-xs text-gray-500">Received</div>
                        <div class="text-xl font-semibold text-green-700">
                            {{ $formatCurrency(arSummary.received_amount) }}
                        </div>
                    </div>

                    <div class="bg-red-50 border rounded-lg p-4">
                        <div class="text-xs text-gray-500">Outstanding</div>
                        <div class="text-xl font-semibold text-red-700">
                            {{ $formatCurrency(arSummary.outstanding_amount) }}
                        </div>
                    </div>

                    <div class="bg-slate-50 border rounded-lg p-4 text-sm">
                        <div>Draft: <b>{{ arSummary.draft }}</b></div>
                        <div>Issued: <b>{{ arSummary.issued }}</b></div>
                        <div>Approved: <b>{{ arSummary.approved }}</b></div>
                        <div>Received: <b>{{ arSummary.received }}</b></div>
                        <div>Cancelled: <b>{{ arSummary.cancelled }}</b></div>
                    </div>
                </div>

                <div class="text-right pt-2">
                    <a
                        :href="route('ar-invoices.index', { project_id: project.id })"
                        class="text-sm text-indigo-600 hover:underline font-medium"
                    >
                        View all invoices →
                    </a>
                </div>
            </div>
        </div>




    </div>
</template>
