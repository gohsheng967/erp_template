<script setup>
import { ref, inject, watch } from "vue";
import { useForm, router } from '@inertiajs/vue3'

import Claim from './Child/Claim.vue'
import PurchaseRequest from './Child/PurchaseRequest.vue'

const toast = inject("toast", null)

const props = defineProps({
    project: Object
});

const currentBudget = ref(props.project.budget ?? 0)

const emit = defineEmits(['budget-updated'])

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


/* -----------------------------
   Collapse States
------------------------------ */
const showBudget = ref(true);
const showClaim = ref(false);
const showPurchase = ref(false);
const showInvoice = ref(false);

const budgetForm = useForm({
    budget: currentBudget.value ?? 0,
})

watch(
    () => props.project.budget,
    val => {
        const normalized = Number(val ?? 0)

        currentBudget.value = normalized

        budgetForm.defaults({
            budget: normalized,
        })

        budgetForm.reset()
    }
)


/* -----------------------------
   INVOICE ISSUED
------------------------------ */
const invoices = ref([
    { id: 1, invoice_no: "INV-2025-001", amount: 15000, date: "2025-02-04", status: "Paid" },
    { id: 2, invoice_no: "INV-2025-002", amount: 22000, date: "2025-02-10", status: "Pending" }
]);

const newInvoice = ref({
    invoice_no: "",
    amount: null,
    date: "",
});

function createInvoice() {
    if (!newInvoice.value.invoice_no || !newInvoice.value.amount || !newInvoice.value.date) {
        alert("Please fill in all fields.");
        return;
    }

    invoices.value.push({
        id: Date.now(),
        invoice_no: newInvoice.value.invoice_no,
        amount: parseFloat(newInvoice.value.amount),
        date: newInvoice.value.date,
        status: "Pending"
    });

    newInvoice.value.invoice_no = "";
    newInvoice.value.amount = null;
    newInvoice.value.date = "";
}

const expandedRow = ref(null);

function toggleRow(id) {
    expandedRow.value = expandedRow.value === id ? null : id;
}
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
        <!-- 4) INVOICE ISSUED + HISTORY -->
        <!-- =============================== -->
        <div class="bg-white rounded-xl shadow-md border">
            <button @click="showInvoice = !showInvoice"
                    class="w-full flex justify-between items-center p-4 text-left">
                <span class="text-lg font-semibold">Invoice Issued</span>
                <svg :class="showInvoice ? 'rotate-180' : ''" class="h-5 w-5 transition"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-show="showInvoice" class="p-6 border-t space-y-6">

                <!-- CREATE INVOICE FORM -->
                <div class="bg-gray-50 rounded-lg p-4 border space-y-4">
                    <h3 class="font-semibold text-gray-700">Create New Invoice</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm mb-1">Invoice No</label>
                            <input v-model="newInvoice.invoice_no" class="border px-3 py-2 rounded-md w-full"
                                   placeholder="INV-2025-003">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm mb-1">Amount (RM)</label>
                            <input v-model="newInvoice.amount" type="number"
                                   class="border px-3 py-2 rounded-md w-full" placeholder="0.00">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm mb-1">Date</label>
                            <input v-model="newInvoice.date" type="date"
                                   class="border px-3 py-2 rounded-md w-full">
                        </div>
                    </div>

                    <button @click="createInvoice"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Add Invoice
                    </button>
                </div>


                <!-- INVOICE HISTORY TABLE -->
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Invoice History</h3>

                    <table class="min-w-full text-sm divide-y divide-gray-200 bg-white rounded-lg shadow">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Invoice No</th>
                            <th class="px-3 py-2 text-left">Amount</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        <tr v-for="inv in invoices" :key="inv.id">
                            <td class="px-3 py-2">{{ inv.invoice_no }}</td>
                            <td class="px-3 py-2">RM {{ inv.amount.toLocaleString() }}</td>
                            <td class="px-3 py-2">{{ inv.date }}</td>
                            <td class="px-3 py-2">
                                <span :class="inv.status === 'Paid' ? 'text-green-600' : 'text-yellow-600'"
                                      class="font-semibold">
                                    {{ inv.status }}
                                </span>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                </div>

            </div>
        </div>



    </div>
</template>
