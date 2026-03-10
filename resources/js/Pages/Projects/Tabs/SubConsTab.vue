<script setup>
import { computed, ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import { useFormat } from "@/Composables/useFormat";

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    projectSubCons: {
        type: Array,
        default: () => [],
    },
    subConOptions: {
        type: Array,
        default: () => [],
    },
    bankOptions: {
        type: Array,
        default: () => [],
    },
});

const { formatCurrency } = useFormat();

function blankBankAccount() {
    return {
        bank_name: "",
        account_name: "",
        account_no: "",
    };
}

const showCreateModal = ref(false);
const bindSubConId = ref("");
const activeSubConId = ref("");

const bindForm = useForm({
    sub_con_id: "",
});

const createForm = useForm({
    name: "",
    company_name: "",
    email: "",
    phone: "",
    address: "",
    bank_accounts: [blankBankAccount()],
});

const selectedSubCon = computed(() => {
    if (!props.projectSubCons.length) return null;
    const selectedId = Number(activeSubConId.value);
    const match = props.projectSubCons.find((row) => row.id === selectedId);
    return match ?? props.projectSubCons[0];
});

function bindExistingSubCon() {
    bindForm.sub_con_id = Number(bindSubConId.value);
    bindForm.post(route("projects.sub-cons.bind", props.project.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            bindSubConId.value = "";
            bindForm.reset();
        },
    });
}

function addBankAccount() {
    if (createForm.bank_accounts.length >= 10) return;
    createForm.bank_accounts.push(blankBankAccount());
}

function removeBankAccount(index) {
    if (createForm.bank_accounts.length === 1) {
        createForm.bank_accounts[0] = blankBankAccount();
        return;
    }

    createForm.bank_accounts.splice(index, 1);
}

function createAndBindSubCon() {
    createForm.post(route("projects.sub-cons.create", props.project.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.bank_accounts = [blankBankAccount()];
            showCreateModal.value = false;
        },
    });
}

function paymentStatusClass(status) {
    const value = (status ?? "").toLowerCase();
    if (value === "paid") return "bg-emerald-100 text-emerald-700";
    if (value === "partially paid") return "bg-amber-100 text-amber-700";
    if (value === "pending payment") return "bg-orange-100 text-orange-700";
    if (value === "in progress") return "bg-indigo-100 text-indigo-700";
    return "bg-slate-100 text-slate-600";
}

function formatDate(value) {
    if (!value) return "-";
    return new Date(value).toLocaleDateString("en-GB");
}
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 text-sm font-semibold text-slate-800">
                Bind Existing Sub Con
            </div>
            <div class="flex flex-col gap-3 md:flex-row">
                <select
                    v-model="bindSubConId"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm md:max-w-xl"
                >
                    <option value="">Select Sub Con</option>
                    <option v-for="option in subConOptions" :key="option.id" :value="option.id">
                        {{ option.name }}{{ option.company_name ? ` (${option.company_name})` : "" }}
                    </option>
                </select>

                <button
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="bindForm.processing || !bindSubConId"
                    @click="bindExistingSubCon"
                >
                    Bind To Project
                </button>

                <button
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    @click="showCreateModal = true"
                >
                    + Create New Sub Con
                </button>
            </div>
            <p v-if="bindForm.errors.sub_con_id" class="mt-2 text-xs text-red-600">
                {{ bindForm.errors.sub_con_id }}
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-800">
                    Project Sub Con Listing
                </h3>
                <p class="text-xs text-slate-500">
                    Click a row to inspect project progression, invoice amount and payment status.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Sub Con
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Progress
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Invoice
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Paid
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Payment Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="row in projectSubCons"
                            :key="row.id"
                            class="cursor-pointer transition hover:bg-slate-50"
                            :class="{ 'bg-indigo-50/50': selectedSubCon?.id === row.id }"
                            @click="activeSubConId = row.id"
                        >
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium text-slate-800">
                                    {{ row.name }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ row.company_name ?? "-" }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                {{ row.stats.avg_progress_percent }}%
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                {{ formatCurrency(row.stats.invoiced_amount ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                {{ formatCurrency(row.stats.paid_amount ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="paymentStatusClass(row.stats.payment_status)"
                                >
                                    {{ row.stats.payment_status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!projectSubCons.length">
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">
                                No Sub Con bound to this project yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="selectedSubCon" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h4 class="text-base font-semibold text-slate-900">
                        {{ selectedSubCon.name }}
                    </h4>
                    <p class="text-sm text-slate-500">
                        {{ selectedSubCon.company_name ?? "-" }}
                    </p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    Last Task Update: {{ formatDate(selectedSubCon.stats.latest_task_update_at) }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">Total Tasks</div>
                    <div class="text-lg font-semibold text-slate-800">
                        {{ selectedSubCon.stats.total_tasks }}
                    </div>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">Submitted/Verified</div>
                    <div class="text-lg font-semibold text-slate-800">
                        {{ selectedSubCon.stats.submitted_tasks }}/{{ selectedSubCon.stats.verified_tasks }}
                    </div>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">Certified/Paid</div>
                    <div class="text-lg font-semibold text-slate-800">
                        {{ selectedSubCon.stats.certified_tasks }}/{{ selectedSubCon.stats.paid_tasks }}
                    </div>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">Outstanding</div>
                    <div class="text-lg font-semibold text-slate-800">
                        {{ formatCurrency(selectedSubCon.stats.outstanding_amount ?? 0) }}
                    </div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-500">Contact</div>
                    <div class="mt-1 text-sm text-slate-700">
                        {{ selectedSubCon.email ?? "-" }}
                    </div>
                    <div class="text-sm text-slate-700">
                        {{ selectedSubCon.phone ?? "-" }}
                    </div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-500">Bank Accounts</div>
                    <div v-if="selectedSubCon.bank_accounts?.length" class="mt-1 space-y-1 text-sm text-slate-700">
                        <div
                            v-for="account in selectedSubCon.bank_accounts"
                            :key="account.id"
                        >
                            {{ account.bank_name }} - {{ account.account_no || "-" }}
                            <span v-if="account.account_name">({{ account.account_name }})</span>
                        </div>
                    </div>
                    <div v-else class="mt-1 text-sm text-slate-500">-</div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="showCreateModal"
        class="fixed inset-0 z-50 bg-slate-900/50 px-4 py-6 backdrop-blur-sm"
        @click.self="showCreateModal = false"
    >
        <div class="mx-auto max-h-full w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">
                        Create & Bind Sub Con
                    </h3>
                    <button class="text-slate-400 hover:text-slate-600" @click="showCreateModal = false">
                        <i class="mdi mdi-close text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Name *</label>
                        <input v-model="createForm.name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Company</label>
                        <input v-model="createForm.company_name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Email</label>
                        <input v-model="createForm.email" type="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Phone</label>
                        <input v-model="createForm.phone" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="text-sm font-semibold text-slate-800">Bank Accounts</div>
                        <button
                            type="button"
                            class="rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700"
                            @click="addBankAccount"
                        >
                            + Add
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(account, index) in createForm.bank_accounts"
                            :key="index"
                            class="rounded-md border border-slate-200 bg-slate-50 p-2"
                        >
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                <select
                                    v-model="account.bank_name"
                                    class="rounded-md border border-slate-300 px-2.5 py-2 text-sm"
                                >
                                    <option value="">Select bank</option>
                                    <option v-for="bank in bankOptions" :key="bank" :value="bank">
                                        {{ bank }}
                                    </option>
                                </select>
                                <input
                                    v-model="account.account_no"
                                    class="rounded-md border border-slate-300 px-2.5 py-2 text-sm"
                                    placeholder="Account no"
                                />
                            </div>
                            <div class="mt-2 flex gap-2">
                                <input
                                    v-model="account.account_name"
                                    class="w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm"
                                    placeholder="Account holder name (optional)"
                                />
                                <button
                                    type="button"
                                    class="rounded-md px-2.5 py-2 text-xs text-red-600 hover:bg-red-50"
                                    @click="removeBankAccount(index)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Address</label>
                    <textarea
                        v-model="createForm.address"
                        rows="3"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    ></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                    @click="showCreateModal = false"
                >
                    Cancel
                </button>
                <button
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="createForm.processing"
                    @click="createAndBindSubCon"
                >
                    Create & Bind
                </button>
            </div>
        </div>
    </div>
</template>
