<script setup>
import { ref } from "vue";

const props = defineProps({
    project: Object
});

/* -----------------------------
   Collapse States
------------------------------ */
const showBudget = ref(true);
const showClaim = ref(false);
const showPurchase = ref(false);
const showInvoice = ref(false);

/* -----------------------------
   CLAIM HISTORY + NEW CLAIM
------------------------------ */
const claims = ref([
    { id: 1, type: "Travel", amount: 120.50, date: "2025-02-05", status: "Approved" },
    { id: 2, type: "Food & Beverage", amount: 45.90, date: "2025-02-07", status: "Pending" }
]);

const newClaim = ref({
    type: "",
    amount: null,
    date: "",
});

function createClaim() {
    if (!newClaim.value.type || !newClaim.value.amount || !newClaim.value.date) {
        alert("Please fill in all fields.");
        return;
    }

    claims.value.push({
        id: Date.now(),
        type: newClaim.value.type,
        amount: parseFloat(newClaim.value.amount),
        date: newClaim.value.date,
        status: "Pending"
    });

    newClaim.value.type = "";
    newClaim.value.amount = null;
    newClaim.value.date = "";
}


/* -----------------------------
   PURCHASE REQUESTS
------------------------------ */
const purchaseRequests = ref([
    {
        id: 1,
        item: "Laptop",
        qty: 1,
        amount: 3500,
        date: "2025-02-01",
        status: "Approved",
        stages: [
            { title: "Preparing PO", date: "2025-02-01", completed: true },
            { title: "Sent to Supplier", date: "2025-02-02", completed: true },
            { title: "Supplier Processing", date: "2025-02-03", completed: true },
            { title: "Shipped Out", date: "2025-02-05", completed: false },
            { title: "Delivered", date: "-", completed: false },
        ]
    },

    {
        id: 2,
        item: "Printer Toner",
        qty: 3,
        amount: 450,
        date: "2025-02-03",
        status: "Pending",
        stages: [
            { title: "Preparing PO", date: "2025-02-03", completed: true },
            { title: "Sent to Supplier", date: "-", completed: false },
            { title: "Supplier Processing", date: "-", completed: false },
            { title: "Shipped Out", date: "-", completed: false },
            { title: "Delivered", date: "-", completed: false },
        ]
    }
]);


const newPurchase = ref({
    item: "",
    qty: null,
    amount: null,
    date: "",
});

function createPurchaseRequest() {
    if (!newPurchase.value.item || !newPurchase.value.qty || !newPurchase.value.amount || !newPurchase.value.date) {
        alert("Please fill in all fields.");
        return;
    }

    purchaseRequests.value.push({
        id: Date.now(),
        item: newPurchase.value.item,
        qty: newPurchase.value.qty,
        amount: newPurchase.value.amount,
        date: newPurchase.value.date,
        status: "Pending"
    });

    newPurchase.value.item = "";
    newPurchase.value.qty = null;
    newPurchase.value.amount = null;
    newPurchase.value.date = "";
}


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
            <button @click="showBudget = !showBudget" class="w-full flex justify-between items-center p-4 text-left">
                <span class="text-lg font-semibold">Edit Budget</span>
                <svg :class="showBudget ? 'rotate-180' : ''" class="h-5 w-5 transition" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-show="showBudget" class="p-6 border-t space-y-4">
                <p class="text-gray-600">
                    Current Budget:
                    <span class="font-semibold">RM {{ props.project.budget?.toLocaleString() ?? '200,000' }}</span>
                </p>

                <div class="flex gap-4">
                    <input type="number" class="border px-3 py-2 rounded-md w-64" placeholder="Enter new budget" />

                    <button class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Save Budget
                    </button>
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

                <!-- CREATE CLAIM FORM -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-4 border">
                    <h3 class="font-semibold text-gray-700">Submit New Claim</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm mb-1">Claim Type</label>
                            <select v-model="newClaim.type" class="border px-3 py-2 rounded-md w-full">
                                <option disabled value="">Select type</option>
                                <option>Travel</option>
                                <option>Food & Beverage</option>
                                <option>Tools</option>
                                <option>Misc</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm mb-1">Amount</label>
                            <input v-model="newClaim.amount" type="number"
                                   class="border px-3 py-2 rounded-md w-full" placeholder="0.00">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm mb-1">Date</label>
                            <input v-model="newClaim.date" type="date"
                                   class="border px-3 py-2 rounded-md w-full">
                        </div>
                    </div>

                    <button @click="createClaim"
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Submit Claim
                    </button>
                </div>


                <!-- CLAIM HISTORY TABLE -->
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Claim History</h3>

                    <table class="min-w-full text-sm divide-y divide-gray-200 bg-white rounded-lg shadow">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Type</th>
                            <th class="px-3 py-2 text-left">Amount</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        <tr v-for="c in claims" :key="c.id">
                            <td class="px-3 py-2">{{ c.type }}</td>
                            <td class="px-3 py-2">RM {{ c.amount.toFixed(2) }}</td>
                            <td class="px-3 py-2">{{ c.date }}</td>
                            <td class="px-3 py-2">
                                <span :class="c.status === 'Approved' ? 'text-green-600' : 'text-yellow-600'"
                                      class="font-semibold">
                                    {{ c.status }}
                                </span>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

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

                <!-- CREATE PURCHASE REQUEST -->
                <div class="bg-gray-50 rounded-lg p-4 border space-y-4">
                    <h3 class="font-semibold text-gray-700">Create Purchase Request</h3>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="text-sm text-gray-700">Item</label>
                            <input v-model="newPurchase.item"
                                   class="border px-3 py-2 rounded-md w-full" placeholder="Laptop / Tools">
                        </div>

                        <div>
                            <label class="text-sm text-gray-700">Quantity</label>
                            <input v-model="newPurchase.qty"
                                   type="number" class="border px-3 py-2 rounded-md w-full" placeholder="1">
                        </div>

                        <div>
                            <label class="text-sm text-gray-700">Amount (RM)</label>
                            <input v-model="newPurchase.amount"
                                   type="number" class="border px-3 py-2 rounded-md w-full" placeholder="0.00">
                        </div>

                        <div>
                            <label class="text-sm text-gray-700">Date</label>
                            <input v-model="newPurchase.date"
                                   type="date" class="border px-3 py-2 rounded-md w-full">
                        </div>
                    </div>

                    <button @click="createPurchaseRequest"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Submit Purchase Request
                    </button>
                </div>


                <!-- PURCHASE REQUEST HISTORY TABLE WITH EXPANDABLE ROWS -->
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Purchase Request History</h3>

                    <table class="min-w-full text-sm divide-y divide-gray-200 bg-white rounded-lg shadow">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left">Item</th>
                                <th class="px-3 py-2 text-left">Qty</th>
                                <th class="px-3 py-2 text-left">Amount</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Status</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200">

                            <!-- MAIN ROWS -->
                            <template v-for="pr in purchaseRequests" :key="pr.id">

                                <!-- CLICKABLE MAIN ROW -->
                                <tr 
                                    @click="toggleRow(pr.id)"
                                    class="cursor-pointer hover:bg-gray-50 transition"
                                >
                                    <td class="px-3 py-2 flex items-center gap-2">
                                        <svg 
                                            :class="expandedRow === pr.id ? 'rotate-90' : ''"
                                            class="h-4 w-4 text-gray-500 transition"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                        {{ pr.item }}
                                    </td>
                                    <td class="px-3 py-2">{{ pr.qty }}</td>
                                    <td class="px-3 py-2">RM {{ pr.amount.toLocaleString() }}</td>
                                    <td class="px-3 py-2">{{ pr.date }}</td>
                                    <td class="px-3 py-2">
                                        <span 
                                            :class="pr.status === 'Approved' ? 'text-green-600' : 'text-yellow-600'"
                                            class="font-semibold"
                                        >
                                            {{ pr.status }}
                                        </span>
                                    </td>
                                </tr>

                                <!-- EXPANDED CHILD ROW -->
                                <tr v-if="expandedRow === pr.id" class="bg-gray-50">
                                    <td colspan="5" class="px-6 py-4">

                                        <h4 class="font-medium text-gray-700 mb-2">
                                            Progress Stages
                                        </h4>

                                        <!-- STAGE LIST -->
                                        <div class="space-y-3">

                                            <div 
                                                v-for="(stage, idx) in pr.stages"
                                                :key="idx"
                                                class="flex items-start gap-3"
                                            >
                                                <!-- Bullet / Completed indicator -->
                                                <div 
                                                    :class="stage.completed ? 'bg-green-500' : 'bg-gray-400'"
                                                    class="w-3 h-3 rounded-full mt-1"
                                                ></div>

                                                <!-- Title + Date -->
                                                <div>
                                                    <p class="font-medium text-gray-800">{{ stage.title }}</p>
                                                    <p class="text-xs text-gray-500">{{ stage.date }}</p>
                                                </div>
                                            </div>

                                        </div>

                                    </td>
                                </tr>

                            </template>

                        </tbody>
                    </table>
                </div>
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
