<script setup>
import { computed, inject, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useFormat } from "@/Composables/useFormat";
import DeleteConfirmation from "@/Components/DeleteConfirmation.vue";
import SubConPaymentSlipModal from "./Partials/SubConPaymentSlipModal.vue";
import axios from "axios";

const toast = inject("toast", null);
const { formatCurrency, formatDateTime, capitalize } = useFormat();

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    subCons: {
        type: Array,
        required: true,
    },
    subConTasks: {
        type: Array,
        required: true,
    },
    projectPurchaseOrders: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const tasks = computed(() => props.subConTasks ?? []);
const companyBankAccounts = computed(() => {
    const accounts = page.props.companyBankAccounts ?? [];
    return accounts.filter((account) => account.status === "active");
});

const newTask = ref({
    sub_con_id: "",
    title: "",
    amount: "",
    parent_id: "",
});

const showProgressModal = ref(false);
const showCertModal = ref(false);
const showEditModal = ref(false);
const showDelete = ref(false);
const showHistoryModal = ref(false);
const showVerifyModal = ref(false);
const showJustifyModal = ref(false);
const showPaidModal = ref(false);
const showSlipModal = ref(false);
const selectedTask = ref(null);
const expandedParents = ref(new Set());

const progressForm = ref({
    progress_percent: "",
    note: "",
    attachment: null,
});

const certForm = ref({
    company_bank_account_id: "",
    less_retention: "",
    less_recoupment: "",
    less_material_ob: "",
    less_paid_previously: "",
    payment_slip_remark: "",
});

const editForm = ref({
    sub_con_id: "",
    title: "",
    amount: "",
    parent_id: "",
});

const verifyForm = ref({
    remark: "",
});

const justifyForm = ref({
    remark: "",
});

const paidForm = ref({
    payment_ref_no: "",
    attachments: [],
});

const slipData = ref(null);
const activeStage = ref("all");
const poTaskForm = ref({
    purchase_order_id: "",
    sub_con_id: "",
});

const stageTabs = [
    { key: "all", label: "All" },
    { key: "draft", label: "In Progress" },
    { key: "submitted", label: "Submitted" },
    { key: "verified", label: "Verified" },
];

const stageHints = {
    all: "All main tasks across every stage.",
    draft: "Sub Con is still progressing the task.",
    submitted: "Task completed by Sub Con and waiting Project Department review.",
    verified: "Project Department has verified completion.",
};

function showError(errors, fallback) {
    const msg =
        errors?.status ||
        errors?.company_bank_account_id ||
        errors?.remark ||
        errors?.parent_id ||
        errors?.delete ||
        (Array.isArray(Object.values(errors || {})[0]) ? Object.values(errors || {})[0][0] : null) ||
        errors?.message ||
        fallback ||
        "Action failed.";
    toast?.value?.show(msg, "error");
}

function refresh() {
    router.reload({
        only: ["subConTasks"],
        preserveScroll: true,
    });
}

function createTask() {
    const isMainTask = !newTask.value.parent_id;
    if (isMainTask && (newTask.value.amount === "" || newTask.value.amount === null)) {
        toast?.value?.show("Main task amount is required.", "error");
        return;
    }

    router.post(
        route("projects.sub-con-tasks.store", { project: props.project.uuid }),
        {
            sub_con_id: newTask.value.sub_con_id,
            title: newTask.value.title,
            amount: isMainTask ? newTask.value.amount : 0,
            parent_id: newTask.value.parent_id || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Sub Con task created.", "success");
                newTask.value = { sub_con_id: "", title: "", amount: "", parent_id: "" };
                refresh();
            },
            onError: (errors) => showError(errors, "Failed to create task."),
        }
    );
}

function generateTasksFromPo() {
    if (!poTaskForm.value.purchase_order_id || !poTaskForm.value.sub_con_id) {
        toast?.value?.show("Please select both PO and Sub Con.", "error");
        return;
    }

    router.post(
        route("projects.sub-con-tasks.generate-from-po", { project: props.project.uuid }),
        {
            purchase_order_id: poTaskForm.value.purchase_order_id,
            sub_con_id: poTaskForm.value.sub_con_id,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Tasks generated from PO.", "success");
                poTaskForm.value = { purchase_order_id: "", sub_con_id: "" };
                refresh();
            },
            onError: (errors) => showError(errors, "Failed to generate tasks from PO."),
        }
    );
}

function openProgress(task) {
    selectedTask.value = task;
    progressForm.value = { progress_percent: "", note: "", attachment: null };
    showProgressModal.value = true;
}

function submitProgress() {
    const fd = new FormData();
    fd.append("progress_percent", progressForm.value.progress_percent);
    fd.append("note", progressForm.value.note || "");
    if (progressForm.value.attachment) {
        fd.append("attachment", progressForm.value.attachment);
    }

    router.post(
        route("projects.sub-con-tasks.updates.store", {
            project: props.project.uuid,
            task: selectedTask.value.uuid,
        }),
        fd,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Progress submitted.", "success");
                showProgressModal.value = false;
                selectedTask.value = null;
                refresh();
            },
            onError: (errors) => showError(errors, "Failed to submit progress."),
        }
    );
}

function openEdit(task) {
    selectedTask.value = task;
    editForm.value = {
        sub_con_id: task.sub_con_id,
        title: task.title,
        amount: task.amount,
        parent_id: task.parent_id || "",
    };
    showEditModal.value = true;
}

function submitEdit() {
    const isMainTask = !editForm.value.parent_id;
    if (isMainTask && (editForm.value.amount === "" || editForm.value.amount === null)) {
        toast?.value?.show("Main task amount is required.", "error");
        return;
    }

    router.patch(
        route("projects.sub-con-tasks.update", {
            project: props.project.uuid,
            task: selectedTask.value.uuid,
        }),
        {
            sub_con_id: editForm.value.sub_con_id,
            title: editForm.value.title,
            amount: isMainTask ? editForm.value.amount : 0,
            parent_id: editForm.value.parent_id || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Task updated.", "success");
                showEditModal.value = false;
                selectedTask.value = null;
                refresh();
            },
            onError: (errors) => showError(errors, "Failed to update task."),
        }
    );
}

function openDelete(task) {
    selectedTask.value = task;
    showDelete.value = true;
}

function deleteTask() {
    router.delete(
        route("projects.sub-con-tasks.destroy", {
            project: props.project.uuid,
            task: selectedTask.value.uuid,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Task deleted.", "success");
                showDelete.value = false;
                selectedTask.value = null;
                refresh();
            },
            onError: (errors) => showError(errors, "Failed to delete task."),
        }
    );
}

function openCert(task) {
    selectedTask.value = task;
    certForm.value = {
        company_bank_account_id: "",
        less_retention: "",
        less_recoupment: "",
        less_material_ob: "",
        less_paid_previously: "",
        payment_slip_remark: "",
    };
    showCertModal.value = true;
}

async function submitCert() {
    if (!certForm.value.company_bank_account_id) {
        toast?.value?.show("Please select a company bank account.", "error");
        return;
    }

    try {
        const res = await axios.post(
            route("projects.sub-con-tasks.certify", {
                project: props.project.uuid,
                task: selectedTask.value.uuid,
            }),
            {
                company_bank_account_id: certForm.value.company_bank_account_id,
                less_retention: certForm.value.less_retention || null,
                less_recoupment: certForm.value.less_recoupment || null,
                less_material_ob: certForm.value.less_material_ob || null,
                less_paid_previously: certForm.value.less_paid_previously || null,
                payment_slip_remark: certForm.value.payment_slip_remark || null,
            }
        );

        if (!res?.data?.slip) {
            throw new Error("Payment slip response is invalid.");
        }

        slipData.value = res.data.slip;
        toast?.value?.show("Payment slip generated.", "success");
        showCertModal.value = false;
        refresh();
    } catch (error) {
        const errors = error?.response?.data?.errors ?? {
            message: error?.response?.data?.message || error?.message,
        };
        showError(errors, "Failed to generate payment slip.");
    }
}

function verifyTask(task, action) {
    if (action === "reject" && !verifyForm.value.remark) {
        toast?.value?.show("Remark is required to reject.", "error");
        return;
    }

    router.post(
        route("projects.sub-con-tasks.verify", { project: props.project.uuid, task: task.uuid }),
        {
            action,
            remark: verifyForm.value.remark || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show(
                    action === "approve"
                        ? "Task reviewed and moved to Verified."
                        : "Task rejected and moved to In Progress.",
                    action === "approve" ? "success" : "error"
                );
                showVerifyModal.value = false;
                selectedTask.value = null;
                verifyForm.value.remark = "";
                refresh();
            },
            onError: (errors) => showError(errors, "Failed to verify."),
        }
    );
}

function justifyTask(task, action) {
    if (action === "reject" && !justifyForm.value.remark) {
        toast?.value?.show("Remark is required to reject.", "error");
        return;
    }

    router.post(
        route("projects.sub-con-tasks.justify", { project: props.project.uuid, task: task.uuid }),
        {
            action,
            remark: justifyForm.value.remark || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (action !== "approve") {
                    toast?.value?.show("Approval rejected.", "error");
                }
                showJustifyModal.value = false;
                selectedTask.value = null;
                justifyForm.value.remark = "";
                refresh();
            },
            onError: (errors) => showError(errors, "Failed to justify."),
        }
    );
}

function openHistory(task) {
    selectedTask.value = task;
    showHistoryModal.value = true;
}

function openVerify(task) {
    selectedTask.value = task;
    verifyForm.value.remark = "";
    showVerifyModal.value = true;
}

function allChildrenOf(parentId) {
    return tasks.value.filter((t) => t.parent_id === parentId);
}

function latestUpdateOf(task) {
    const updates = task?.updates ?? [];
    if (!updates.length) return null;

    return updates
        .slice()
        .sort((a, b) => new Date(b?.created_at || 0) - new Date(a?.created_at || 0))[0];
}

function attachmentUpdatesOf(task) {
    return (task?.updates ?? []).filter((update) => update?.attachment_path);
}

function openJustify(task) {
    selectedTask.value = task;
    justifyForm.value.remark = "";
    showJustifyModal.value = true;
}

function openPaid(task) {
    selectedTask.value = task;
    paidForm.value = { payment_ref_no: "", attachments: [] };
    showPaidModal.value = true;
}

function handlePaidFiles(event) {
    paidForm.value.attachments = Array.from(event.target.files);
}

function submitPaid() {
    const fd = new FormData();
    fd.append("payment_ref_no", paidForm.value.payment_ref_no);
    paidForm.value.attachments.forEach((file) => {
        fd.append("attachments[]", file);
    });

    router.post(
        route("projects.sub-con-tasks.paid", {
            project: props.project.uuid,
            task: selectedTask.value.uuid,
        }),
        fd,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Marked as paid.", "success");
                showPaidModal.value = false;
                selectedTask.value = null;
                refresh();
            },
            onError: (errors) => showError(errors, "Failed to mark paid."),
        }
    );
}

function openSlip(task) {
    slipData.value = task?.payment_slip ?? null;
    if (!slipData.value) {
        toast?.value?.show("Payment slip not found.", "error");
        return;
    }
    showSlipModal.value = true;
}

function downloadInvoice(task) {
    if (!task?.invoice_attachment_path) {
        toast?.value?.show("Invoice attachment not found.", "error");
        return;
    }

    window.open(
        route("projects.sub-con-tasks.invoice.download", {
            project: props.project.uuid,
            task: task.uuid,
        }),
        "_blank"
    );
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
    return capitalize(value || "draft");
}

function isChildChecked(task) {
    return (task?.status || "").toLowerCase() !== "draft";
}

function normalizedStatus(task) {
    const status = (task?.status || "").toLowerCase();
    if (["verified", "contra_verified", "invoiced", "approved", "justified", "certified", "paid"].includes(status)) return "verified";
    return status;
}

const taskStageCounts = computed(() => {
    const mainTasks = tasks.value.filter((task) => !task.parent_id);
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
    if (activeStage.value === "all") return tasks.value;
    return tasks.value.filter((task) => normalizedStatus(task) === activeStage.value);
});

const parentTasks = computed(() =>
    filteredTasks.value.filter((t) => !t.parent_id)
);

const childTasks = computed(() =>
    filteredTasks.value.filter((t) => t.parent_id)
);

const mainTaskOptions = computed(() =>
    tasks.value.filter((t) => !t.parent_id)
);

function childrenOf(parentId) {
    return childTasks.value.filter((t) => t.parent_id === parentId);
}

function isExpanded(task) {
    return expandedParents.value.has(task.id);
}

function toggleChildren(task) {
    if (isExpanded(task)) {
        expandedParents.value.delete(task.id);
    } else {
        expandedParents.value.add(task.id);
    }
    expandedParents.value = new Set(expandedParents.value);
}

watch(activeStage, () => {
    expandedParents.value = new Set();
});

const activeStageHint = computed(() => stageHints[activeStage.value] ?? "");

watch(
    () => poTaskForm.value.purchase_order_id,
    (poId) => {
        const selectedPo = (props.projectPurchaseOrders ?? []).find(
            (po) => Number(po.id) === Number(poId)
        );

        poTaskForm.value.sub_con_id = selectedPo?.suggested_sub_con_id ?? "";
    }
);
</script>

<template>
    <div class="space-y-6">
        <div class="bg-white border rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Create Sub Con Task</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-600">Sub Con</label>
                    <select
                        v-model="newTask.sub_con_id"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    >
                        <option value="">Select sub con</option>
                        <option v-for="sc in subCons" :key="sc.id" :value="sc.id">
                            {{ sc.name }} ({{ sc.company_name || "N/A" }})
                        </option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-600">Task Title</label>
                    <input
                        v-model="newTask.title"
                        type="text"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                        placeholder="Task title"
                    />
                </div>

                <div>
                    <label class="block text-sm text-gray-600">
                        Amount
                        <span v-if="!newTask.parent_id" class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="newTask.amount"
                        type="number"
                        min="0"
                        step="0.01"
                        :disabled="!!newTask.parent_id"
                        class="mt-1 w-full border rounded-md px-3 py-2 disabled:bg-gray-100 disabled:text-gray-400"
                        :placeholder="newTask.parent_id ? 'Auto 0 for sub task' : '0.00'"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ newTask.parent_id ? "Sub task amount is fixed to 0." : "Required for main task." }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-600">Parent Task (optional)</label>
                    <select
                        v-model="newTask.parent_id"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    >
                        <option value="">No parent</option>
                        <option v-for="task in mainTaskOptions" :key="task.uuid" :value="task.id">
                            {{ task.title }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                    @click="createTask"
                >
                    Add Task
                </button>
            </div>

            <div class="mt-5 border-t pt-4">
                <h4 class="text-sm font-semibold text-gray-800">Generate From Confirmed PO</h4>
                <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm text-gray-600">Purchase Order</label>
                        <select
                            v-model="poTaskForm.purchase_order_id"
                            class="mt-1 w-full border rounded-md px-3 py-2"
                        >
                            <option value="">Select confirmed PO</option>
                            <option
                                v-for="po in projectPurchaseOrders"
                                :key="po.id"
                                :value="po.id"
                            >
                                {{ po.code }} · {{ po.supplier?.company_name || "N/A" }} · {{ po.items?.length || 0 }} item(s)
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600">Sub Con</label>
                        <select
                            v-model="poTaskForm.sub_con_id"
                            class="mt-1 w-full border rounded-md px-3 py-2"
                        >
                            <option value="">Select sub con</option>
                            <option v-for="sc in subCons" :key="sc.id" :value="sc.id">
                                {{ sc.name }} ({{ sc.company_name || "N/A" }})
                            </option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button
                        class="px-4 py-2 bg-slate-700 text-white rounded hover:bg-slate-800"
                        @click="generateTasksFromPo"
                    >
                        Generate Tasks
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white border rounded-lg overflow-hidden">
            <div class="border-b bg-gray-50 px-4 py-3">
                <div class="flex flex-wrap gap-2">
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
                            {{ taskStageCounts[tab.key] ?? 0 }}
                        </span>
                    </button>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    {{ activeStageHint }}
                </p>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Task
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Sub Con
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Amount
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Progress
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Status
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <template v-for="task in parentTasks" :key="task.uuid">
                        <tr>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="childrenOf(task.id).length"
                                        class="text-xs px-2 py-0.5 rounded bg-gray-100 hover:bg-gray-200"
                                        @click="toggleChildren(task)"
                                    >
                                        {{ isExpanded(task) ? '-' : '+' }}
                                    </button>
                                    <span
                                        class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"
                                    >
                                        Parent
                                    </span>
                                    <div class="font-medium text-gray-900">
                                        {{ task.title }}
                                    </div>
                                </div>
                                <div v-if="childrenOf(task.id).length" class="text-xs text-gray-500 mt-1">
                                    Child Tasks: <span class="font-medium text-gray-700">{{ childrenOf(task.id).length }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ task.sub_con?.name ?? "-" }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ formatCurrency(task.amount) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ task.progress_percent ?? 0 }}%
                                <div v-if="task.updates?.length" class="text-xs text-gray-500">
                                    Last: {{ task.updates[0]?.note || "Update submitted" }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="statusClass(task.status)"
                                >
                                    {{ statusLabel(task.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-wrap justify-center gap-2">
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openHistory(task)"
                                    >
                                        History
                                    </button>
                                    <button
                                        v-if="!['submitted', 'verified', 'contra_verified', 'invoiced', 'approved', 'justified', 'certified', 'paid'].includes((task.status || '').toLowerCase())"
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openEdit(task)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="!['submitted', 'verified', 'contra_verified', 'invoiced', 'approved', 'justified', 'certified', 'paid'].includes((task.status || '').toLowerCase())"
                                        class="px-2 py-1 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200"
                                        @click="openDelete(task)"
                                    >
                                        Delete
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200"
                                        v-if="task.status === 'submitted'"
                                        @click="openVerify(task)"
                                    >
                                        Review
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-for="child in childrenOf(task.id)"
                            v-show="isExpanded(task)"
                            :key="child.uuid"
                            class="bg-gray-50"
                        >
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center gap-2 pl-6">
                                    <span
                                        class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700"
                                    >
                                        Child
                                    </span>
                                    <div class="font-medium text-gray-900">
                                        {{ child.title }}
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1 pl-6">
                                    Parent Task: <span class="font-medium text-gray-700">{{ task.title }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ child.sub_con?.name ?? "-" }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ formatCurrency(child.amount) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ child.progress_percent ?? 0 }}%
                                <div v-if="child.updates?.length" class="text-xs text-gray-500">
                                    Last: {{ child.updates[0]?.note || "Update submitted" }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="isChildChecked(child) ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'"
                                >
                                    <i
                                        v-if="isChildChecked(child)"
                                        class="mdi mdi-check text-sm leading-none"
                                        title="Checked"
                                    ></i>
                                    <span v-else>Unchecked</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-wrap justify-center gap-2">
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openHistory(child)"
                                    >
                                        History
                                    </button>
                                    <button
                                        v-if="!['submitted', 'verified', 'contra_verified', 'invoiced', 'approved', 'justified', 'certified', 'paid'].includes((child.status || '').toLowerCase())"
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openEdit(child)"
                                    >
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="parentTasks.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                            No Sub Con tasks for this stage.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Progress Modal -->
    <div v-if="showProgressModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Submit Progress</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showProgressModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm text-gray-600">Progress (%)</label>
                    <input
                        v-model="progressForm.progress_percent"
                        type="number"
                        min="0"
                        max="100"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                        placeholder="0 - 100"
                    />
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Note</label>
                    <textarea
                        v-model="progressForm.note"
                        rows="3"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                        placeholder="Progress note"
                    ></textarea>
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Attachment (optional)</label>
                    <div class="mt-2 border-2 border-dashed rounded-lg p-4 text-center cursor-pointer hover:border-indigo-400 transition">
                        <input
                            type="file"
                            class="hidden"
                            id="subconProgressUpload"
                            @change="(e) => (progressForm.attachment = e.target.files[0])"
                        />
                        <label for="subconProgressUpload" class="cursor-pointer">
                            <i class="mdi mdi-upload text-2xl text-gray-400"></i>
                            <p class="text-sm text-gray-600 mt-1">
                                Click to upload attachment
                            </p>
                            <p class="text-xs text-gray-400">PDF / JPG / PNG • Max 10MB</p>
                        </label>
                    </div>
                    <div v-if="progressForm.attachment" class="mt-3 text-xs text-gray-700">
                        Selected: <span class="font-medium">{{ progressForm.attachment.name }}</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    @click="showProgressModal = false"
                >
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                    @click="submitProgress"
                >
                    Submit
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Edit Task</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showEditModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm text-gray-600">Sub Con</label>
                    <select
                        v-model="editForm.sub_con_id"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    >
                        <option value="">Select sub con</option>
                        <option v-for="sc in subCons" :key="sc.id" :value="sc.id">
                            {{ sc.name }} ({{ sc.company_name || "N/A" }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Task Title</label>
                    <input
                        v-model="editForm.title"
                        type="text"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    />
                </div>
                <div>
                    <label class="block text-sm text-gray-600">
                        Amount
                        <span v-if="!editForm.parent_id" class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="editForm.amount"
                        type="number"
                        min="0"
                        step="0.01"
                        :disabled="!!editForm.parent_id"
                        class="mt-1 w-full border rounded-md px-3 py-2 disabled:bg-gray-100 disabled:text-gray-400"
                        :placeholder="editForm.parent_id ? 'Auto 0 for sub task' : '0.00'"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ editForm.parent_id ? "Sub task amount is fixed to 0." : "Required for main task." }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Parent Task (optional)</label>
                    <select
                        v-model="editForm.parent_id"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    >
                        <option value="">No parent</option>
                        <option v-for="task in mainTaskOptions" :key="task.uuid" :value="task.id">
                            {{ task.title }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    @click="showEditModal = false"
                >
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                    @click="submitEdit"
                >
                    Update
                </button>
            </div>
        </div>
    </div>

    <!-- Cert Modal -->
    <div v-if="showCertModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Payment Cert</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showCertModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="text-sm font-medium">
                        Company Bank Account
                    </label>
                    <select
                        v-model="certForm.company_bank_account_id"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    >
                        <option value="">Select Company Bank Account</option>
                        <option
                            v-for="account in companyBankAccounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.bank_name }} - {{ account.account_no }}
                        </option>
                    </select>
                    <p v-if="!companyBankAccounts.length" class="text-xs text-gray-400 mt-1">
                        No active company bank accounts found.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Less - Retention</label>
                        <input
                            v-model="certForm.less_retention"
                            type="number"
                            step="0.01"
                            class="mt-1 w-full border rounded-md px-3 py-2"
                            placeholder="0.00"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Less - Recoupment Advance Payment</label>
                        <input
                            v-model="certForm.less_recoupment"
                            type="number"
                            step="0.01"
                            class="mt-1 w-full border rounded-md px-3 py-2"
                            placeholder="0.00"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Less - Payment Material Purchased OB</label>
                        <input
                            v-model="certForm.less_material_ob"
                            type="number"
                            step="0.01"
                            class="mt-1 w-full border rounded-md px-3 py-2"
                            placeholder="0.00"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Less - Amount Paid Previously</label>
                        <input
                            v-model="certForm.less_paid_previously"
                            type="number"
                            step="0.01"
                            class="mt-1 w-full border rounded-md px-3 py-2"
                            placeholder="0.00"
                        />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium">
                        Payment Slip Remark (Optional)
                    </label>
                    <textarea
                        v-model="certForm.payment_slip_remark"
                        rows="2"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                        placeholder="Optional"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    @click="showCertModal = false"
                >
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                    @click="submitCert"
                >
                    Generate Slip
                </button>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div v-if="showHistoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Progress History</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showHistoryModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4">
                <div v-if="!selectedTask?.updates?.length" class="text-sm text-gray-500">
                    No updates yet.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="update in selectedTask.updates"
                        :key="update.uuid"
                        class="border rounded-lg px-4 py-3"
                    >
                        <div class="flex justify-between items-center">
                            <div class="text-sm font-semibold text-gray-800">
                                {{ update.progress_percent }}%
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ formatDateTime(update.created_at) }}
                            </div>
                        </div>
                        <div class="text-sm text-gray-700 mt-1">
                            {{ update.note || "No note" }}
                        </div>
                        <div v-if="update.attachment_path" class="mt-2">
                            <a
                                :href="route('projects.sub-con-tasks.updates.download', {
                                    project: project.uuid,
                                    task: selectedTask.uuid,
                                    update: update.uuid,
                                })"
                                class="text-indigo-600 hover:text-indigo-800 text-sm"
                                target="_blank"
                                rel="noopener"
                            >Download attachment</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <DeleteConfirmation
        v-if="showDelete"
        title="Delete Task"
        message="Are you sure you want to delete this task?"
        @confirm="deleteTask"
        @close="showDelete = false"
    />

    <!-- Verify Modal -->
    <div v-if="showVerifyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Review Submission</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showVerifyModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div class="rounded-lg border bg-gray-50 p-4">
                    <div class="text-sm text-gray-500">Submitted Main Task</div>
                    <div class="mt-1 font-semibold text-gray-900">
                        {{ selectedTask?.title || "-" }}
                    </div>
                    <div class="mt-1 text-sm text-gray-600">
                        Sub Con: {{ selectedTask?.sub_con?.name || "-" }}
                    </div>
                    <div class="mt-1 text-sm text-gray-600">
                        Progress: {{ selectedTask?.progress_percent ?? 0 }}%
                    </div>
                    <div class="mt-2 text-sm text-gray-600">
                        Latest Note:
                        <span class="text-gray-800">
                            {{ latestUpdateOf(selectedTask)?.note || "No note" }}
                        </span>
                    </div>
                </div>

                <div v-if="allChildrenOf(selectedTask?.id).length" class="rounded-lg border p-4">
                    <div class="text-sm font-medium text-gray-700 mb-2">Submitted Items</div>
                    <div class="space-y-2">
                        <div
                            v-for="child in allChildrenOf(selectedTask?.id)"
                            :key="child.uuid"
                            class="flex items-center justify-between rounded border px-3 py-2"
                        >
                            <span class="text-sm text-gray-800">{{ child.title }}</span>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="isChildChecked(child) ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'"
                            >
                                <i
                                    v-if="isChildChecked(child)"
                                    class="mdi mdi-check text-sm leading-none"
                                    title="Checked"
                                ></i>
                                <span v-else>Unchecked</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="attachmentUpdatesOf(selectedTask).length" class="rounded-lg border p-4">
                    <div class="text-sm font-medium text-gray-700 mb-2">Submitted Attachments</div>
                    <div class="space-y-2 max-h-44 overflow-auto">
                        <a
                            v-for="update in attachmentUpdatesOf(selectedTask)"
                            :key="update.uuid"
                            :href="route('projects.sub-con-tasks.updates.download', { project: project.uuid, task: selectedTask.uuid, update: update.uuid })"
                            class="flex items-center justify-between rounded bg-gray-50 px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50 border"
                            target="_blank"
                            rel="noopener"
                        >
                            <span class="truncate max-w-[250px]">{{ update.attachment_name || "Attachment" }}</span>
                            <span class="text-gray-500">{{ formatDateTime(update.created_at) }}</span>
                        </a>
                    </div>
                </div>

                <label class="block text-sm text-gray-600">Review Remark (required for reject)</label>
                <textarea
                    v-model="verifyForm.remark"
                    rows="3"
                    class="mt-1 w-full border rounded-md px-3 py-2"
                    placeholder="Add review remark"
                ></textarea>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    @click="showVerifyModal = false"
                >
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                    @click="verifyTask(selectedTask, 'reject')"
                >
                    Reject
                </button>
                <button
                    class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700"
                    @click="verifyTask(selectedTask, 'approve')"
                >
                    Approve
                </button>
            </div>
        </div>
    </div>

    <!-- Justify Modal -->
    <div v-if="showJustifyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Review Invoice</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showJustifyModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4">
                <label class="block text-sm text-gray-600">Remark (required for reject)</label>
                <textarea
                    v-model="justifyForm.remark"
                    rows="3"
                    class="mt-1 w-full border rounded-md px-3 py-2"
                    placeholder="Add remark"
                ></textarea>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    @click="showJustifyModal = false"
                >
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                    @click="justifyTask(selectedTask, 'reject')"
                >
                    Reject
                </button>
                <button
                    class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700"
                    @click="justifyTask(selectedTask, 'approve')"
                >
                    Approve
                </button>
            </div>
        </div>
    </div>

    <!-- Paid Modal -->
    <div v-if="showPaidModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Mark as Paid</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showPaidModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div class="bg-gray-50 border rounded-lg p-4 text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Task</span>
                        <span class="font-semibold">
                            {{ selectedTask?.title ?? "-" }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Amount</span>
                        <span class="font-semibold">
                            {{ formatCurrency(selectedTask?.amount ?? 0) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Payment Slip</span>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">
                                {{ selectedTask?.payment_slip_no ?? selectedTask?.payment_slip?.slip_no ?? "-" }}
                            </span>
                            <button
                                class="text-indigo-600 text-xs"
                                @click="openSlip(selectedTask)"
                            >
                                View
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium">
                        Payment Reference No
                    </label>
                    <input
                        v-model="paidForm.payment_ref_no"
                        type="text"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                        placeholder="e.g. Maybank-FT-240903-001"
                    />
                </div>

                <div>
                    <label class="text-sm font-medium">Payment Voucher / Slip</label>
                    <div class="mt-2 border-2 border-dashed rounded-lg p-4 text-center cursor-pointer">
                        <input
                            type="file"
                            multiple
                            class="hidden"
                            id="subconPaidUpload"
                            @change="handlePaidFiles"
                        />
                        <label for="subconPaidUpload" class="cursor-pointer">
                            <i class="mdi mdi-upload text-2xl text-gray-400"></i>
                            <p class="text-sm text-gray-600 mt-1">
                                Click to upload payment slip(s)
                            </p>
                            <p class="text-xs text-gray-400">At least 1 file • Max 10MB each</p>
                        </label>
                    </div>

                    <ul v-if="paidForm.attachments.length" class="mt-3 text-xs text-gray-700 space-y-1">
                        <li
                            v-for="(file, i) in paidForm.attachments"
                            :key="i"
                            class="flex justify-between bg-gray-50 rounded px-3 py-1"
                        >
                            <span class="truncate max-w-[220px]">
                                {{ file.name }}
                            </span>
                            <span class="text-gray-400">
                                {{ (file.size / 1024 / 1024).toFixed(2) }} MB
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    @click="showPaidModal = false"
                >
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700"
                    :disabled="!paidForm.payment_ref_no || !paidForm.attachments.length"
                    @click="submitPaid"
                >
                    Confirm Payment
                </button>
            </div>
        </div>
    </div>

    <SubConPaymentSlipModal
        :show="showSlipModal"
        :slip="slipData"
        @close="showSlipModal = false"
    />
</template>

