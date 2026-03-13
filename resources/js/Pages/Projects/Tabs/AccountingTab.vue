<script setup>
import { ref, inject, watch, onMounted, computed } from "vue"
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'

import Claim from './Child/Claim.vue'
import PurchaseRequest from './Child/PurchaseRequest.vue'
import TopupRequest from './Child/TopupRequest.vue'

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
    budget: Number(currentBudget.value ?? 0),
    add_on: "",
    reason: "",
})

const budgetAllocationHistory = computed(() => props.project.budget_allocations ?? [])
const addOnAmount = computed(() => Number(budgetForm.add_on ?? 0))
const proposedBudgetAmount = computed(() => Number(currentBudget.value ?? 0) + addOnAmount.value)

function saveBudget() {
    if (addOnAmount.value <= 0) return
    budgetForm.budget = proposedBudgetAmount.value

    budgetForm.patch(
        route('projects.update-budget', props.project.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                const saved = Number(proposedBudgetAmount.value)
                budgetForm.budget = saved
                budgetForm.reset('add_on', 'reason')
                currentBudget.value = saved
                toast?.value?.show('Budget updated', 'success')
            },
        }
    )
}

function addPercentOfCurrent(percent) {
    budgetForm.add_on = Number((Number(currentBudget.value ?? 0) * percent).toFixed(2))
}

function clearAddOn() {
    budgetForm.reset('add_on', 'reason')
}

function formatDateTime(value) {
    if (!value) return "-"
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return "-"
    return date.toLocaleString("en-MY", {
        year: "numeric",
        month: "short",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    })
}

watch(
    () => props.project.budget,
    val => {
        const normalized = Number(val ?? 0)
        currentBudget.value = normalized
        budgetForm.budget = normalized
        budgetForm.reset('add_on', 'reason')
    }
)

/* ===============================
   COLLAPSE STATES
================================ */
const showBudget = ref(true)
const showClaim = ref(false)
const showPurchase = ref(false)
const showTopup = ref(false)
const showInvoice = ref(false)

/* ===============================
   EXPENSE SUMMARY (Same as Overview)
================================ */
const expenseSummaryLoading = ref(false)
const expenseSummary = ref({
    budget: {
        total: 0,
        used: 0,
        remaining: 0,
        percentage: 0,
    },
})

async function loadExpenseSummary() {
    if (!props.project?.id) return

    expenseSummaryLoading.value = true

    try {
        const res = await axios.get(
            route('projects.overview.kpi', props.project.id)
        )
        expenseSummary.value = res.data
    } catch (e) {
        console.error(e)
        toast?.value?.show('Failed to load expense summary', 'error')
    } finally {
        expenseSummaryLoading.value = false
    }
}

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

onMounted(() => {
    loadArSummary()
    loadExpenseSummary()
})
watch(() => props.project.id, () => {
    loadArSummary()
    loadExpenseSummary()
})
</script>




<template>
    <div class="space-y-8">

        <!-- =============================== -->
        <!-- 0) EXPENSE SUMMARY -->
        <!-- =============================== -->
        <div class="bg-white rounded-xl shadow-md border p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Expense Summary</h3>
                <span class="text-xs text-slate-500">AP + Claims + confirmed PO</span>
            </div>

            <div v-if="expenseSummaryLoading" class="text-sm text-slate-500">
                Loading expense summary...
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="border rounded-lg p-4 bg-slate-50">
                    <div class="text-xs text-slate-500">Total Budget</div>
                    <div class="text-xl font-semibold text-slate-800">
                        {{ $formatCurrency(expenseSummary.budget.total ?? 0) }}
                    </div>
                </div>

                <div class="border rounded-lg p-4 bg-indigo-50">
                    <div class="text-xs text-slate-500">Used</div>
                    <div class="text-xl font-semibold text-indigo-700">
                        {{ $formatCurrency(expenseSummary.budget.used ?? 0) }}
                    </div>
                </div>

                <div class="border rounded-lg p-4 bg-emerald-50">
                    <div class="text-xs text-slate-500">Remaining</div>
                    <div class="text-xl font-semibold text-emerald-700">
                        {{ $formatCurrency(expenseSummary.budget.remaining ?? 0) }}
                    </div>
                </div>

                <div class="border rounded-lg p-4 bg-white">
                    <div class="text-xs text-slate-500">Usage %</div>
                    <div class="text-xl font-semibold text-slate-800">
                        {{ expenseSummary.budget.percentage ?? 0 }}%
                    </div>
                    <div class="w-full h-2 rounded-full bg-slate-200 mt-2 overflow-hidden">
                        <div
                            class="h-2 bg-indigo-500 rounded-full"
                            :style="{ width: `${Math.min(expenseSummary.budget.percentage ?? 0, 100)}%` }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>


        <!-- =============================== -->
        <!-- 1) ADD-ON BUDGET -->
        <!-- =============================== -->
        <div class="bg-white rounded-xl shadow-md border">
            <button
                @click="showBudget = !showBudget"
                class="w-full flex justify-between items-center p-4 text-left"
            >
                <span class="text-lg font-semibold">Add-on Budget</span>
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-3">
                        <div class="text-sm text-slate-500">Current Budget</div>
                        <div class="text-2xl font-semibold text-slate-800">
                            {{ $formatCurrency(currentBudget) }}
                        </div>
                        <div class="text-xs text-slate-500">
                            Planned expense budget. Actual expenses come from AP, claims, PO, etc.
                        </div>
                    </div>

                    <div class="space-y-3">
                        <label class="block text-sm font-medium text-slate-700">
                            Add-on Amount
                        </label>
                        <div class="relative w-full">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 text-sm">
                                RM
                            </span>
                            <input
                                v-model="budgetForm.add_on"
                                type="number"
                                step="0.01"
                                min="0"
                                class="border px-3 py-2 rounded-md w-full pl-12"
                                placeholder="0.00"
                                @keydown.enter.prevent="saveBudget"
                            />
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="px-3 py-1.5 text-xs border border-slate-300 rounded-md text-slate-700 hover:bg-slate-50"
                                @click="addPercentOfCurrent(0.05)"
                                :disabled="budgetForm.processing"
                            >
                                +5%
                            </button>
                            <button
                                type="button"
                                class="px-3 py-1.5 text-xs border border-slate-300 rounded-md text-slate-700 hover:bg-slate-50"
                                @click="addPercentOfCurrent(0.1)"
                                :disabled="budgetForm.processing"
                            >
                                +10%
                            </button>
                            <button
                                type="button"
                                class="px-3 py-1.5 text-xs border border-slate-300 rounded-md text-slate-700 hover:bg-slate-50"
                                @click="addPercentOfCurrent(0.2)"
                                :disabled="budgetForm.processing"
                            >
                                +20%
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Reason for add-on
                            </label>
                            <textarea
                                v-model="budgetForm.reason"
                                rows="2"
                                class="w-full border rounded-md px-3 py-2"
                                placeholder="Explain why this budget add-on is required"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 text-sm">
                    <div class="border border-slate-200 rounded-lg bg-slate-50 p-3">
                        <div class="text-slate-500">New Budget (After Add-on)</div>
                        <div class="font-semibold text-slate-800">
                            {{ $formatCurrency(proposedBudgetAmount) }}
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <span
                        v-if="budgetForm.isDirty"
                        class="text-xs text-orange-600 font-medium"
                    >
                        Unsaved changes
                    </span>

                    <div class="sm:ml-auto flex gap-3">
                        <button
                            @click="saveBudget"
                            :disabled="budgetForm.processing || addOnAmount <= 0 || !budgetForm.reason?.trim()"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md
                                hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Add to Budget
                        </button>

                        <button
                            type="button"
                            class="px-4 py-2 border border-slate-300 rounded-md text-slate-700 hover:bg-slate-50"
                            @click="clearAddOn"
                            :disabled="budgetForm.processing || !budgetForm.isDirty"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div v-if="budgetForm.errors.budget || budgetForm.errors.add_on || budgetForm.errors.reason" class="text-sm text-red-500">
                    {{ budgetForm.errors.budget ?? budgetForm.errors.add_on ?? budgetForm.errors.reason }}
                </div>

                <div class="pt-2">
                    <h4 class="text-sm font-semibold text-slate-800 mb-2">
                        Budget Allocation History
                    </h4>

                    <div
                        v-if="budgetAllocationHistory.length === 0"
                        class="text-sm text-slate-500 border border-dashed rounded-md p-4"
                    >
                        No budget allocations recorded yet.
                    </div>

                    <div v-else class="overflow-x-auto border rounded-md">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="text-left px-3 py-2 font-medium">Date</th>
                                    <th class="text-left px-3 py-2 font-medium">Added</th>
                                    <th class="text-left px-3 py-2 font-medium">Previous</th>
                                    <th class="text-left px-3 py-2 font-medium">New Total</th>
                                    <th class="text-left px-3 py-2 font-medium">Reason</th>
                                    <th class="text-left px-3 py-2 font-medium">By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in budgetAllocationHistory"
                                    :key="item.id"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ formatDateTime(item.created_at) }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-emerald-700 font-medium">
                                        +{{ $formatCurrency(item.add_on_amount) }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ $formatCurrency(item.previous_budget) }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap font-medium text-slate-800">
                                        {{ $formatCurrency(item.new_budget) }}
                                    </td>
                                    <td class="px-3 py-2 min-w-72 text-slate-700">
                                        {{ item.reason || "-" }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ item.user?.name ?? "System" }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
        <!-- 4) TOP-UP REQUEST + HISTORY -->
        <!-- =============================== -->
        <div class="bg-white rounded-xl shadow-md border">
            <button
                @click="showTopup = !showTopup"
                class="w-full flex justify-between items-center p-4 text-left"
            >
                <span class="text-lg font-semibold">Top-Up Request</span>
                <svg
                    :class="showTopup ? 'rotate-180' : ''"
                    class="h-5 w-5 transition"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-show="showTopup" class="p-6 border-t space-y-6">
                <TopupRequest
                    :project-id="project.id"
                    :project-name="project.name"
                />
            </div>
        </div>

        <!-- =============================== -->
        <!-- 5) ACCOUNTS RECEIVABLE -->
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
