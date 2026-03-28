<script setup>
import { computed, inject, ref } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import POShowA4 from "@/Pages/Transactions/PurchaseOrder/Partials/POShowA4.vue";
import Modal from "@/Components/Modal.vue";
import { useFormat } from "@/Composables/useFormat";

const props = defineProps({
    supplier: { type: Object, required: true },
    purchaseOrders: { type: Array, default: () => [] },
    claims: { type: Array, default: () => [] },
});

const toast = inject("toast", null);
const { formatCurrency, formatDateTime } = useFormat();

const activeTab = ref("po");
const poQuery = ref("");
const claimQuery = ref("");

const selectedPO = ref(null);
const showPoConfirmModal = ref(false);
const poConfirmForm = ref({
    order_date: "",
    signed_po: null,
});

const showPoA4Modal = ref(false);
const isPoA4Loading = ref(false);
const selectedPoA4 = ref(null);
const poA4Company = ref(null);

const claimCreateForm = ref({
    po_uuid: "",
    title: "",
    claimed_amount: "",
    proforma_invoice: null,
    proof_attachments: [],
    remark: "",
});

const filteredPOs = computed(() => {
    const q = poQuery.value.trim().toLowerCase();
    if (!q) return props.purchaseOrders;

    return props.purchaseOrders.filter((po) => {
        const project = po?.purchase_request?.project;
        const text = [
            po?.code,
            po?.purchase_request?.code,
            po?.purchase_request?.title,
            project?.code,
            project?.name,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return text.includes(q);
    });
});

const filteredClaims = computed(() => {
    const q = claimQuery.value.trim().toLowerCase();
    if (!q) return props.claims;

    return props.claims.filter((claim) => {
        const text = [
            claim?.claim_no,
            claim?.title,
            claim?.purchase_order?.code,
            claim?.project?.code,
            claim?.project?.name,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return text.includes(q);
    });
});

const poCounts = computed(() => {
    const list = props.purchaseOrders ?? [];
    return {
        total: list.length,
        pending: list.filter((po) => po?.status === "issued").length,
        confirmed: list.filter((po) => po?.status === "confirmed").length,
    };
});

const claimCounts = computed(() => {
    const list = props.claims ?? [];
    return {
        total: list.length,
        submitted: list.filter((claim) => claim?.status === "submitted").length,
        processing: list.filter((claim) =>
            ["project_verified", "contra_verified", "ceo_gm_approved", "appealed", "accepted_by_subcon", "payment_in_progress", "pending_real_invoice_upload"].includes(claim?.status)
        ).length,
        completed: list.filter((claim) => claim?.status === "real_invoice_uploaded").length,
    };
});

function openPOConfirm(po) {
    selectedPO.value = po;
    poConfirmForm.value.order_date = po?.order_date ? String(po.order_date).slice(0, 10) : "";
    poConfirmForm.value.signed_po = null;
    showPoConfirmModal.value = true;
}

async function openPOA4(po) {
    try {
        isPoA4Loading.value = true;
        const response = await axios.get(route("supplier.purchase-orders.show", { po: po.uuid }));
        selectedPoA4.value = response?.data?.po ?? null;
        poA4Company.value = response?.data?.company ?? null;
        showPoA4Modal.value = true;
    } catch (error) {
        toast?.value?.show(error?.response?.data?.message || "Failed to load PO preview", "error");
    } finally {
        isPoA4Loading.value = false;
    }
}

function submitPOConfirmation() {
    if (!selectedPO.value) return;

    const fd = new FormData();
    fd.append("order_date", poConfirmForm.value.order_date || "");
    if (poConfirmForm.value.signed_po) {
        fd.append("signed_po", poConfirmForm.value.signed_po);
    }

    router.post(route("supplier.purchase-orders.confirm", { po: selectedPO.value.uuid }), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showPoConfirmModal.value = false;
            toast?.value?.show("PO confirmed successfully.", "success");
        },
        onError: (errors) => {
            const first = Object.values(errors || {})[0];
            toast?.value?.show(first || "Failed to confirm PO.", "error");
        },
    });
}

function submitClaim() {
    const fd = new FormData();
    fd.append("po_uuid", claimCreateForm.value.po_uuid || "");
    fd.append("title", claimCreateForm.value.title || "");
    fd.append("claimed_amount", claimCreateForm.value.claimed_amount || "0");
    if (claimCreateForm.value.proforma_invoice) {
        fd.append("proforma_invoice", claimCreateForm.value.proforma_invoice);
    }
    claimCreateForm.value.proof_attachments.forEach((file) => {
        fd.append("proof_attachments[]", file);
    });
    fd.append("remark", claimCreateForm.value.remark || "");

    router.post(route("supplier.claims.store"), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            claimCreateForm.value = {
                po_uuid: "",
                title: "",
                claimed_amount: "",
                proforma_invoice: null,
                proof_attachments: [],
                remark: "",
            };
            toast?.value?.show("Claim submitted successfully.", "success");
        },
        onError: (errors) => {
            const first = Object.values(errors || {})[0];
            toast?.value?.show(first || "Failed to submit claim.", "error");
        },
    });
}

function logout() {
    router.post(route("supplier.logout"));
}

function statusBadgeClass(status) {
    if (status === "issued") return "bg-amber-100 text-amber-700";
    if (status === "confirmed") return "bg-emerald-100 text-emerald-700";
    if (status === "submitted") return "bg-blue-100 text-blue-700";
    return "bg-gray-100 text-gray-700";
}
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <div class="mx-auto max-w-7xl px-4 py-6 space-y-6">
            <section class="rounded-2xl border border-cyan-100 bg-gradient-to-r from-cyan-50 via-teal-50 to-emerald-50 p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.2em] text-cyan-700 font-semibold">Partner Workspace</p>
                        <h1 class="mt-1 text-2xl font-bold text-slate-900">Supplier Portal</h1>
                        <div class="mt-2 text-sm text-slate-700">{{ supplier.company_name }}</div>
                        <div class="text-xs text-slate-600">Login ID: {{ supplier.login_identity_no }}</div>
                    </div>
                    <button
                        class="rounded-md border border-cyan-200 bg-white px-3 py-1.5 text-sm font-medium text-cyan-700 hover:bg-cyan-50"
                        @click="logout"
                    >
                        Logout
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-cyan-200 bg-white/80 p-3">
                        <div class="text-[11px] uppercase tracking-wide text-cyan-700 font-semibold">PO Pending</div>
                        <div class="mt-1 text-2xl font-bold text-slate-900">{{ poCounts.pending }}</div>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-white/80 p-3">
                        <div class="text-[11px] uppercase tracking-wide text-emerald-700 font-semibold">PO Confirmed</div>
                        <div class="mt-1 text-2xl font-bold text-slate-900">{{ poCounts.confirmed }}</div>
                    </div>
                    <div class="rounded-xl border border-teal-200 bg-white/80 p-3">
                        <div class="text-[11px] uppercase tracking-wide text-teal-700 font-semibold">Total Claims</div>
                        <div class="mt-1 text-2xl font-bold text-slate-900">{{ claimCounts.total }}</div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        class="rounded-full px-3 py-1.5 text-sm font-medium border transition"
                        :class="activeTab === 'po' ? 'border-cyan-500 bg-cyan-50 text-cyan-700 shadow-sm' : 'border-slate-300 text-slate-700 hover:border-cyan-200 hover:text-cyan-700'"
                        @click="activeTab = 'po'"
                    >
                        PO Confirmation
                        <span class="ml-1 rounded-full bg-white/80 px-1.5 py-0.5 text-[11px]">{{ poCounts.total }}</span>
                    </button>
                    <button
                        class="rounded-full px-3 py-1.5 text-sm font-medium border transition"
                        :class="activeTab === 'claims' ? 'border-cyan-500 bg-cyan-50 text-cyan-700 shadow-sm' : 'border-slate-300 text-slate-700 hover:border-cyan-200 hover:text-cyan-700'"
                        @click="activeTab = 'claims'"
                    >
                        Claims
                        <span class="ml-1 rounded-full bg-white/80 px-1.5 py-0.5 text-[11px]">{{ claimCounts.total }}</span>
                    </button>
                </div>
            </section>

            <div v-if="activeTab === 'po'" class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-cyan-700">Filter Purchase Orders</div>
                    <input
                        v-model="poQuery"
                        type="text"
                        placeholder="PO No / PR / Project"
                        class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-100"
                    />
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-4 py-2.5">PO</th>
                                    <th class="px-4 py-2.5">Title</th>
                                    <th class="px-4 py-2.5">Project</th>
                                    <th class="px-4 py-2.5 text-right">Amount</th>
                                    <th class="px-4 py-2.5">Status</th>
                                    <th class="px-4 py-2.5 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="po in filteredPOs" :key="po.uuid" class="hover:bg-cyan-50/30">
                                    <td class="px-4 py-2.5">
                                        <div class="font-semibold text-slate-900">{{ po.code }}</div>
                                        <div class="text-xs text-slate-500">{{ formatDateTime(po.order_date) }}</div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700">
                                        {{ po.purchase_request?.title || "-" }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700">
                                        {{ po.purchase_request?.project?.code }} - {{ po.purchase_request?.project?.name }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right tabular-nums font-medium text-slate-800">
                                        {{ formatCurrency(po.total_amount || 0) }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                            :class="statusBadgeClass(po.status)"
                                        >
                                            {{ po.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button
                                                class="inline-flex h-7 items-center rounded-md border border-slate-300 bg-white px-2 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                                :disabled="isPoA4Loading"
                                                @click="openPOA4(po)"
                                            >
                                                View A4
                                            </button>
                                            <button
                                                class="inline-flex h-7 items-center rounded-md border border-cyan-200 bg-cyan-50 px-2 text-xs font-medium text-cyan-700 hover:bg-cyan-100 disabled:opacity-40"
                                                :disabled="po.status !== 'issued'"
                                                @click="openPOConfirm(po)"
                                            >
                                                Confirm
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredPOs.length">
                                    <td colspan="6" class="px-4 py-7 text-center text-sm text-slate-400">
                                        No purchase orders found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div v-else class="space-y-4">
                <section class="rounded-2xl border border-teal-100 bg-white p-4 shadow-sm space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-sm font-semibold text-slate-900">Submit Invoice</div>
                        <div class="text-xs text-slate-500">
                            Submitted: {{ claimCounts.submitted }} | Processing: {{ claimCounts.processing }} | Completed: {{ claimCounts.completed }}
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm font-medium text-slate-700">PO</label>
                            <select v-model="claimCreateForm.po_uuid" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-400 focus:ring-teal-100">
                                <option value="">Select PO</option>
                                <option v-for="po in purchaseOrders" :key="po.uuid" :value="po.uuid">
                                    {{ po.code }} - {{ po.purchase_request?.project?.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Title</label>
                            <input v-model="claimCreateForm.title" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-400 focus:ring-teal-100" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Claim Amount</label>
                            <input v-model="claimCreateForm.claimed_amount" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-400 focus:ring-teal-100" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Proforma Invoice</label>
                            <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="mt-1 block w-full text-sm"
                                @change="(e) => (claimCreateForm.proforma_invoice = e.target.files?.[0] ?? null)"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Proof Attachments (Multiple)</label>
                        <input
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="mt-1 block w-full text-sm"
                            @change="(e) => (claimCreateForm.proof_attachments = Array.from(e.target.files ?? []))"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Remark</label>
                        <textarea v-model="claimCreateForm.remark" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-400 focus:ring-teal-100"></textarea>
                    </div>
                    <div class="text-right">
                        <button class="rounded-md bg-teal-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-700" @click="submitClaim">
                            Submit Invoice
                        </button>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-teal-700">Search Claims</div>
                    <input
                        v-model="claimQuery"
                        type="text"
                        placeholder="Claim No / PO / Project"
                        class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-400 focus:ring-teal-100"
                    />
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-4 py-2.5">Claim</th>
                                    <th class="px-4 py-2.5">Project</th>
                                    <th class="px-4 py-2.5 text-right">Amount</th>
                                    <th class="px-4 py-2.5">Status</th>
                                    <th class="px-4 py-2.5">Attachments</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="claim in filteredClaims" :key="claim.uuid" class="hover:bg-teal-50/30">
                                <td class="px-4 py-2.5">
                                    <div class="font-semibold text-slate-900">{{ claim.claim_no }}</div>
                                    <div class="text-xs text-slate-500">{{ claim.title }}</div>
                                    <div class="text-xs text-slate-500">PO: {{ claim.purchase_order?.code || "-" }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-slate-700">
                                    {{ claim.project?.code }} - {{ claim.project?.name }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums font-medium text-slate-800">
                                    {{ formatCurrency(claim.claimed_amount || 0) }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex items-center rounded-full bg-cyan-100 text-cyan-700 px-2.5 py-0.5 text-xs font-semibold">
                                        {{ claim.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-700">
                                    <a
                                        class="font-medium text-teal-600 hover:text-teal-800"
                                        :href="route('supplier.claims.proforma.download', { claim: claim.uuid })"
                                    >
                                        Proforma
                                    </a>
                                    <template v-for="(proof, idx) in (claim.proof_attachments || [])" :key="`${claim.uuid}-proof-${idx}`">
                                        <span class="mx-1 text-slate-400">|</span>
                                        <a
                                            class="font-medium text-teal-600 hover:text-teal-800"
                                            :href="`${route('supplier.claims.proof.download', { claim: claim.uuid })}?idx=${idx}`"
                                        >
                                            Proof {{ idx + 1 }}
                                        </a>
                                    </template>
                                </td>
                                </tr>
                                <tr v-if="!filteredClaims.length">
                                    <td colspan="5" class="px-4 py-7 text-center text-sm text-slate-400">
                                        No claims found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <Modal :show="showPoConfirmModal" max-width="lg" @close="showPoConfirmModal = false">
        <div class="space-y-4">
            <div class="text-lg font-semibold text-slate-800">Confirm PO</div>
            <div class="text-sm text-slate-600">{{ selectedPO?.code }}</div>
            <div class="text-sm text-slate-600">Title: {{ selectedPO?.purchase_request?.title || "-" }}</div>

            <div>
                <label class="text-sm font-medium text-slate-700">Order Date</label>
                <input v-model="poConfirmForm.order_date" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Signed PO Upload</label>
                <input
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    class="mt-1 block w-full text-sm"
                    @change="(e) => (poConfirmForm.signed_po = e.target.files?.[0] ?? null)"
                />
                <div v-if="selectedPO?.signed_po" class="mt-1 text-xs text-slate-500">
                    Existing: {{ selectedPO.signed_po.name }}
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100" @click="showPoConfirmModal = false">
                    Cancel
                </button>
                <button class="rounded-md bg-cyan-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-cyan-700" @click="submitPOConfirmation">
                    Confirm PO
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showPoA4Modal" max-width="7xl" @close="showPoA4Modal = false">
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="text-sm text-slate-500">Purchase Order Preview</div>
                <button class="text-sm text-cyan-600 hover:text-cyan-700" @click="window.print()">Print / Save PDF</button>
            </div>
            <div class="max-h-[85vh] overflow-auto border rounded bg-gray-50 p-6 flex items-start justify-center">
                <POShowA4 v-if="selectedPoA4" :po="selectedPoA4" :company="poA4Company" />
            </div>
        </div>
    </Modal>
</template>
