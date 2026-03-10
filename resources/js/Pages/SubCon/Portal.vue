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
const showHistoryModal = ref(false);
const selectedTask = ref(null);
const progressForm = ref({
    progress_percent: "",
    note: "",
    attachment: null,
});

const canSubmitStatuses = ["draft", "submitted"];

const groupedTasks = computed(() => {
    const groups = {};
    (props.tasks ?? []).forEach((task) => {
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

function logout() {
    router.post(route("sub-con.logout"));
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

function openProgress(task) {
    selectedTask.value = task;
    progressForm.value = { progress_percent: "", note: "", attachment: null };
    showProgressModal.value = true;
}

function openHistory(task) {
    selectedTask.value = task;
    showHistoryModal.value = true;
}

function handleFileChange(event) {
    progressForm.value.attachment = event.target.files?.[0] ?? null;
}

function submitProgress() {
    if (!selectedTask.value?.project?.uuid || !selectedTask.value?.uuid) {
        toast?.value?.show("Invalid task selection.", "error");
        return;
    }

    const fd = new FormData();
    fd.append("progress_percent", progressForm.value.progress_percent);
    fd.append("note", progressForm.value.note || "");

    if (progressForm.value.attachment) {
        fd.append("attachment", progressForm.value.attachment);
    }

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
                const message = errors?.status || errors?.progress_percent || "Failed to submit progress.";
                toast?.value?.show(message, "error");
            },
        }
    );
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <header class="bg-white border-b">
            <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-500">Sub Con Portal</div>
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
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-500">Total</div>
                    <div class="text-lg font-semibold">{{ stats.total }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-500">Draft</div>
                    <div class="text-lg font-semibold">{{ stats.draft }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-500">Submitted</div>
                    <div class="text-lg font-semibold">{{ stats.submitted }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-500">Verified</div>
                    <div class="text-lg font-semibold">{{ stats.verified }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-500">Justified</div>
                    <div class="text-lg font-semibold">{{ stats.justified }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-500">Certified</div>
                    <div class="text-lg font-semibold">{{ stats.certified }}</div>
                </div>
                <div class="bg-white border rounded-lg p-3">
                    <div class="text-xs text-gray-500">Paid</div>
                    <div class="text-lg font-semibold">{{ stats.paid }}</div>
                </div>
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
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Task</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Progress</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-center text-xs uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="task in group.tasks" :key="task.uuid">
                                <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ task.title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatMoney(task.amount) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ task.progress_percent ?? 0 }}%</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex px-2 py-1 rounded-full text-xs font-semibold"
                                        :class="statusClass(task.status)"
                                    >
                                        {{ task.status }}
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

                <div>
                    <label class="block text-sm text-gray-600">Progress (%)</label>
                    <input
                        v-model="progressForm.progress_percent"
                        type="number"
                        min="0"
                        max="100"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    />
                </div>

                <div>
                    <label class="block text-sm text-gray-600">Note (optional)</label>
                    <textarea
                        v-model="progressForm.note"
                        rows="3"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                    />
                </div>

                <div>
                    <label class="block text-sm text-gray-600">Attachment (optional)</label>
                    <input
                        type="file"
                        class="mt-1 w-full border rounded-md px-3 py-2"
                        @change="handleFileChange"
                    />
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
</template>
