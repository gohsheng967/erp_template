<script setup>
import { computed, inject, ref } from "vue";
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

function showError(errors, fallback) {
    const msg =
        errors?.status ||
        errors?.parent_id ||
        errors?.delete ||
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
    router.post(
        route("projects.sub-con-tasks.store", { project: props.project.uuid }),
        {
            sub_con_id: newTask.value.sub_con_id,
            title: newTask.value.title,
            amount: newTask.value.amount || 0,
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
    router.patch(
        route("projects.sub-con-tasks.update", {
            project: props.project.uuid,
            task: selectedTask.value.uuid,
        }),
        {
            sub_con_id: editForm.value.sub_con_id,
            title: editForm.value.title,
            amount: editForm.value.amount || 0,
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

        slipData.value = res.data.slip;
        showSlipModal.value = true;
        toast?.value?.show("Payment slip generated.", "success");
        showCertModal.value = false;
        refresh();
    } catch (error) {
        showError(error?.response?.data?.errors, "Failed to generate payment slip.");
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
                    action === "approve" ? "Progress verified." : "Progress rejected.",
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
                toast?.value?.show(
                    action === "approve" ? "Payment justified." : "Justification rejected.",
                    action === "approve" ? "success" : "error"
                );
                showJustifyModal.value = false;
                selectedTask.value = null;
                justifyForm.value.remark = "";
                refresh();

                if (action === "approve") {
                    openCert(task);
                }
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

function statusClass(status) {
    switch ((status || "").toLowerCase()) {
        case "submitted":
            return "bg-amber-100 text-amber-700";
        case "verified":
            return "bg-indigo-100 text-indigo-700";
        case "justified":
            return "bg-emerald-100 text-emerald-700";
        case "certified":
            return "bg-sky-100 text-sky-700";
        case "paid":
            return "bg-emerald-200 text-emerald-800";
        default:
            return "bg-gray-100 text-gray-700";
    }
}

const parentTasks = computed(() =>
    tasks.value.filter((t) => !t.parent_id)
);

const childTasks = computed(() =>
    tasks.value.filter((t) => t.parent_id)
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
                    <label class="block text-sm text-gray-600">Amount</label>
                    <input
                        v-model="newTask.amount"
                        type="number"
                        min="0"
                        step="0.01"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                        placeholder="0.00"
                    />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-600">Parent Task (optional)</label>
                    <select
                        v-model="newTask.parent_id"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    >
                        <option value="">No parent</option>
                        <option v-for="task in tasks" :key="task.uuid" :value="task.id">
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
        </div>

        <div class="bg-white border rounded-lg overflow-hidden">
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
                                    {{ capitalize(task.status || "draft") }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-wrap justify-center gap-2">
                                    <button
                                        v-if="['draft', 'submitted'].includes(task.status)"
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openProgress(task)"
                                    >
                                        Update Progress
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openHistory(task)"
                                    >
                                        History
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openEdit(task)"
                                    >
                                        Edit
                                    </button>
                                    <button
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
                                        Verify
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-emerald-100 text-emerald-700 hover:bg-emerald-200"
                                        v-if="task.status === 'verified'"
                                        @click="openJustify(task)"
                                    >
                                        Justify
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-sky-100 text-sky-700 hover:bg-sky-200"
                                        v-if="task.status === 'justified'"
                                        @click="openCert(task)"
                                    >
                                        Cert
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-emerald-200 text-emerald-800 hover:bg-emerald-300"
                                        v-if="task.status === 'certified'"
                                        @click="openPaid(task)"
                                    >
                                        Paid
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
                                    :class="statusClass(child.status)"
                                >
                                    {{ capitalize(child.status || "draft") }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-wrap justify-center gap-2">
                                    <button
                                        v-if="['draft', 'submitted'].includes(child.status)"
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openProgress(child)"
                                    >
                                        Update Progress
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openHistory(child)"
                                    >
                                        History
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200"
                                        @click="openEdit(child)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200"
                                        @click="openDelete(child)"
                                    >
                                        Delete
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200"
                                        v-if="child.status === 'submitted'"
                                        @click="openVerify(child)"
                                    >
                                        Verify
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-emerald-100 text-emerald-700 hover:bg-emerald-200"
                                        v-if="child.status === 'verified'"
                                        @click="openJustify(child)"
                                    >
                                        Justify
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-sky-100 text-sky-700 hover:bg-sky-200"
                                        v-if="child.status === 'justified'"
                                        @click="openCert(child)"
                                    >
                                        Cert
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded bg-emerald-200 text-emerald-800 hover:bg-emerald-300"
                                        v-if="child.status === 'certified'"
                                        @click="openPaid(child)"
                                    >
                                        Paid
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="tasks.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                            No Sub Con tasks yet.
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
                    <label class="block text-sm text-gray-600">Amount</label>
                    <input
                        v-model="editForm.amount"
                        type="number"
                        min="0"
                        step="0.01"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    />
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Parent Task (optional)</label>
                    <select
                        v-model="editForm.parent_id"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    >
                        <option value="">No parent</option>
                        <option v-for="task in tasks" :key="task.uuid" :value="task.id">
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
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Verify Progress</h3>
                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="showVerifyModal = false"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4">
                <label class="block text-sm text-gray-600">Remark (required for reject)</label>
                <textarea
                    v-model="verifyForm.remark"
                    rows="3"
                    class="mt-1 w-full border rounded-md px-3 py-2"
                    placeholder="Add remark"
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
                <h3 class="text-lg font-semibold text-gray-800">Justify Payment</h3>
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
