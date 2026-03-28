<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import POShowA4 from "@/Pages/Transactions/PurchaseOrder/Partials/POShowA4.vue";

const props = defineProps({
    subCon: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    poStats: {
        type: Object,
        default: () => ({}),
    },
    claimStats: {
        type: Object,
        default: () => ({}),
    },
    tasks: {
        type: Array,
        required: true,
    },
    purchaseOrders: {
        type: Array,
        default: () => [],
    },
    claims: {
        type: Array,
        default: () => [],
    },
});

const toast = inject("toast", null);

const showProgressModal = ref(false);
const showInvoiceModal = ref(false);
const showHistoryModal = ref(false);
const showPoConfirmModal = ref(false);
const showPoA4Modal = ref(false);
const isPoA4Loading = ref(false);
const selectedTask = ref(null);
const selectedPO = ref(null);
const selectedPoA4 = ref(null);
const poA4Company = ref(null);
const progressForm = ref({
    progress_percent: "",
    note: "",
    attachments: [],
    sub_task_ids: [],
});
const invoiceForm = ref({
    invoice_no: "",
    invoice_date: "",
    invoice_amount: "",
    invoice_remark: "",
    invoice_attachment: null,
});

const canSubmitStatuses = ["draft"];
const activeStage = ref("all");
const activePanel = ref("purchase_orders");
const taskQuery = ref("");
const poQuery = ref("");
const claimQuery = ref("");
const poConfirmForm = ref({
    order_date: "",
    signed_po: null,
});
const selectedTaskHasChildren = computed(() => (selectedTask.value?.children?.length ?? 0) > 0);
const activeClaimStage = ref("all");
const showClaimDecisionModal = ref(false);
const showClaimInvoiceModal = ref(false);
const selectedClaim = ref(null);
const claimDecisionForm = ref({
    decision: "accept",
    remark: "",
    appeal_proof_attachments: [],
});
const claimCreateForm = ref({
    po_uuid: "",
    title: "",
    claimed_amount: "",
    proforma_invoice: null,
    proof_attachments: [],
    remark: "",
});
const claimInvoiceForm = ref({
    real_invoice_no: "",
    real_invoice_date: "",
    real_invoice_amount: "",
    real_invoice: null,
    remark: "",
});
const scrollMemoryKey = "subcon-portal:scroll-y";

function saveScrollPosition() {
    if (typeof window === "undefined") return;
    window.sessionStorage.setItem(scrollMemoryKey, String(window.scrollY || 0));
}

function restoreScrollPosition() {
    if (typeof window === "undefined") return;
    const raw = window.sessionStorage.getItem(scrollMemoryKey);
    const y = Number(raw ?? 0);
    if (!Number.isFinite(y) || y <= 0) return;
    window.requestAnimationFrame(() => {
        window.scrollTo({ top: y, left: 0, behavior: "auto" });
    });
}

onMounted(() => {
    restoreScrollPosition();
    window.addEventListener("scroll", saveScrollPosition, { passive: true });
});

onBeforeUnmount(() => {
    saveScrollPosition();
    window.removeEventListener("scroll", saveScrollPosition);
});

const stageTabs = [
    { key: "all", label: "All" },
    { key: "draft", label: "In Progress" },
    { key: "submitted", label: "Submitted" },
    { key: "verified", label: "Verified" },
];

const stageHints = {
    all: "All main tasks across every stage.",
    draft: "Continue submitting progress until task completion.",
    submitted: "Task is complete and waiting Project Department review.",
    verified: "Project Department has verified completion.",
};

const claimStageTabs = [
    { key: "all", label: "All Claims" },
    { key: "ceo_gm_approved", label: "Pending Decision" },
    { key: "appealed", label: "Appealed" },
    { key: "accepted_by_subcon", label: "Accepted" },
    { key: "payment_in_progress", label: "Payment In Progress" },
    { key: "pending_real_invoice_upload", label: "Pending Real Invoice Upload" },
    { key: "real_invoice_uploaded", label: "Uploaded" },
];

function normalizedStatus(task) {
    const status = (task?.status || "").toLowerCase();
    if (["verified", "contra_verified", "invoiced", "approved", "justified", "certified", "paid"].includes(status)) return "verified";
    return status;
}

const stageCounts = computed(() => {
    const mainTasks = (props.tasks ?? []).filter((task) => !task.parent_id);
    const counts = { all: mainTasks.length };
    stageTabs.forEach((tab) => {
        if (tab.key !== "all") counts[tab.key] = 0;
    });

    mainTasks.forEach((task) => {
        const key = normalizedStatus(task);
        if (counts[key] === undefined) counts[key] = 0;
        counts[key] += 1;
    });

    return counts;
});

const filteredTasks = computed(() => {
    const mainTasks = (props.tasks ?? []).filter((task) => !task.parent_id);
    const stageFiltered =
        activeStage.value === "all"
            ? mainTasks
            : mainTasks.filter((task) => normalizedStatus(task) === activeStage.value);

    const q = taskQuery.value.trim().toLowerCase();
    if (!q) return stageFiltered;

    return stageFiltered.filter((task) => {
        const haystacks = [
            task?.title,
            task?.task_no,
            task?.project?.code,
            task?.project?.name,
        ]
            .filter(Boolean)
            .map((v) => String(v).toLowerCase());

        return haystacks.some((text) => text.includes(q));
    });
});

const groupedTasks = computed(() => {
    const groups = {};
    filteredTasks.value.forEach((task) => {
        const key = task.project?.uuid ?? "no-project";
        if (!groups[key]) {
            groups[key] = {
                project: task.project ?? { name: "Unknown Project", code: "-" },
                tasks: [],
            };
        }
        groups[key].tasks.push(task);
    });

    return Object.values(groups);
});

const activeStageHint = computed(() => stageHints[activeStage.value] ?? "");
const filteredClaims = computed(() => {
    const list = props.claims ?? [];
    const stageFiltered =
        activeClaimStage.value === "all"
            ? list
            : list.filter((claim) => (claim.status || "") === activeClaimStage.value);

    const q = claimQuery.value.trim().toLowerCase();
    if (!q) return stageFiltered;

    return stageFiltered.filter((claim) => {
        const haystacks = [
            claim?.claim_no,
            claim?.title,
            claim?.project?.code,
            claim?.project?.name,
        ]
            .filter(Boolean)
            .map((v) => String(v).toLowerCase());

        return haystacks.some((text) => text.includes(q));
    });
});

const claimStageCounts = computed(() => {
    const base = { all: (props.claims ?? []).length };
    claimStageTabs.forEach((tab) => {
        if (tab.key !== "all") base[tab.key] = 0;
    });

    (props.claims ?? []).forEach((claim) => {
        const key = claim?.status || "";
        if (base[key] === undefined) base[key] = 0;
        base[key] += 1;
    });

    return base;
});

const filteredPurchaseOrders = computed(() => {
    const list = props.purchaseOrders ?? [];
    const q = poQuery.value.trim().toLowerCase();
    if (!q) return list;

    return list.filter((po) => {
        const haystacks = [
            po?.code,
            po?.supplier?.company_name,
            po?.purchase_request?.code,
            po?.purchase_request?.title,
            po?.purchase_request?.project?.code,
            po?.purchase_request?.project?.name,
        ]
            .filter(Boolean)
            .map((v) => String(v).toLowerCase());

        return haystacks.some((text) => text.includes(q));
    });
});

const claimablePurchaseOrders = computed(() => {
    return (props.purchaseOrders ?? []).filter((po) => !!po?.uuid);
});

function logout() {
    router.post(route("sub-con.logout"));
}

function statusClass(status) {
    switch ((status || "").toLowerCase()) {
        case "submitted":
            return "bg-amber-100 text-amber-700";
        case "verified":
            return "bg-indigo-100 text-indigo-700";
        default:
            return "bg-gray-100 text-gray-700";
    }
}

function statusLabel(status) {
    const value = (status || "").toLowerCase();
    if (value === "draft") return "In Progress";
    if (["verified", "contra_verified", "invoiced", "approved", "justified", "certified", "paid"].includes(value)) return "Verified";
    return value
        .split("_")
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
}

function formatMoney(value) {
    const num = Number(value || 0);
    return new Intl.NumberFormat("en-MY", {
        style: "currency",
        currency: "MYR",
    }).format(num);
}

function formatDateTime(value) {
    if (!value) return "-";
    return new Date(value).toLocaleString();
}

function claimStatusLabel(status) {
    const map = {
        submitted: "Submitted",
        project_verified: "Project Verified",
        contra_verified: "Contra Verified",
        ceo_gm_approved: "Pending Your Decision",
        appealed: "Appealed",
        accepted_by_subcon: "Accepted",
        payment_in_progress: "Payment In Progress",
        pending_real_invoice_upload: "Pending Real Invoice Upload",
        real_invoice_uploaded: "Real Invoice Uploaded",
    };
    return map[status] || status || "-";
}

function claimStatusClass(status) {
    if (status === "ceo_gm_approved") return "bg-amber-100 text-amber-700";
    if (status === "appealed") return "bg-orange-100 text-orange-700";
    if (status === "accepted_by_subcon") return "bg-cyan-100 text-cyan-700";
    if (status === "payment_in_progress") return "bg-sky-100 text-sky-700";
    if (status === "pending_real_invoice_upload") return "bg-teal-100 text-teal-700";
    if (status === "real_invoice_uploaded") return "bg-emerald-100 text-emerald-700";
    return "bg-gray-100 text-gray-700";
}

function taskReference(task) {
    return task?.task_no || task?.uuid || "-";
}

function childCompletion(task) {
    const children = task?.children ?? [];
    if (!children.length) return null;

    const completed = children.filter((child) => (child?.status || "").toLowerCase() !== "draft").length;
    return `${completed}/${children.length}`;
}

function openProgress(task) {
    const lockedSubTaskIds = (task?.children ?? [])
        .filter((child) => (child?.status || "").toLowerCase() !== "draft")
        .map((child) => Number(child.id))
        .filter((id) => Number.isInteger(id) && id > 0);

    selectedTask.value = task;
    progressForm.value = {
        progress_percent: "",
        note: "",
        attachments: [],
        sub_task_ids: Array.from(new Set(lockedSubTaskIds)),
    };
    showProgressModal.value = true;
}

function openHistory(task) {
    selectedTask.value = task;
    showHistoryModal.value = true;
}

function openInvoice(task) {
    selectedTask.value = task;
    invoiceForm.value = {
        invoice_no: "",
        invoice_date: "",
        invoice_amount: task?.amount ?? "",
        invoice_remark: "",
        invoice_attachment: null,
    };
    showInvoiceModal.value = true;
}

function handleFileChange(event) {
    progressForm.value.attachments = Array.from(event.target.files ?? []);
}

function handleInvoiceFileChange(event) {
    invoiceForm.value.invoice_attachment = event.target.files?.[0] ?? null;
}

function clearInvoiceAttachment() {
    invoiceForm.value.invoice_attachment = null;
}

function submitProgress() {
    if (!selectedTask.value?.project?.uuid || !selectedTask.value?.uuid) {
        toast?.value?.show("Invalid task selection.", "error");
        return;
    }

    const fd = new FormData();
    if (!selectedTaskHasChildren.value) {
        fd.append("progress_percent", progressForm.value.progress_percent);
    }
    fd.append("note", progressForm.value.note || "");

    const lockedSubTaskIds = (selectedTask.value?.children ?? [])
        .filter((child) => (child?.status || "").toLowerCase() !== "draft")
        .map((child) => Number(child.id))
        .filter((id) => Number.isInteger(id) && id > 0);

    const selectedSubTaskIds = (progressForm.value.sub_task_ids ?? [])
        .map((id) => Number(id))
        .filter((id) => Number.isInteger(id) && id > 0);

    const mergedSubTaskIds = Array.from(new Set([...lockedSubTaskIds, ...selectedSubTaskIds]));

    progressForm.value.attachments.forEach((file) => fd.append("attachments[]", file));
    mergedSubTaskIds.forEach((id) => fd.append("sub_task_ids[]", id));

    router.post(
        route("sub-con.tasks.updates.store", { task: selectedTask.value.uuid }),
        fd,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Progress submitted.", "success");
                showProgressModal.value = false;
                selectedTask.value = null;
                router.reload({ only: ["tasks", "stats"], preserveScroll: true });
            },
            onError: (errors) => {
                const message = errors?.status || errors?.progress_percent || errors?.sub_task_ids || "Failed to submit progress.";
                toast?.value?.show(message, "error");
            },
        }
    );
}

function isLockedSubTask(subTask) {
    return (subTask?.status || "").toLowerCase() !== "draft";
}

function previousAttachmentUpdates() {
    return (selectedTask.value?.updates ?? []).filter((update) => update?.attachment_path);
}

function submitInvoice() {
    if (!selectedTask.value?.uuid) {
        toast?.value?.show("Invalid task selection.", "error");
        return;
    }

    const fd = new FormData();
    fd.append("invoice_no", invoiceForm.value.invoice_no || "");
    fd.append("invoice_date", invoiceForm.value.invoice_date || "");
    fd.append("invoice_amount", invoiceForm.value.invoice_amount || "");
    fd.append("invoice_remark", invoiceForm.value.invoice_remark || "");
    if (invoiceForm.value.invoice_attachment) {
        fd.append("invoice_attachment", invoiceForm.value.invoice_attachment);
    }

    router.post(route("sub-con.tasks.invoice.store", { task: selectedTask.value.uuid }), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Invoice submitted.", "success");
            showInvoiceModal.value = false;
            selectedTask.value = null;
            router.reload({ only: ["tasks", "stats"], preserveScroll: true });
        },
        onError: (errors) => {
            const message =
                errors?.status ||
                errors?.invoice_no ||
                errors?.invoice_date ||
                errors?.invoice_amount ||
                errors?.invoice_attachment ||
                "Failed to submit invoice.";
            toast?.value?.show(message, "error");
        },
    });
}

function openClaimDecision(claim, decision = "accept") {
    selectedClaim.value = claim;
    claimDecisionForm.value = {
        decision,
        remark: "",
        accept_proforma_invoice: null,
        appeal_proof_attachments: [],
    };
    showClaimDecisionModal.value = true;
}

function submitClaimDecision() {
    if (!selectedClaim.value?.uuid) return;
    if (claimDecisionForm.value.decision === "appeal" && !claimDecisionForm.value.remark.trim()) {
        toast?.value?.show("Appeal remark is required.", "error");
        return;
    }
    if (claimDecisionForm.value.decision === "appeal" && !claimDecisionForm.value.appeal_proof_attachments?.length) {
        toast?.value?.show("Appeal proof attachment is required.", "error");
        return;
    }
    if (claimDecisionForm.value.decision === "accept" && !claimDecisionForm.value.accept_proforma_invoice) {
        toast?.value?.show("New proforma invoice is required when accepting.", "error");
        return;
    }

    const fd = new FormData();
    fd.append("decision", claimDecisionForm.value.decision);
    fd.append("remark", claimDecisionForm.value.remark || "");
    if (claimDecisionForm.value.decision === "accept" && claimDecisionForm.value.accept_proforma_invoice) {
        fd.append("accept_proforma_invoice", claimDecisionForm.value.accept_proforma_invoice);
    }
    if (claimDecisionForm.value.decision === "appeal") {
        claimDecisionForm.value.appeal_proof_attachments.forEach((file) => {
            fd.append("appeal_proof_attachments[]", file);
        });
    }

    router.post(
        route("sub-con.claims.decision", { claim: selectedClaim.value.uuid }),
        fd,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show(
                    claimDecisionForm.value.decision === "accept"
                        ? "Accepted successfully."
                        : "Appeal submitted.",
                    "success"
                );
                showClaimDecisionModal.value = false;
                selectedClaim.value = null;
                router.reload({ only: ["claims", "claimStats"], preserveScroll: true });
            },
            onError: (errors) => {
                const message = errors?.status || errors?.remark || errors?.accept_proforma_invoice || errors?.appeal_proof_attachments || errors?.["appeal_proof_attachments.0"] || "Failed to submit decision.";
                toast?.value?.show(message, "error");
            },
        }
    );
}

function handleAppealProofFile(event) {
    claimDecisionForm.value.appeal_proof_attachments = Array.from(event.target.files ?? []);
}

function handleAcceptProformaFile(event) {
    claimDecisionForm.value.accept_proforma_invoice = event.target.files?.[0] ?? null;
}

function openClaimInvoiceUpload(claim) {
    selectedClaim.value = claim;
    claimInvoiceForm.value = {
        real_invoice_no: "",
        real_invoice_date: "",
        real_invoice_amount: String(claim?.approved_amount ?? claim?.claimed_amount ?? 0),
        real_invoice: null,
        remark: "",
    };
    showClaimInvoiceModal.value = true;
}

function handleClaimInvoiceFile(event) {
    claimInvoiceForm.value.real_invoice = event.target.files?.[0] ?? null;
}

function submitClaimInvoiceUpload() {
    if (!selectedClaim.value?.uuid) return;
    if (!claimInvoiceForm.value.real_invoice) {
        toast?.value?.show("Please upload real invoice file.", "error");
        return;
    }

    const fd = new FormData();
    fd.append("real_invoice_no", claimInvoiceForm.value.real_invoice_no || "");
    fd.append("real_invoice_date", claimInvoiceForm.value.real_invoice_date || "");
    fd.append("real_invoice_amount", claimInvoiceForm.value.real_invoice_amount || "");
    fd.append("real_invoice", claimInvoiceForm.value.real_invoice);
    fd.append("remark", claimInvoiceForm.value.remark || "");

    router.post(
        route("sub-con.claims.real-invoice.upload", { claim: selectedClaim.value.uuid }),
        fd,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Real invoice uploaded.", "success");
                showClaimInvoiceModal.value = false;
                selectedClaim.value = null;
                router.reload({ only: ["claims", "claimStats"], preserveScroll: true });
            },
            onError: (errors) => {
                const message =
                    errors?.status ||
                    errors?.real_invoice_no ||
                    errors?.real_invoice_date ||
                    errors?.real_invoice_amount ||
                    errors?.real_invoice ||
                    "Failed to upload real invoice.";
                toast?.value?.show(message, "error");
            },
        }
    );
}

function handleClaimProformaFile(event) {
    claimCreateForm.value.proforma_invoice = event.target.files?.[0] ?? null;
}

function submitClaimFromPortal() {
    if (!claimCreateForm.value.proforma_invoice) {
        toast?.value?.show("Please upload proforma invoice.", "error");
        return;
    }
    if (!claimCreateForm.value.proof_attachments?.length) {
        toast?.value?.show("Please upload at least one claim proof attachment.", "error");
        return;
    }

    const fd = new FormData();
    fd.append("po_uuid", claimCreateForm.value.po_uuid || "");
    fd.append("title", claimCreateForm.value.title || "");
    fd.append("claimed_amount", claimCreateForm.value.claimed_amount || "");
    fd.append("proforma_invoice", claimCreateForm.value.proforma_invoice);
    claimCreateForm.value.proof_attachments.forEach((file) => {
        fd.append("proof_attachments[]", file);
    });
    fd.append("remark", claimCreateForm.value.remark || "");

    router.post(route("sub-con.claims.store"), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Claim submitted successfully.", "success");
            claimCreateForm.value = {
                po_uuid: "",
                title: "",
                claimed_amount: "",
                proforma_invoice: null,
                proof_attachments: [],
                remark: "",
            };
            router.reload({ only: ["claims", "claimStats"], preserveScroll: true });
        },
        onError: (errors) => {
            const message =
                errors?.po_uuid ||
                errors?.title ||
                errors?.claimed_amount ||
                errors?.proforma_invoice ||
                errors?.proof_attachments ||
                errors?.["proof_attachments.0"] ||
                "Failed to submit claim.";
            toast?.value?.show(message, "error");
        },
    });
}

function handleClaimProofFile(event) {
    claimCreateForm.value.proof_attachments = Array.from(event.target.files ?? []);
}

function openPoConfirmModal(po) {
    selectedPO.value = po;
    poConfirmForm.value = {
        order_date: po?.order_date || "",
        signed_po: null,
    };
    showPoConfirmModal.value = true;
}

async function openPoA4Modal(po) {
    if (!po?.uuid) return;

    showPoA4Modal.value = true;
    isPoA4Loading.value = true;
    selectedPoA4.value = null;
    poA4Company.value = null;

    try {
        const response = await axios.get(route("sub-con.purchase-orders.show", { po: po.uuid }));
        selectedPoA4.value = response?.data?.po ?? null;
        poA4Company.value = response?.data?.company ?? null;
    } catch (error) {
        showPoA4Modal.value = false;
        const message = error?.response?.data?.message || "Failed to load PO preview.";
        toast?.value?.show(message, "error");
    } finally {
        isPoA4Loading.value = false;
    }
}

function closePoA4Modal() {
    showPoA4Modal.value = false;
    selectedPoA4.value = null;
    poA4Company.value = null;
}

function printPoA4() {
    if (typeof window === "undefined") return;
    window.print();
}

function closePoConfirmModal() {
    showPoConfirmModal.value = false;
    selectedPO.value = null;
    poConfirmForm.value = {
        order_date: "",
        signed_po: null,
    };
}

function handlePoConfirmFile(event) {
    poConfirmForm.value.signed_po = event?.target?.files?.[0] ?? null;
}

function clearPoConfirmFile() {
    poConfirmForm.value.signed_po = null;
}

function confirmPurchaseOrder() {
    if (!selectedPO.value?.uuid) return;

    if (!poConfirmForm.value.order_date) {
        toast?.value?.show("Order date is required before confirmation.", "error");
        return;
    }

    const fd = new FormData();
    fd.append("order_date", poConfirmForm.value.order_date);
    if (poConfirmForm.value.signed_po) {
        fd.append("signed_po", poConfirmForm.value.signed_po);
    }

    router.post(route("sub-con.purchase-orders.confirm", { po: selectedPO.value.uuid }), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("PO confirmed successfully.", "success");
            closePoConfirmModal();
            router.reload({ only: ["purchaseOrders", "poStats"], preserveScroll: true });
        },
        onError: (errors) => {
            const message = errors?.status || errors?.order_date || errors?.signed_po || "Failed to confirm PO.";
            toast?.value?.show(message, "error");
        },
    });
}
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="bg-gradient-to-r from-indigo-600 to-violet-600 border-b border-indigo-700">
            <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-indigo-100">Sub Con Portal</div>
                    <h1 class="text-xl font-semibold text-white">
                        {{ subCon?.name }}<span v-if="subCon?.company_name"> - {{ subCon.company_name }}</span>
                    </h1>
                    <div class="text-xs text-indigo-100 mt-0.5">
                        Track task progress and respond to claim decisions in one place.
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-sm text-indigo-100">{{ subCon?.login_identity_no }}</div>
                    <button
                        class="px-3 py-1.5 text-xs bg-white/10 text-white rounded border border-white/20 hover:bg-white/20"
                        @click="logout"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Total</div>
                    <div class="mt-1 text-xl font-semibold text-gray-900">{{ stats.total }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">In Progress</div>
                    <div class="mt-1 text-xl font-semibold text-slate-800">{{ stats.draft }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Submitted</div>
                    <div class="mt-1 text-xl font-semibold text-amber-700">{{ stats.submitted }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Verified</div>
                    <div class="mt-1 text-xl font-semibold text-indigo-700">{{ stats.verified }}</div>
                </div>
            </div>

            <div class="bg-white border rounded-lg p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs rounded-full border font-semibold"
                        :class="activePanel === 'purchase_orders' ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-indigo-300'"
                        @click="activePanel = 'purchase_orders'"
                    >
                        PO Confirmation
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs rounded-full border font-semibold"
                        :class="activePanel === 'tasks' ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-indigo-300'"
                        @click="activePanel = 'tasks'"
                    >
                        Task Workspace
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs rounded-full border font-semibold"
                        :class="activePanel === 'claims' ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-indigo-300'"
                        @click="activePanel = 'claims'"
                    >
                        Claims Workspace
                    </button>
                </div>
            </div>

            <div v-if="activePanel === 'tasks'" class="space-y-6">
            <div class="bg-white border rounded-lg px-4 py-3">
                <div class="flex flex-wrap gap-2 items-center">
                    <button
                        v-for="tab in stageTabs"
                        :key="tab.key"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                        :class="
                            activeStage === tab.key
                                ? 'border-indigo-300 bg-indigo-50 text-indigo-700'
                                : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-100'
                        "
                        @click="activeStage = tab.key"
                    >
                        <span>{{ tab.label }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-700">
                            {{ stageCounts[tab.key] ?? 0 }}
                        </span>
                    </button>
                    <div class="ml-auto w-full md:w-64">
                        <input
                            v-model="taskQuery"
                            type="text"
                            placeholder="Search task / project / ref"
                            class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-xs"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    {{ activeStageHint }}
                </p>
            </div>

            <div
                v-for="group in groupedTasks"
                :key="group.project?.uuid ?? group.project?.name"
                class="bg-white border rounded-lg overflow-hidden"
            >
                <div class="px-4 py-3 border-b bg-gray-50">
                    <div class="text-sm text-gray-500">Project</div>
                    <div class="font-semibold text-gray-800">
                        {{ group.project?.code || "-" }} - {{ group.project?.name || "Unknown Project" }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ group.tasks.length }} main task(s)
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Task</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Task Ref</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Progress</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-center text-xs uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="task in group.tasks" :key="task.uuid">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-800">{{ task.title }}</div>
                                    <div v-if="task.children?.length" class="text-xs text-gray-500 mt-0.5">
                                        Sub Task: {{ childCompletion(task) }} completed
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs font-medium text-indigo-700">{{ taskReference(task) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatMoney(task.amount) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 min-w-[170px]">
                                    <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                        <span>Completion</span>
                                        <span class="font-semibold">{{ task.progress_percent ?? 0 }}%</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500"
                                            :style="{ width: `${Math.max(0, Math.min(100, Number(task.progress_percent ?? 0)))}%` }"
                                        ></div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex px-2 py-1 rounded-full text-xs font-semibold"
                                        :class="statusClass(task.status)"
                                    >
                                        {{ statusLabel(task.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-center gap-2">
                                        <button
                                            v-if="canSubmitStatuses.includes(task.status)"
                                            class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700"
                                            @click="openProgress(task)"
                                        >
                                            Submit Progress
                                        </button>
                                        <button
                                            class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200"
                                            @click="openHistory(task)"
                                        >
                                            History
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="groupedTasks.length === 0"
                class="bg-white border rounded-lg p-8 text-center"
            >
                <div class="text-sm font-semibold text-gray-700">No tasks for this stage</div>
                <div class="mt-1 text-xs text-gray-500">Try switching tabs to view other task statuses.</div>
            </div>
            </div>

            <div v-if="activePanel === 'claims'" class="space-y-6">
            <div class="bg-white border rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b bg-gradient-to-r from-indigo-50 via-violet-50 to-white">
                    <div class="text-sm font-semibold text-gray-900">Submit New Claim</div>
                    <div class="text-xs text-gray-600 mt-0.5">
                        Submit proforma invoice from portal. Appeal and real invoice upload are also handled here.
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Purchase Order</label>
                            <select
                                v-model="claimCreateForm.po_uuid"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                            >
                                <option value="">Select PO</option>
                                <option v-for="po in claimablePurchaseOrders" :key="po.uuid" :value="po.uuid">
                                    {{ po.code || "-" }} | {{ po.purchase_request?.project?.code || "-" }} - {{ po.purchase_request?.project?.name || "No project" }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Claim Title</label>
                            <input
                                v-model="claimCreateForm.title"
                                type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                                placeholder="Work done / claim purpose"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Claimed Amount (RM)</label>
                            <input
                                v-model="claimCreateForm.claimed_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                                placeholder="0.00"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-end">
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Proforma Invoice</label>
                            <label class="block rounded-lg border-2 border-dashed border-indigo-200 bg-indigo-50/40 px-4 py-4 cursor-pointer hover:bg-indigo-50 transition">
                                <input
                                    type="file"
                                    class="hidden"
                                    @change="handleClaimProformaFile"
                                />
                                <div class="text-sm font-medium text-indigo-700">
                                    {{ claimCreateForm.proforma_invoice ? claimCreateForm.proforma_invoice.name : "Click to upload proforma invoice" }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    PDF / JPG / PNG, maximum 10MB
                                </div>
                            </label>
                        </div>

                        <div>
                            <button
                                type="button"
                                class="w-full px-4 py-2.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-medium shadow-sm"
                                @click="submitClaimFromPortal"
                            >
                                Submit Claim
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-end">
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Proof Attachment</label>
                            <label class="block rounded-lg border-2 border-dashed border-indigo-200 bg-indigo-50/40 px-4 py-4 cursor-pointer hover:bg-indigo-50 transition">
                                <input
                                    type="file"
                                    class="hidden"
                                    multiple
                                    @change="handleClaimProofFile"
                                />
                                <div class="text-sm font-medium text-indigo-700">
                                    {{ claimCreateForm.proof_attachments?.length ? `${claimCreateForm.proof_attachments.length} file(s) selected` : "Click to upload proof attachment(s)" }}
                                </div>
                                <div v-if="claimCreateForm.proof_attachments?.length" class="mt-1 text-xs text-gray-600">
                                    {{ claimCreateForm.proof_attachments.map((file) => file.name).join(", ") }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    PDF / JPG / PNG, maximum 10MB each (at least 1 required)
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Remark (optional)</label>
                        <textarea
                            v-model="claimCreateForm.remark"
                            rows="2"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                            placeholder="Any context for reviewer"
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Claims Total</div>
                    <div class="mt-1 text-xl font-semibold text-gray-900">{{ claimStats.total ?? 0 }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Pending Decision</div>
                    <div class="mt-1 text-xl font-semibold text-amber-700">{{ claimStats.pending_decision ?? 0 }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Appealed</div>
                    <div class="mt-1 text-xl font-semibold text-orange-700">{{ claimStats.appealed ?? 0 }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Payment In Progress</div>
                    <div class="mt-1 text-xl font-semibold text-sky-700">{{ claimStats.payment_in_progress ?? 0 }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Pending Upload</div>
                    <div class="mt-1 text-xl font-semibold text-teal-700">{{ claimStats.pending_real_invoice_upload ?? 0 }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Uploaded</div>
                    <div class="mt-1 text-xl font-semibold text-emerald-700">{{ claimStats.completed ?? 0 }}</div>
                </div>
            </div>

            <div class="bg-white border rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <div class="text-sm font-semibold text-gray-800">Sub Con Claims</div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        Accept or appeal CEO/GM approved claims here. Real invoice upload is enabled after internal team completes payment.
                    </div>
                </div>

                <div class="px-4 py-3 border-b">
                    <div class="flex flex-wrap gap-2 items-center">
                        <button
                            v-for="tab in claimStageTabs"
                            :key="tab.key"
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                            :class="
                                activeClaimStage === tab.key
                                    ? 'border-indigo-300 bg-indigo-50 text-indigo-700'
                                    : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-100'
                            "
                            @click="activeClaimStage = tab.key"
                        >
                            <span>{{ tab.label }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-700">
                                {{ claimStageCounts[tab.key] ?? 0 }}
                            </span>
                        </button>
                        <div class="ml-auto w-full md:w-72">
                            <input
                                v-model="claimQuery"
                                type="text"
                                placeholder="Search claim / project / claim no"
                                class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-xs"
                            />
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Claim</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Project</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Files</th>
                                <th class="px-4 py-3 text-center text-xs uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="claim in filteredClaims" :key="claim.uuid">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-800">{{ claim.claim_no || "-" }}</div>
                                    <div class="text-xs text-gray-500">PO: {{ claim.purchase_order?.code || "-" }}</div>
                                    <div class="text-gray-700">{{ claim.title }}</div>
                                    <div v-if="(claim.appeal_round ?? 0) > 0" class="text-xs text-orange-600 mt-0.5">
                                        Appeal round: {{ claim.appeal_round }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-800">{{ claim.project?.code || "-" }}</div>
                                    <div class="text-xs text-gray-600">{{ claim.project?.name || "-" }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div>Claimed: {{ formatMoney(claim.claimed_amount || 0) }}</div>
                                    <div>Approved: {{ formatMoney(claim.approved_amount || 0) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold" :class="claimStatusClass(claim.status)">
                                        {{ claimStatusLabel(claim.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div class="flex flex-col gap-1">
                                        <a
                                            :href="route('sub-con.claims.proforma.download', { claim: claim.uuid })"
                                            class="text-indigo-600 hover:text-indigo-800"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Proforma
                                        </a>
                                        <a
                                            v-for="(proof, idx) in (claim.proof_attachments || [])"
                                            :key="`${claim.uuid}-proof-${idx}`"
                                            :href="`${route('sub-con.claims.proof.download', { claim: claim.uuid })}?idx=${idx}`"
                                            class="text-fuchsia-600 hover:text-fuchsia-800"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Proof {{ idx + 1 }}
                                        </a>
                                        <a
                                            v-if="claim.real_invoice_name"
                                            :href="route('sub-con.claims.real-invoice.download', { claim: claim.uuid })"
                                            class="text-emerald-600 hover:text-emerald-800"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Real Invoice
                                        </a>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-center gap-2">
                                        <button
                                            v-if="claim.status === 'ceo_gm_approved'"
                                            class="px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                            @click="openClaimDecision(claim, 'accept')"
                                        >
                                            Accept
                                        </button>
                                        <button
                                            v-if="claim.status === 'ceo_gm_approved'"
                                            class="px-2 py-1 text-xs rounded bg-amber-500 text-white hover:bg-amber-600"
                                            @click="openClaimDecision(claim, 'appeal')"
                                        >
                                            Appeal
                                        </button>
                                        <button
                                            v-if="claim.status === 'pending_real_invoice_upload'"
                                            class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700"
                                            @click="openClaimInvoiceUpload(claim)"
                                        >
                                            Upload Real Invoice
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredClaims.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                    No claims in this stage.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>

            <div v-if="activePanel === 'purchase_orders'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">PO Total</div>
                    <div class="mt-1 text-xl font-semibold text-gray-900">{{ poStats.total ?? 0 }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Pending Confirmation</div>
                    <div class="mt-1 text-xl font-semibold text-amber-700">{{ poStats.pending_confirmation ?? 0 }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Confirmed</div>
                    <div class="mt-1 text-xl font-semibold text-emerald-700">{{ poStats.confirmed ?? 0 }}</div>
                </div>
            </div>

            <div class="bg-white border rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <div class="text-sm font-semibold text-gray-800">Purchase Orders</div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        Confirm PO with order date and signed copy.
                    </div>
                </div>

                <div class="px-4 py-3 border-b">
                    <div class="w-full md:w-80">
                        <input
                            v-model="poQuery"
                            type="text"
                            placeholder="Search PO / supplier / PR / project"
                            class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-xs"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">PO</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Supplier / Project</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Signed PO</th>
                                <th class="px-4 py-3 text-center text-xs uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="po in filteredPurchaseOrders" :key="po.uuid">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-800">{{ po.code || "-" }}</div>
                                    <div class="text-xs text-gray-500">Date: {{ formatDateTime(po.order_date) }}</div>
                                    <div class="text-xs text-gray-500">PR: {{ po.purchase_request?.code || "-" }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <div class="font-medium text-gray-800">{{ po.supplier?.company_name || "-" }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ po.purchase_request?.project?.code || "-" }} - {{ po.purchase_request?.project?.name || "No project" }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatMoney(po.total_amount) }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex px-2 py-1 rounded-full text-xs font-semibold"
                                        :class="po.status === 'confirmed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                    >
                                        {{ po.status === 'confirmed' ? 'Confirmed' : 'Pending Confirmation' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <a
                                        v-if="po.signed_po?.url"
                                        :href="po.signed_po.url"
                                        class="text-indigo-600 hover:text-indigo-800"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ po.signed_po.name || "View signed PO" }}
                                    </a>
                                    <span v-else class="text-xs text-gray-400">Not uploaded</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-100"
                                            @click="openPoA4Modal(po)"
                                        >
                                            View A4
                                        </button>

                                        <button
                                            v-if="po.status !== 'confirmed'"
                                            type="button"
                                            class="rounded bg-indigo-600 px-2 py-1 text-xs text-white hover:bg-indigo-700"
                                            @click="openPoConfirmModal(po)"
                                        >
                                            Confirm PO
                                        </button>
                                    </div>
                                    <div v-if="po.status === 'confirmed'" class="mt-1 text-xs text-emerald-700 text-center font-semibold">Confirmed</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="filteredPurchaseOrders.length === 0"
                class="bg-white border rounded-lg p-8 text-center"
            >
                <div class="text-sm font-semibold text-gray-700">No purchase orders found</div>
                <div class="mt-1 text-xs text-gray-500">Try another keyword or wait for new issued PO.</div>
            </div>
            </div>
        </main>
    </div>

    <div v-if="showPoA4Modal" class="fixed inset-0 z-50 bg-black/60 p-4 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-[1200px] h-[92vh] overflow-hidden flex flex-col">
            <div class="px-6 py-3 border-b bg-gray-50 flex items-center gap-3">
                <div class="text-sm font-semibold text-gray-800">
                    PO A4 Preview: {{ selectedPoA4?.code || "-" }}
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                        @click="printPoA4"
                    >
                        Print
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-md hover:bg-gray-100"
                        @click="closePoA4Modal"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-auto bg-gray-100 p-4">
                <div v-if="isPoA4Loading" class="h-full flex items-center justify-center text-sm text-gray-500">
                    Loading PO preview...
                </div>
                <div v-else-if="selectedPoA4 && poA4Company" class="flex justify-center">
                    <POShowA4 :po="selectedPoA4" :company="poA4Company" />
                </div>
                <div v-else class="h-full flex items-center justify-center text-sm text-red-600">
                    Failed to load PO preview.
                </div>
            </div>
        </div>
    </div>

    <div v-if="showPoConfirmModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center px-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-indigo-50 to-violet-50 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Confirm Purchase Order</h3>
                    <p class="text-xs text-gray-600 mt-0.5">Complete confirmation details before submitting.</p>
                </div>
                <button
                    class="h-7 w-7 rounded-full border border-gray-200 text-gray-500 hover:text-gray-700 hover:bg-white"
                    @click="closePoConfirmModal"
                >
                    x
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-3">
                    <div class="text-xs uppercase tracking-wide text-indigo-700 font-semibold">PO Reference</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">
                        {{ selectedPO?.code || "-" }}
                    </div>
                    <div class="text-xs text-gray-600 mt-1">
                        {{ selectedPO?.supplier?.company_name || "-" }}
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Order Date</label>
                    <input
                        v-model="poConfirmForm.order_date"
                        type="date"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Signed PO (optional if already uploaded)</label>
                    <label class="block rounded-lg border-2 border-dashed border-indigo-200 bg-indigo-50/40 px-4 py-4 cursor-pointer hover:bg-indigo-50 transition">
                        <input type="file" class="hidden" @change="handlePoConfirmFile" />
                        <div class="text-sm font-medium text-indigo-700">
                            {{ poConfirmForm.signed_po ? poConfirmForm.signed_po.name : "Click to upload signed PO" }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">PDF / JPG / PNG, maximum 10MB</div>
                    </label>
                    <button
                        v-if="poConfirmForm.signed_po"
                        type="button"
                        class="mt-2 text-xs text-red-600 hover:text-red-700"
                        @click="clearPoConfirmFile"
                    >
                        Remove selected file
                    </button>
                </div>
            </div>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-2.5">
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-md hover:bg-gray-100"
                    @click="closePoConfirmModal"
                >
                    Cancel
                </button>
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                    @click="confirmPurchaseOrder"
                >
                    Confirm PO
                </button>
            </div>
        </div>
    </div>

    <div v-if="showProgressModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center px-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Submit Progress</h3>
                <button class="text-gray-400 hover:text-gray-600" @click="showProgressModal = false">x</button>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div class="text-sm text-gray-500">
                    {{ selectedTask?.title }} ({{ selectedTask?.project?.name }})
                </div>

                <div v-if="!selectedTaskHasChildren">
                    <label class="block text-sm text-gray-600">Progress (%)</label>
                    <input
                        v-model="progressForm.progress_percent"
                        type="number"
                        min="0"
                        max="100"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    />
                </div>
                <div v-else class="rounded-md border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs text-indigo-700">
                    Progress is auto-calculated from checked sub tasks.
                </div>

                <div>
                    <label class="block text-sm text-gray-600">Note (optional)</label>
                    <textarea
                        v-model="progressForm.note"
                        rows="3"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    />
                </div>

                <div v-if="selectedTask?.children?.length">
                    <label class="block text-sm text-gray-600">Sub Task Checklist</label>
                    <div class="mt-2 space-y-2 rounded-lg border bg-gray-50 p-3 max-h-44 overflow-auto">
                        <label
                            v-for="subTask in selectedTask.children"
                            :key="subTask.uuid"
                            class="flex items-center justify-between gap-3 rounded bg-white px-3 py-2 border"
                            :class="isLockedSubTask(subTask) ? 'opacity-70' : ''"
                        >
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="progressForm.sub_task_ids"
                                    :value="subTask.id"
                                    type="checkbox"
                                    :disabled="isLockedSubTask(subTask)"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-sm text-gray-700">{{ subTask.title }}</span>
                            </div>
                            <span v-if="isLockedSubTask(subTask)" class="text-xs font-medium text-emerald-600">Completed</span>
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Previously checked sub tasks are auto-checked and locked.
                    </p>
                </div>

                <div>
                    <div v-if="previousAttachmentUpdates().length" class="mb-3">
                        <label class="block text-sm text-gray-600">Previous Attachments</label>
                        <div class="mt-2 space-y-2 rounded-lg border bg-gray-50 p-3 max-h-40 overflow-auto">
                            <a
                                v-for="update in previousAttachmentUpdates()"
                                :key="update.uuid"
                                :href="route('sub-con.tasks.updates.download', { task: selectedTask.uuid, update: update.uuid })"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center justify-between rounded bg-white px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50 border"
                            >
                                <span class="truncate max-w-[220px]">{{ update.attachment_name || "Attachment" }}</span>
                                <span class="text-gray-500">{{ formatDateTime(update.created_at) }}</span>
                            </a>
                        </div>
                    </div>

                    <label class="block text-sm text-gray-600">Attachments (optional)</label>
                    <label
                        class="mt-1 block cursor-pointer rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-center hover:border-indigo-300 hover:bg-indigo-50/40"
                    >
                        <input
                            type="file"
                            multiple
                            class="hidden"
                            @change="handleFileChange"
                        />
                        <div class="text-sm font-medium text-gray-700">Click to upload files</div>
                        <div class="text-xs text-gray-500 mt-1">PDF / JPG / PNG · up to 10 files · 10MB each</div>
                    </label>
                    <div v-if="progressForm.attachments.length" class="mt-2 space-y-1">
                        <div
                            v-for="(file, idx) in progressForm.attachments"
                            :key="`${file.name}-${idx}`"
                            class="flex items-center justify-between rounded bg-gray-50 px-3 py-1 text-xs text-gray-700"
                        >
                            <span class="truncate max-w-[240px]">{{ file.name }}</span>
                            <span class="text-gray-400">{{ (file.size / 1024 / 1024).toFixed(2) }} MB</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3">
                <button class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300" @click="showProgressModal = false">
                    Cancel
                </button>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700" @click="submitProgress">
                    Submit
                </button>
            </div>
        </div>
    </div>

    <div v-if="showHistoryModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center px-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Progress History</h3>
                <button class="text-gray-400 hover:text-gray-600" @click="showHistoryModal = false">x</button>
            </div>

            <div class="px-6 py-4">
                <div v-if="!selectedTask?.updates?.length" class="text-sm text-gray-500">
                    No updates yet.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="update in selectedTask.updates"
                        :key="update.uuid"
                        class="border rounded-lg p-3"
                    >
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold text-gray-800">{{ update.progress_percent }}%</div>
                            <div class="text-xs text-gray-500">{{ formatDateTime(update.created_at) }}</div>
                        </div>
                        <div class="text-sm text-gray-700 mt-1">
                            {{ update.note || "No note" }}
                        </div>
                        <div v-if="update.attachment_path" class="mt-2">
                            <a
                                :href="route('sub-con.tasks.updates.download', {
                                    task: selectedTask.uuid,
                                    update: update.uuid,
                                })"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                                target="_blank"
                                rel="noopener"
                            >
                                Download attachment
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="showInvoiceModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center px-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl overflow-hidden">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-violet-50 to-indigo-50 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Submit Invoice</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Complete all required fields before submit.</p>
                </div>
                <button class="text-gray-400 hover:text-gray-600" @click="showInvoiceModal = false">x</button>
            </div>

            <div class="px-6 py-5 space-y-5">
                <div class="rounded-lg border border-violet-100 bg-violet-50/50 p-4">
                    <div class="text-xs uppercase tracking-wide text-violet-700 font-semibold">Task Reference</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">
                        {{ selectedTask?.title || "-" }}
                    </div>
                    <div class="text-xs text-gray-600 mt-1">
                        {{ selectedTask?.project?.name || "-" }}
                    </div>
                    <div class="text-xs text-gray-600 mt-2">
                        Task Amount:
                        <span class="font-medium text-gray-800">{{ formatMoney(selectedTask?.amount ?? 0) }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Invoice No</label>
                        <input
                            v-model="invoiceForm.invoice_no"
                            type="text"
                            placeholder="e.g. INV-2026-001"
                            class="mt-1 w-full border rounded-md px-3 py-2 focus:ring-2 focus:ring-violet-200 focus:border-violet-400"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Invoice Date</label>
                        <input
                            v-model="invoiceForm.invoice_date"
                            type="date"
                            class="mt-1 w-full border rounded-md px-3 py-2 focus:ring-2 focus:ring-violet-200 focus:border-violet-400"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Invoice Amount</label>
                        <input
                            v-model="invoiceForm.invoice_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 w-full border rounded-md px-3 py-2 focus:ring-2 focus:ring-violet-200 focus:border-violet-400"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Preview: {{ formatMoney(invoiceForm.invoice_amount || 0) }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Remark (optional)</label>
                        <textarea
                            v-model="invoiceForm.invoice_remark"
                            rows="2"
                            placeholder="Add note for Project Department / CEO / GM"
                            class="mt-1 w-full border rounded-md px-3 py-2 focus:ring-2 focus:ring-violet-200 focus:border-violet-400"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Invoice Attachment</label>
                    <label
                        class="mt-1 block cursor-pointer rounded-lg border-2 border-dashed border-violet-200 bg-violet-50/40 px-4 py-6 text-center hover:bg-violet-50"
                    >
                        <input
                            type="file"
                            class="hidden"
                            @change="handleInvoiceFileChange"
                        />
                        <div class="text-sm font-medium text-violet-700">Click to upload invoice file</div>
                        <div class="text-xs text-gray-500 mt-1">PDF / JPG / PNG, max 10MB</div>
                    </label>
                    <div v-if="invoiceForm.invoice_attachment" class="mt-2 rounded-md border bg-gray-50 px-3 py-2 flex items-center justify-between">
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-gray-800 truncate">
                                {{ invoiceForm.invoice_attachment.name }}
                            </div>
                            <div class="text-[11px] text-gray-500">
                                {{ (invoiceForm.invoice_attachment.size / 1024 / 1024).toFixed(2) }} MB
                            </div>
                        </div>
                        <button
                            type="button"
                            class="ml-3 text-xs text-red-600 hover:text-red-700"
                            @click="clearInvoiceAttachment"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3">
                <button class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300" @click="showInvoiceModal = false">
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-violet-600 text-white rounded hover:bg-violet-700"
                    :disabled="!invoiceForm.invoice_no || !invoiceForm.invoice_date || !invoiceForm.invoice_amount || !invoiceForm.invoice_attachment"
                    :class="{ 'opacity-60 cursor-not-allowed': !invoiceForm.invoice_no || !invoiceForm.invoice_date || !invoiceForm.invoice_amount || !invoiceForm.invoice_attachment }"
                    @click="submitInvoice"
                >
                    Submit Invoice
                </button>
            </div>
        </div>
    </div>

    <div v-if="showClaimDecisionModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center px-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-emerald-50 to-teal-50 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ claimDecisionForm.decision === "accept" ? "Accept Claim Decision" : "Appeal Claim Decision" }}
                    </h3>
                    <p class="text-xs text-gray-600 mt-0.5">Review summary before confirming your decision.</p>
                </div>
                <button
                    class="h-7 w-7 rounded-full border border-gray-200 text-gray-500 hover:text-gray-700 hover:bg-white"
                    @click="showClaimDecisionModal = false"
                >
                    x
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Claim Summary</div>
                    <div class="mt-1 text-sm font-semibold text-gray-800">
                        {{ selectedClaim?.claim_no || "-" }} - {{ selectedClaim?.title || "-" }}
                    </div>
                    <div class="mt-1 text-sm text-gray-700">
                        Approved Amount: <span class="font-semibold text-gray-900">{{ formatMoney(selectedClaim?.approved_amount || 0) }}</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">
                        Remark {{ claimDecisionForm.decision === "appeal" ? "(required)" : "(optional)" }}
                    </label>
                    <textarea
                        v-model="claimDecisionForm.remark"
                        rows="4"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                        placeholder="Enter formal reason / note for this decision"
                    ></textarea>
                </div>

                <div v-if="claimDecisionForm.decision === 'accept'">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">
                        New Proforma Invoice (required)
                    </label>
                    <label class="block rounded-lg border-2 border-dashed border-emerald-200 bg-emerald-50/40 px-4 py-4 cursor-pointer hover:bg-emerald-50 transition">
                        <input
                            type="file"
                            class="hidden"
                            @change="handleAcceptProformaFile"
                        />
                        <div class="text-sm font-medium text-emerald-700">
                            {{ claimDecisionForm.accept_proforma_invoice ? claimDecisionForm.accept_proforma_invoice.name : "Click to upload new proforma invoice" }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            PDF / JPG / PNG, maximum 10MB
                        </div>
                    </label>
                </div>

                <div v-if="claimDecisionForm.decision === 'appeal'">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">
                        Additional Proof Attachment (required)
                    </label>
                    <label class="block rounded-lg border-2 border-dashed border-amber-200 bg-amber-50/40 px-4 py-4 cursor-pointer hover:bg-amber-50 transition">
                        <input
                            type="file"
                            class="hidden"
                            multiple
                            @change="handleAppealProofFile"
                        />
                        <div class="text-sm font-medium text-amber-700">
                            {{ claimDecisionForm.appeal_proof_attachments?.length ? `${claimDecisionForm.appeal_proof_attachments.length} file(s) selected` : "Click to upload proof attachment(s)" }}
                        </div>
                        <div v-if="claimDecisionForm.appeal_proof_attachments?.length" class="mt-1 text-xs text-gray-600">
                            {{ claimDecisionForm.appeal_proof_attachments.map((file) => file.name).join(", ") }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            PDF / JPG / PNG, maximum 10MB each
                        </div>
                    </label>
                </div>
            </div>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-2.5">
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-md hover:bg-gray-100"
                    @click="showClaimDecisionModal = false"
                >
                    Cancel
                </button>
                <button
                    class="px-3 py-1.5 text-xs font-medium text-white rounded-md"
                    :class="claimDecisionForm.decision === 'accept' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-500 hover:bg-amber-600'"
                    @click="submitClaimDecision"
                >
                    Confirm Decision
                </button>
            </div>
        </div>
    </div>

    <div v-if="showClaimInvoiceModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center px-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-indigo-50 to-violet-50 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Upload Real Invoice</h3>
                    <p class="text-xs text-gray-600 mt-0.5">Complete all mandatory fields and attach the official invoice.</p>
                </div>
                <button
                    class="h-7 w-7 rounded-full border border-gray-200 text-gray-500 hover:text-gray-700 hover:bg-white"
                    @click="showClaimInvoiceModal = false"
                >
                    x
                </button>
            </div>

            <div class="px-6 py-5 space-y-5">
                <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
                    <div class="text-xs uppercase tracking-wide text-indigo-700 font-semibold">Claim Reference</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">
                        {{ selectedClaim?.claim_no || "-" }} - {{ selectedClaim?.title || "-" }}
                    </div>
                    <div class="text-xs text-gray-600 mt-1">
                        Approved Amount: {{ formatMoney(selectedClaim?.approved_amount || 0) }}
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Invoice No</label>
                        <input
                            v-model="claimInvoiceForm.real_invoice_no"
                            type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                            placeholder="e.g. RI-2026-001"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Invoice Date</label>
                        <input
                            v-model="claimInvoiceForm.real_invoice_date"
                            type="date"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Invoice Amount</label>
                        <input
                            v-model="claimInvoiceForm.real_invoice_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Invoice File</label>
                        <label class="block rounded-lg border-2 border-dashed border-indigo-200 bg-indigo-50/40 px-4 py-4 cursor-pointer hover:bg-indigo-50 transition">
                            <input type="file" class="hidden" @change="handleClaimInvoiceFile" />
                            <div class="text-sm font-medium text-indigo-700">
                                {{ claimInvoiceForm.real_invoice ? claimInvoiceForm.real_invoice.name : "Click to upload real invoice" }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">PDF / JPG / PNG, maximum 10MB</div>
                        </label>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Remark (optional)</label>
                        <textarea
                            v-model="claimInvoiceForm.remark"
                            rows="3"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                            placeholder="Additional note for your invoice upload"
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-2.5">
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-md hover:bg-gray-100"
                    @click="showClaimInvoiceModal = false"
                >
                    Cancel
                </button>
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                    @click="submitClaimInvoiceUpload"
                >
                    Upload Invoice
                </button>
            </div>
        </div>
    </div>
</template>


