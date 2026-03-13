<script setup>
import { computed, inject, ref } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    subCon: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    tasks: {
        type: Array,
        required: true,
    },
});

const toast = inject("toast", null);

const showProgressModal = ref(false);
const showInvoiceModal = ref(false);
const showHistoryModal = ref(false);
const selectedTask = ref(null);
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
const selectedTaskHasChildren = computed(() => (selectedTask.value?.children?.length ?? 0) > 0);

const stageTabs = [
    { key: "all", label: "All" },
    { key: "draft", label: "In Progress" },
    { key: "submitted", label: "Submitted" },
    { key: "contra_verified", label: "Project Verified" },
    { key: "invoiced", label: "Invoiced" },
    { key: "payment", label: "Payment" },
];

const stageHints = {
    all: "All main tasks across every stage.",
    draft: "Continue submitting progress until task completion.",
    submitted: "Task is complete and waiting Project Department review.",
    contra_verified: "Project Department verified (Project Verified). You may submit invoice.",
    invoiced: "Invoice submitted and pending CEO / GM approval.",
    payment: "After approval, continue follow-up in Payment tab.",
};

function normalizedStatus(task) {
    const status = (task?.status || "").toLowerCase();
    if (status === "verified") return "contra_verified";
    if (["approved", "justified", "certified", "paid"].includes(status)) return "payment";
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
    if (activeStage.value === "all") return mainTasks;
    return mainTasks.filter((task) => normalizedStatus(task) === activeStage.value);
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

function logout() {
    router.post(route("sub-con.logout"));
}

function statusClass(status) {
    switch ((status || "").toLowerCase()) {
        case "submitted":
            return "bg-amber-100 text-amber-700";
        case "contra_verified":
            return "bg-indigo-100 text-indigo-700";
        case "verified":
            return "bg-indigo-100 text-indigo-700";
        case "invoiced":
            return "bg-violet-100 text-violet-700";
        case "approved":
            return "bg-emerald-100 text-emerald-700";
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

function statusLabel(status) {
    const value = (status || "").toLowerCase();
    if (value === "draft") return "In Progress";
    if (value === "contra_verified" || value === "verified") return "Project Verified";
    if (value === "justified") return "Approved";
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
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="bg-white border-b">
            <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-indigo-600">Sub Con Portal</div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        {{ subCon?.name }}<span v-if="subCon?.company_name"> - {{ subCon.company_name }}</span>
                    </h1>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-sm text-gray-600">{{ subCon?.login_identity_no }}</div>
                    <button
                        class="px-3 py-2 text-sm bg-red-50 text-red-700 rounded border border-red-200 hover:bg-red-100"
                        @click="logout"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
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
                    <div class="text-xs uppercase tracking-wide text-gray-500">Project Verified</div>
                    <div class="mt-1 text-xl font-semibold text-indigo-700">{{ stats.contra_verified }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Invoiced</div>
                    <div class="mt-1 text-xl font-semibold text-violet-700">{{ stats.invoiced }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Payment</div>
                    <div class="mt-1 text-xl font-semibold text-emerald-700">{{ stats.payment }}</div>
                </div>
            </div>

            <div class="bg-white border rounded-lg px-4 py-3">
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
                            {{ stageCounts[tab.key] ?? 0 }}
                        </span>
                    </button>
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
                                    <div v-if="activeStage === 'payment'" class="text-center text-xs text-gray-400">
                                        Status only
                                    </div>
                                    <div v-else class="flex justify-center gap-2">
                                        <button
                                            v-if="canSubmitStatuses.includes(task.status)"
                                            class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700"
                                            @click="openProgress(task)"
                                        >
                                            Submit Progress
                                        </button>
                                        <button
                                            v-if="['contra_verified', 'verified'].includes(task.status)"
                                            class="px-2 py-1 text-xs rounded bg-violet-600 text-white hover:bg-violet-700"
                                            @click="openInvoice(task)"
                                        >
                                            Submit Invoice
                                        </button>
                                        <button
                                            class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200"
                                            @click="openHistory(task)"
                                        >
                                            History
                                        </button>
                                        <a
                                            v-if="task.invoice_attachment_path"
                                            :href="route('sub-con.tasks.invoice.download', { task: task.uuid })"
                                            class="px-2 py-1 text-xs rounded bg-violet-100 text-violet-700 hover:bg-violet-200"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Invoice File
                                        </a>
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
        </main>
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
</template>

