<script setup>
import { computed, inject, ref } from "vue";
import { router } from "@inertiajs/vue3";
import { useFormat } from "@/Composables/useFormat";

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    subCons: {
        type: Array,
        default: () => [],
    },
    subConClaims: {
        type: Array,
        default: () => [],
    },
});

const toast = inject("toast", null);
const { formatCurrency, formatDateTime } = useFormat();
const MAX_VERIFY_PERCENT = 1000;

const activeStage = ref("all");

const stageTabs = [
    { key: "all", label: "All" },
    { key: "submitted", label: "Submitted" },
    { key: "project_verified", label: "Project Verified" },
    { key: "contra_verified", label: "Contra Verified" },
    { key: "ceo_gm_approved", label: "CEO/GM Approved" },
    { key: "appealed", label: "Appealed" },
    { key: "accepted_by_subcon", label: "Accepted by Sub Con" },
    { key: "payment_in_progress", label: "Payment In Progress" },
    { key: "pending_real_invoice_upload", label: "Pending Real Invoice Upload" },
    { key: "real_invoice_uploaded", label: "Real Invoice Uploaded" },
];

const claims = computed(() => props.subConClaims ?? []);
const filteredClaims = computed(() => {
    if (activeStage.value === "all") return claims.value;
    return claims.value.filter((claim) => (claim.status || "") === activeStage.value);
});

const selectedClaim = ref(null);
const actionModal = ref({
    open: false,
    type: "",
    title: "",
    amount: "",
    percent: "100",
    remark: "",
    work_done_note: "",
    site_reference: "",
    deduction_reason: "",
});

const invoiceModal = ref({
    open: false,
    real_invoice_no: "",
    real_invoice_date: "",
    real_invoice_amount: "",
    real_invoice: null,
    remark: "",
});

function stageCount(stage) {
    if (stage === "all") return claims.value.length;
    return claims.value.filter((item) => item.status === stage).length;
}

function statusLabel(status) {
    if (status === "submitted") return "Submitted";
    if (status === "project_verified") return "Project Verified";
    if (status === "contra_verified") return "Contra Verified";
    if (status === "ceo_gm_approved") return "CEO / GM Approved";
    if (status === "appealed") return "Appealed";
    if (status === "accepted_by_subcon") return "Accepted by Sub Con";
    if (status === "payment_in_progress") return "Payment In Progress";
    if (status === "pending_real_invoice_upload") return "Pending Real Invoice Upload";
    if (status === "real_invoice_uploaded") return "Real Invoice Uploaded";
    return status || "-";
}

function statusTone(status) {
    if (status === "submitted") return "bg-blue-100 text-blue-700";
    if (status === "project_verified") return "bg-indigo-100 text-indigo-700";
    if (status === "contra_verified") return "bg-purple-100 text-purple-700";
    if (status === "ceo_gm_approved") return "bg-emerald-100 text-emerald-700";
    if (status === "appealed") return "bg-amber-100 text-amber-700";
    if (status === "accepted_by_subcon") return "bg-cyan-100 text-cyan-700";
    if (status === "payment_in_progress") return "bg-sky-100 text-sky-700";
    if (status === "pending_real_invoice_upload") return "bg-teal-100 text-teal-700";
    if (status === "real_invoice_uploaded") return "bg-green-100 text-green-700";
    return "bg-gray-100 text-gray-700";
}

function claimSubConName(claim) {
    return claim?.sub_con?.company_name || claim?.sub_con?.name || "-";
}

function claimItems(claim) {
    return Array.isArray(claim?.claim_items) ? claim.claim_items : [];
}

function showError(errors, fallback = "Action failed.") {
    const first = Object.values(errors || {})[0];
    const message = Array.isArray(first) ? first[0] : first;
    toast?.value?.show(message || fallback, "error");
}

function refreshClaims() {
    router.reload({
        only: ["subConClaims"],
        preserveScroll: true,
    });
}

function openAction(type, claim) {
    if (type === "prepare_payment") {
        router.post(
            route("projects.sub-con-claims.prepare-payment-slip", {
                project: props.project.uuid,
                claim: claim.uuid,
            }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.value?.show("Payment slip generated. Continue in Payment Slips module.", "success");
                    refreshClaims();
                    const url = route("payment-slips.index", { tab: "pending" });
                    const popup = window.open(url, "_blank", "noopener");
                    if (!popup) {
                        router.visit(url);
                    }
                },
                onError: (errors) => showError(errors),
            }
        );
        return;
    }

    if (type === "mark_payment_done") {
        router.post(
            route("projects.sub-con-claims.mark-payment-completed", {
                project: props.project.uuid,
                claim: claim.uuid,
            }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.value?.show("Payment marked completed. Sub Con can upload real invoice now.", "success");
                    refreshClaims();
                },
                onError: (errors) => showError(errors),
            }
        );
        return;
    }

    selectedClaim.value = claim;

    const baseAmount =
        type === "project_verify"
            ? Number(claim?.claimed_amount ?? 0)
            : type === "contra_verify"
            ? Number(claim?.project_verified_amount ?? claim?.claimed_amount ?? 0)
            : Number(
                  claim?.approved_amount ??
                      claim?.contra_verified_amount ??
                      claim?.project_verified_amount ??
                      claim?.claimed_amount ??
                      0
              );

    actionModal.value = {
        open: true,
        type,
        title:
            type === "project_verify"
                ? "Project Verify"
                : type === "contra_verify"
                ? "Contra Verify"
                : type === "approve"
                ? "CEO / GM Approve"
                : "Action",
        amount: String(baseAmount),
        percent: "100",
        remark: "",
        work_done_note: "",
        site_reference: "",
        deduction_reason: "",
    };

    if (["project_verify", "contra_verify", "approve"].includes(type)) {
        syncVerifyAmountFromPercent();
    }
}

function closeActionModal() {
    actionModal.value.open = false;
    selectedClaim.value = null;
}

function claimSubmissionRemark(claim) {
    const logs = Array.isArray(claim?.remark_log) ? claim.remark_log : [];
    const submitLog = logs.find((item) => item?.action === "submit");
    const text = String(submitLog?.remark || "").trim();
    return text || "-";
}

function previousDecisionHistory(claim) {
    const logs = Array.isArray(claim?.remark_log) ? claim.remark_log : [];
    if (!logs.length) return [];

    return [...logs]
        .reverse()
        .filter((item) => ["ceo_gm_approve", "contra_verify", "project_verify"].includes(String(item?.action || "")))
        .map((decisionLog) => {
            const amounts = decisionLog?.amounts || {};
            const amount =
                amounts.approved_amount ??
                amounts.contra_verified_amount ??
                amounts.project_verified_amount ??
                null;

            const percent =
                amounts.approved_percent ??
                amounts.contra_verified_percent ??
                amounts.project_verified_percent ??
                null;

            return {
                amount: amount !== null ? Number(amount) : null,
                percent: percent !== null ? Number(percent) : null,
                by: decisionLog?.by || "-",
                at: decisionLog?.at || null,
                action: String(decisionLog?.action || ""),
            };
        })
        .filter((entry) => entry.amount !== null || entry.percent !== null)
        .slice(0, 6);
}

function shouldShowPreviousDecisionBlock() {
    return ["contra_verify", "approve"].includes(actionModal.value.type);
}

function previousDecisionLabel(action) {
    if (action === "ceo_gm_approve") return "CEO/GM";
    if (action === "contra_verify") return "Contra";
    if (action === "project_verify") return "Project";
    return "Previous";
}

function actionBaseAmount() {
    const claim = selectedClaim.value;
    if (!claim) return 0;

    if (actionModal.value.type === "project_verify") {
        return Number(claim.claimed_amount || 0);
    }

    if (actionModal.value.type === "contra_verify") {
        return Number(claim.project_verified_amount ?? claim.claimed_amount ?? 0);
    }

    return Number(
        claim?.approved_amount ??
            claim?.contra_verified_amount ??
            claim?.project_verified_amount ??
            claim?.claimed_amount ??
            0
    );
}

function clampPercent(value) {
    const number = Number(value);
    if (!Number.isFinite(number)) return 0;
    return Math.max(0, Math.min(MAX_VERIFY_PERCENT, number));
}

function syncVerifyAmountFromPercent() {
    const baseAmount = actionBaseAmount();
    const percent = clampPercent(actionModal.value.percent);
    actionModal.value.percent = String(percent);
    const amount = ((baseAmount * percent) / 100).toFixed(2);
    actionModal.value.amount = amount;
}

function isReviewStep() {
    return ["project_verify", "contra_verify", "approve"].includes(actionModal.value.type);
}

function isPercentDrivenStep() {
    return ["project_verify", "contra_verify", "approve"].includes(actionModal.value.type);
}

function buildVerificationRemark() {
    const lines = [];
    const remark = String(actionModal.value.remark || "").trim();
    const workDone = String(actionModal.value.work_done_note || "").trim();
    const siteRef = String(actionModal.value.site_reference || "").trim();
    const deduction = String(actionModal.value.deduction_reason || "").trim();

    if (remark) lines.push(remark);
    if (workDone) lines.push(`Work done note: ${workDone}`);
    if (siteRef) lines.push(`Site reference: ${siteRef}`);
    if (deduction) lines.push(`Deduction reason: ${deduction}`);

    return lines.join("\n").trim() || null;
}

function submitAction() {
    if (!selectedClaim.value) return;

    const claim = selectedClaim.value;
    const type = actionModal.value.type;
    const isPercentStep = ["project_verify", "contra_verify", "approve"].includes(type);
    const verifyPercent = clampPercent(actionModal.value.percent);

    if (isPercentStep) {
        syncVerifyAmountFromPercent();
        if (
            verifyPercent < 100 &&
            !String(actionModal.value.deduction_reason || "").trim() &&
            !String(actionModal.value.remark || "").trim()
        ) {
            toast?.value?.show("Please add deduction reason or remark for partial verification.", "error");
            return;
        }
    }

    if (type === "project_verify") {
        router.post(
            route("projects.sub-con-claims.verify-project", {
                project: props.project.uuid,
                claim: claim.uuid,
            }),
            {
                verified_amount: actionModal.value.amount,
                verified_percent: verifyPercent,
                remark: buildVerificationRemark(),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.value?.show("Verified by Project Department.", "success");
                    closeActionModal();
                    refreshClaims();
                },
                onError: (errors) => showError(errors),
            }
        );
        return;
    }

    if (type === "contra_verify") {
        router.post(
            route("projects.sub-con-claims.verify-contra", {
                project: props.project.uuid,
                claim: claim.uuid,
            }),
            {
                verified_amount: actionModal.value.amount,
                verified_percent: verifyPercent,
                remark: buildVerificationRemark(),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.value?.show("Verified by Contra.", "success");
                    closeActionModal();
                    refreshClaims();
                },
                onError: (errors) => showError(errors),
            }
        );
        return;
    }

    if (type === "approve") {
        router.post(
            route("projects.sub-con-claims.approve", {
                project: props.project.uuid,
                claim: claim.uuid,
            }),
            {
                approved_amount: actionModal.value.amount,
                approved_percent: verifyPercent,
            remark: buildVerificationRemark(),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.value?.show("Approved by CEO/GM.", "success");
                    closeActionModal();
                    refreshClaims();
                },
                onError: (errors) => showError(errors),
            }
        );
        return;
    }

    if (type === "prepare_payment") {
        router.post(
            route("projects.sub-con-claims.prepare-payment-slip", {
                project: props.project.uuid,
                claim: claim.uuid,
            }),
            {
                payment_slip_no: actionModal.value.amount || null,
                remark: actionModal.value.remark || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.value?.show("Payment slip prepared. Waiting Sub Con real invoice upload.", "success");
                    closeActionModal();
                    refreshClaims();
                },
                onError: (errors) => showError(errors),
            }
        );
        return;
    }

    router.post(
        route("projects.sub-con-claims.sub-con-decision", {
            project: props.project.uuid,
            claim: claim.uuid,
        }),
        {
            decision: type === "accept" ? "accept" : "appeal",
            remark: actionModal.value.remark || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show(
                    type === "accept" ? "Accepted by Sub Con." : "Claim appealed by Sub Con.",
                    "success"
                );
                closeActionModal();
                refreshClaims();
            },
            onError: (errors) => showError(errors),
        }
    );
}

function openInvoiceModal(claim) {
    selectedClaim.value = claim;
    invoiceModal.value = {
        open: true,
        real_invoice_no: "",
        real_invoice_date: "",
        real_invoice_amount: String(claim.approved_amount ?? claim.claimed_amount ?? 0),
        real_invoice: null,
        remark: "",
    };
}

function closeInvoiceModal() {
    invoiceModal.value.open = false;
    selectedClaim.value = null;
}

function submitRealInvoice() {
    if (!selectedClaim.value) return;
    if (!invoiceModal.value.real_invoice) {
        toast?.value?.show("Please upload real invoice.", "error");
        return;
    }

    const fd = new FormData();
    fd.append("real_invoice_no", invoiceModal.value.real_invoice_no);
    fd.append("real_invoice_date", invoiceModal.value.real_invoice_date);
    fd.append("real_invoice_amount", invoiceModal.value.real_invoice_amount);
    fd.append("real_invoice", invoiceModal.value.real_invoice);
    fd.append("remark", invoiceModal.value.remark || "");

    router.post(
        route("projects.sub-con-claims.real-invoice.upload", {
            project: props.project.uuid,
            claim: selectedClaim.value.uuid,
        }),
        fd,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Real invoice uploaded.", "success");
                closeInvoiceModal();
                refreshClaims();
            },
            onError: (errors) => showError(errors),
        }
    );
}
</script>

<template>
    <div class="space-y-5">
        <div class="bg-white border rounded-lg p-4">
            <h3 class="font-semibold text-gray-800 mb-1">Sub Con Claims</h3>
            <p class="text-xs text-gray-500 mb-4">
                Workflow: Submit Proforma > Project Verify > Contra Verify > CEO/GM Approve > Sub Con Accept/Appeal > Generate Payment Slip > Payment Done > Real Invoice Upload
            </p>
            <p class="text-xs text-indigo-600">
                Submission, Sub Con accept/appeal, and pending real invoice upload are handled in Sub Con Portal.
            </p>
        </div>

        <div class="bg-white border rounded-lg p-4">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="stage in stageTabs"
                    :key="stage.key"
                    type="button"
                    class="px-3 py-1.5 text-xs rounded-full border"
                    :class="activeStage === stage.key
                        ? 'bg-indigo-600 border-indigo-600 text-white'
                        : 'bg-white border-gray-300 text-gray-700 hover:border-indigo-400 hover:text-indigo-700'"
                    @click="activeStage = stage.key"
                >
                    {{ stage.label }} ({{ stageCount(stage.key) }})
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Claim</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Sub Con</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Amounts</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Files</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="claim in filteredClaims" :key="claim.uuid">
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-800">{{ claim.claim_no || "-" }}</div>
                                <div class="text-gray-600">{{ claim.title }}</div>
                                <div class="text-xs text-gray-500">PO: {{ claim.purchase_order?.code || "-" }}</div>
                                <div class="text-xs text-gray-500">{{ formatDateTime(claim.created_at) || "-" }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-800">{{ claimSubConName(claim) }}</div>
                                <div class="text-xs text-amber-600" v-if="(claim.appeal_round ?? 0) > 0">
                                    Appeal round: {{ claim.appeal_round }}
                                </div>
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <div>Claimed: {{ formatCurrency(claim.claimed_amount || 0) }}</div>
                                <div>Project: {{ formatCurrency(claim.project_verified_amount || 0) }}</div>
                                <div>Contra: {{ formatCurrency(claim.contra_verified_amount || 0) }}</div>
                                <div>Approved: {{ formatCurrency(claim.approved_amount || 0) }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-full text-xs font-medium" :class="statusTone(claim.status)">
                                    {{ statusLabel(claim.status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-col items-start gap-1 text-xs">
                                    <a
                                        :href="route('projects.sub-con-claims.proforma.download', { project: project.uuid, claim: claim.uuid })"
                                        class="text-indigo-600 hover:text-indigo-700"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Proforma
                                    </a>
                                    <a
                                        v-for="(proof, idx) in (claim.proof_attachments || [])"
                                        :key="`${claim.uuid}-proof-${idx}`"
                                        :href="`${route('projects.sub-con-claims.proof.download', { project: project.uuid, claim: claim.uuid })}?idx=${idx}`"
                                        class="text-fuchsia-600 hover:text-fuchsia-700"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Proof {{ idx + 1 }}
                                    </a>
                                    <a
                                        v-if="claim.real_invoice_name"
                                        :href="route('projects.sub-con-claims.real-invoice.download', { project: project.uuid, claim: claim.uuid })"
                                        class="text-emerald-600 hover:text-emerald-700"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Real Invoice
                                    </a>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                <div class="flex flex-wrap justify-end gap-1.5">
                                    <button
                                        v-if="['submitted', 'appealed'].includes(claim.status)"
                                        type="button"
                                        class="px-2.5 py-1 text-xs rounded bg-blue-50 text-blue-700 hover:bg-blue-100"
                                        @click="openAction('project_verify', claim)"
                                    >
                                        Project Verify
                                    </button>
                                    <button
                                        v-if="claim.status === 'project_verified'"
                                        type="button"
                                        class="px-2.5 py-1 text-xs rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                                        @click="openAction('contra_verify', claim)"
                                    >
                                        Contra Verify
                                    </button>
                                    <button
                                        v-if="claim.status === 'contra_verified'"
                                        type="button"
                                        class="px-2.5 py-1 text-xs rounded bg-purple-50 text-purple-700 hover:bg-purple-100"
                                        @click="openAction('approve', claim)"
                                    >
                                        CEO/GM Approve
                                    </button>
                                    <button
                                        v-if="claim.status === 'ceo_gm_approved'"
                                        type="button"
                                        class="px-2.5 py-1 text-xs rounded bg-gray-100 text-gray-700"
                                    >
                                        Waiting Sub Con Decision
                                    </button>
                                    <button
                                        v-if="claim.status === 'accepted_by_subcon'"
                                        type="button"
                                        class="px-2.5 py-1 text-xs rounded bg-cyan-50 text-cyan-700 hover:bg-cyan-100"
                                        @click="openAction('prepare_payment', claim)"
                                    >
                                        Generate Payment Slip
                                    </button>
                                    <button
                                        v-if="claim.status === 'payment_in_progress'"
                                        type="button"
                                        class="px-2.5 py-1 text-xs rounded bg-sky-50 text-sky-700 hover:bg-sky-100"
                                        @click="openAction('mark_payment_done', claim)"
                                    >
                                        Payment Done
                                    </button>
                                    <button
                                        v-if="claim.status === 'pending_real_invoice_upload'"
                                        type="button"
                                        class="px-2.5 py-1 text-xs rounded bg-gray-100 text-gray-700"
                                    >
                                        Waiting Sub Con Upload
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="filteredClaims.length === 0">
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">
                                No claims in this stage.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div
        v-if="actionModal.open"
        class="fixed inset-0 z-50 bg-black/40 flex items-start justify-center p-4 md:py-8 overflow-y-auto"
    >
        <div class="bg-white rounded-lg shadow w-full max-w-2xl p-4 max-h-[92vh] overflow-y-auto">
            <h4 class="text-sm font-semibold text-gray-800 mb-3">{{ actionModal.title }}</h4>

            <div
                v-if="selectedClaim && ['project_verify', 'contra_verify', 'approve', 'prepare_payment'].includes(actionModal.type)"
                class="space-y-3"
            >
                <div
                    v-if="isReviewStep()"
                    class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3 text-xs text-gray-700"
                >
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div>
                            <span class="font-semibold text-gray-800">Claim No:</span>
                            {{ selectedClaim.claim_no || "-" }}
                        </div>
                        <div>
                            <span class="font-semibold text-gray-800">Sub Con:</span>
                            {{ claimSubConName(selectedClaim) }}
                        </div>
                        <div>
                            <span class="font-semibold text-gray-800">Title:</span>
                            {{ selectedClaim.title || "-" }}
                        </div>
                        <div>
                            <span class="font-semibold text-gray-800">Submitted:</span>
                            {{ formatDateTime(selectedClaim.submitted_at || selectedClaim.created_at) || "-" }}
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="font-semibold text-gray-800">Submission Remark:</span>
                        {{ claimSubmissionRemark(selectedClaim) }}
                    </div>
                        <div class="mt-2">
                            <a
                                :href="route('projects.sub-con-claims.proforma.download', { project: project.uuid, claim: selectedClaim.uuid })"
                                class="inline-flex items-center rounded border border-indigo-200 bg-white px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50"
                                target="_blank"
                                rel="noopener"
                            >
                                View Proforma Attachment
                            </a>
                            <template v-for="(proof, idx) in (selectedClaim.proof_attachments || [])" :key="`${selectedClaim.uuid}-proof-btn-${idx}`">
                                <a
                                    :href="`${route('projects.sub-con-claims.proof.download', { project: project.uuid, claim: selectedClaim.uuid })}?idx=${idx}`"
                                    class="ml-2 inline-flex items-center rounded border border-fuchsia-200 bg-white px-2.5 py-1 text-xs font-medium text-fuchsia-700 hover:bg-fuchsia-50"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    View Proof {{ idx + 1 }}
                                </a>
                            </template>
                        </div>
                    <div v-if="claimItems(selectedClaim).length" class="mt-3">
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-600">
                            Claim Item Details (From PO)
                        </div>
                        <div class="overflow-x-auto rounded-md border border-slate-200 bg-white">
                            <table class="min-w-full text-[11px]">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left font-semibold">Item</th>
                                        <th class="px-2 py-1.5 text-left font-semibold">Description</th>
                                        <th class="px-2 py-1.5 text-right font-semibold">Qty</th>
                                        <th class="px-2 py-1.5 text-right font-semibold">Unit Price</th>
                                        <th class="px-2 py-1.5 text-right font-semibold">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in claimItems(selectedClaim)"
                                        :key="item.id"
                                        class="border-t border-slate-100"
                                    >
                                        <td class="px-2 py-1.5 text-slate-800" :class="item.is_child ? 'pl-5' : ''">
                                            {{ item.is_child ? "-> " : "" }}{{ item.title || "-" }}
                                        </td>
                                        <td class="px-2 py-1.5 text-slate-600">{{ item.description || "-" }}</td>
                                        <td class="px-2 py-1.5 text-right text-slate-700">{{ Number(item.quantity || 0).toFixed(2) }}</td>
                                        <td class="px-2 py-1.5 text-right text-slate-700">{{ formatCurrency(item.unit_price || 0) }}</td>
                                        <td class="px-2 py-1.5 text-right font-semibold text-slate-800">{{ formatCurrency(item.total || 0) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div
                        v-if="shouldShowPreviousDecisionBlock() && previousDecisionHistory(selectedClaim).length"
                        class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2"
                    >
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">
                            Previous Decided Amounts
                        </div>
                        <div class="mt-1 space-y-1.5">
                            <div
                                v-for="(entry, idx) in previousDecisionHistory(selectedClaim)"
                                :key="`${entry.action}-${entry.at || idx}`"
                                class="rounded border border-amber-100 bg-white/80 px-2 py-1.5 text-[11px] text-gray-700"
                            >
                                <div class="font-medium text-gray-800">
                                    {{ previousDecisionLabel(entry.action) }}:
                                    {{ entry.amount !== null ? formatCurrency(entry.amount) : "-" }}
                                    <span v-if="entry.percent !== null" class="text-gray-600">
                                        ({{ entry.percent }}%)
                                    </span>
                                </div>
                                <div class="text-gray-600">
                                    by {{ entry.by }} at {{ formatDateTime(entry.at) || "-" }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        {{
                                actionModal.type === "prepare_payment"
                                ? "Payment Slip No (optional)"
                                : isPercentDrivenStep()
                                ? (actionModal.type === "approve"
                                    ? "Approved Amount (Auto from %)"
                                    : "Verified Amount (Auto from %)")
                                : "Amount"
                        }}
                    </label>
                    <input
                        v-model="actionModal.amount"
                        :type="actionModal.type === 'prepare_payment' ? 'text' : 'number'"
                        :min="actionModal.type === 'prepare_payment' ? undefined : 0"
                        :step="actionModal.type === 'prepare_payment' ? undefined : '0.01'"
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                        :readonly="isPercentDrivenStep()"
                    />
                </div>

                <div v-if="isPercentDrivenStep()" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Original Claimed Amount (Read Only)
                        </label>
                        <input
                            :value="formatCurrency(selectedClaim?.claimed_amount || 0)"
                            type="text"
                            readonly
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-gray-100 text-gray-700"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Current Base Amount (Read Only)
                        </label>
                        <input
                            :value="formatCurrency(actionBaseAmount())"
                            type="text"
                            readonly
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-gray-100 text-gray-700"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Verified Percentage (%)
                        </label>
                        <input
                            v-model="actionModal.percent"
                            type="number"
                            min="0"
                            :max="MAX_VERIFY_PERCENT"
                            step="0.01"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                            @input="syncVerifyAmountFromPercent"
                        />
                        <p class="mt-1 text-[11px] text-gray-500">Can exceed 100% when current verification is higher than previous stage.</p>
                    </div>
                    <div class="md:col-span-2">
                        <input
                            v-model="actionModal.percent"
                            type="range"
                            min="0"
                            :max="MAX_VERIFY_PERCENT"
                            step="1"
                            class="w-full"
                            @input="syncVerifyAmountFromPercent"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Work Done Note (optional)</label>
                        <input
                            v-model="actionModal.work_done_note"
                            type="text"
                            placeholder="e.g. Foundation 80% completed"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Site Reference / PIC (optional)</label>
                        <input
                            v-model="actionModal.site_reference"
                            type="text"
                            placeholder="e.g. Site A - Mr. Tan"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Deduction Reason (required if below 100%)
                        </label>
                        <textarea
                            v-model="actionModal.deduction_reason"
                            rows="2"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                            placeholder="State why full amount is not verified."
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Remark (optional)
                </label>
                <textarea
                    v-model="actionModal.remark"
                    rows="3"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                ></textarea>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200"
                    @click="closeActionModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="submitAction"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <div
        v-if="invoiceModal.open"
        class="fixed inset-0 z-50 bg-black/40 flex items-start justify-center p-4 md:py-8 overflow-y-auto"
    >
        <div class="bg-white rounded-lg shadow w-full max-w-md p-4 space-y-3 max-h-[92vh] overflow-y-auto">
            <h4 class="text-sm font-semibold text-gray-800">Upload Real Invoice</h4>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Invoice No</label>
                <input
                    v-model="invoiceModal.real_invoice_no"
                    type="text"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Invoice Date</label>
                <input
                    v-model="invoiceModal.real_invoice_date"
                    type="date"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Invoice Amount</label>
                <input
                    v-model="invoiceModal.real_invoice_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Invoice File</label>
                <input
                    type="file"
                    class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                    @change="(e) => (invoiceModal.real_invoice = e.target.files?.[0] || null)"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Remark (optional)</label>
                <textarea
                    v-model="invoiceModal.remark"
                    rows="3"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                ></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200"
                    @click="closeInvoiceModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="submitRealInvoice"
                >
                    Upload
                </button>
            </div>
        </div>
    </div>
</template>
