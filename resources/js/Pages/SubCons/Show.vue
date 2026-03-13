<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useFormat } from "@/Composables/useFormat";

const props = defineProps({
    subCon: {
        type: Object,
        required: true,
    },
    taskStats: {
        type: Object,
        required: true,
    },
    portalUser: {
        type: Object,
        default: null,
    },
    projectSummaries: {
        type: Array,
        default: () => [],
    },
    recentTasks: {
        type: Array,
        default: () => [],
    },
});

const { formatCurrency, formatDateTime, capitalize } = useFormat();
const stats = computed(() => props.taskStats ?? {});
const bankAccounts = computed(() => props.subCon?.bank_accounts ?? []);
const completionRate = computed(() => {
    const total = Number(stats.value.total ?? 0);
    if (!total) return 0;
    return Math.round((Number(stats.value.paid ?? 0) / total) * 100);
});

const workflowStats = computed(() => [
    { label: "In Progress", value: stats.value.draft ?? 0, tone: "bg-slate-100 text-slate-700" },
    { label: "Submitted", value: stats.value.submitted ?? 0, tone: "bg-amber-100 text-amber-700" },
    { label: "Project Verified", value: stats.value.contra_verified ?? 0, tone: "bg-indigo-100 text-indigo-700" },
    { label: "Invoiced", value: stats.value.invoiced ?? 0, tone: "bg-purple-100 text-purple-700" },
    { label: "Approved", value: stats.value.justified ?? 0, tone: "bg-emerald-100 text-emerald-700" },
    { label: "Certified", value: stats.value.certified ?? 0, tone: "bg-sky-100 text-sky-700" },
    { label: "Paid", value: stats.value.paid ?? 0, tone: "bg-emerald-200 text-emerald-800" },
]);

function statusClass(status) {
    switch ((status || "").toLowerCase()) {
        case "submitted":
            return "bg-amber-100 text-amber-700";
        case "contra_verified":
        case "verified":
            return "bg-indigo-100 text-indigo-700";
        case "invoiced":
            return "bg-purple-100 text-purple-700";
        case "approved":
        case "justified":
            return "bg-emerald-100 text-emerald-700";
        case "certified":
            return "bg-sky-100 text-sky-700";
        case "paid":
            return "bg-emerald-200 text-emerald-800";
        default:
            return "bg-slate-100 text-slate-700";
    }
}

function statusLabel(status) {
    const value = (status || "").toLowerCase();
    if (value === "draft") return "In Progress";
    if (value === "contra_verified" || value === "verified") return "Project Verified";
    if (value === "justified") return "Approved";
    return capitalize(value || "draft");
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">Sub Con Details</h2>
                    <p class="text-sm text-gray-500">
                        {{ subCon.name }}{{ subCon.company_name ? ` - ${subCon.company_name}` : "" }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('projects.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm"
                    >
                        <i class="mdi mdi-briefcase-outline"></i>
                        Manage Project Tasks
                    </Link>
                    <Link
                        :href="route('sub-cons.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm"
                    >
                        <i class="mdi mdi-arrow-left text-base"></i>
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 mb-6">
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Total Tasks</div>
                    <div class="text-2xl font-semibold text-gray-800">
                        {{ stats.total || 0 }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Across {{ stats.project_count || 0 }} project(s)
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Contract Value</div>
                    <div class="text-2xl font-semibold text-gray-800">
                        {{ formatCurrency(stats.total_amount || 0) }}
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Invoiced Amount</div>
                    <div class="text-2xl font-semibold text-indigo-700">
                        {{ formatCurrency(stats.invoiced_amount || 0) }}
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Paid Amount</div>
                    <div class="text-2xl font-semibold text-emerald-700">
                        {{ formatCurrency(stats.paid_amount || 0) }}
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Outstanding</div>
                    <div class="text-2xl font-semibold text-orange-700">
                        {{ formatCurrency(stats.outstanding_amount || 0) }}
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Completion</div>
                    <div class="text-2xl font-semibold text-gray-800">
                        {{ completionRate }}%
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-gray-100">
                        <div class="h-2 rounded-full bg-emerald-500" :style="{ width: `${completionRate}%` }"></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="bg-white border rounded-2xl p-6 shadow-sm xl:col-span-2">
                    <div class="flex flex-col md:flex-row md:items-start gap-6">
                        <div
                            class="w-14 h-14 rounded-xl bg-indigo-100 flex items-center justify-center
                               text-indigo-600 font-bold text-lg shrink-0"
                        >
                            {{ subCon.name?.charAt(0) || "S" }}
                        </div>

                        <div class="flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-2xl font-semibold text-gray-800 leading-tight">
                                    {{ subCon.name }}
                                </h2>
                            </div>

                            <p class="text-sm text-gray-500">
                                Company:
                                <span class="text-gray-700 font-medium">
                                    {{ subCon.company_name || "-" }}
                                </span>
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm mt-4">
                                <div>
                                    <p class="text-gray-500">Email</p>
                                    <p class="font-medium text-gray-800 break-all">
                                        {{ subCon.email || "-" }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-gray-500">Phone</p>
                                    <p class="font-medium text-gray-800">
                                        {{ subCon.phone || "-" }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-gray-500">Last Task Update</p>
                                    <p class="font-medium text-gray-800">
                                        {{ formatDateTime(stats.latest_task_update_at) || "-" }}
                                    </p>
                                </div>
                            </div>

                            <div class="pt-4 mt-4 border-t">
                                <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                                    Address
                                </p>
                                <p class="text-sm text-gray-700 whitespace-pre-line">
                                    {{ subCon.address || "-" }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border rounded-2xl p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-800 mb-3">Portal Account</h3>
                    <div v-if="portalUser" class="space-y-3 text-sm">
                        <div>
                            <div class="text-xs text-gray-500">Identity No</div>
                            <div class="font-medium text-gray-800">{{ portalUser.identity_no }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Login Email</div>
                            <div class="font-medium text-gray-800 break-all">{{ portalUser.email }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="portalUser.status ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ portalUser.status ? "Active" : "Inactive" }}
                            </span>
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="portalUser.must_change_password ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700'"
                            >
                                {{ portalUser.must_change_password ? "Password Reset Required" : "Password Up-to-Date" }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500">
                        No portal account configured.
                    </div>

                    <div class="mt-5 pt-5 border-t">
                        <h4 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Bank Accounts</h4>
                        <div v-if="bankAccounts.length" class="space-y-2 text-sm">
                            <div
                                v-for="(account, index) in bankAccounts"
                                :key="account.id ?? index"
                                class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2"
                            >
                                <p class="font-medium text-gray-800">
                                    {{ account.bank_name || "-" }} - {{ account.account_no || "-" }}
                                </p>
                                <p class="text-xs text-gray-500">{{ account.account_name || "-" }}</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500">
                            {{ subCon.bank || "-" }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white border rounded-2xl p-6 shadow-sm mb-6">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <h3 class="text-base font-semibold text-gray-800">Task Workflow Overview</h3>
                    <span class="text-xs text-gray-500">
                        {{ stats.total || 0 }} total task(s)
                    </span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3">
                    <div
                        v-for="item in workflowStats"
                        :key="item.label"
                        class="rounded-xl border border-gray-100 p-3"
                    >
                        <div class="text-xs text-gray-500">{{ item.label }}</div>
                        <div class="mt-2 inline-flex rounded-full px-2.5 py-1 text-sm font-semibold" :class="item.tone">
                            {{ item.value }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border rounded-2xl shadow-sm mb-6 overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-base font-semibold text-gray-800">Project Breakdown</h3>
                    <p class="text-sm text-gray-500">
                        Payment and task progress grouped by project.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tasks</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Invoiced</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Paid</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Outstanding</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in projectSummaries" :key="row.project?.uuid">
                                <td class="px-4 py-3 text-sm">
                                    <Link
                                        :href="route('projects.show', row.project.uuid)"
                                        class="font-medium text-indigo-700 hover:text-indigo-900"
                                    >
                                        {{ row.project.name }}
                                    </Link>
                                    <div class="text-xs text-gray-500">{{ row.project.code || "-" }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ row.total_tasks }} ({{ row.draft_tasks }} in progress)
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ formatCurrency(row.invoiced_amount || 0) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ formatCurrency(row.paid_amount || 0) }}
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-orange-700">
                                    {{ formatCurrency(row.outstanding_amount || 0) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ formatDateTime(row.latest_task_update_at) || "-" }}
                                </td>
                            </tr>
                            <tr v-if="!projectSummaries.length">
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                                    No project tasks found for this sub con.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-base font-semibold text-gray-800">Recent Task Activity</h3>
                    <p class="text-sm text-gray-500">
                        Latest task updates and payment stage signals.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Task</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Progress</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="task in recentTasks" :key="task.uuid">
                                <td class="px-4 py-3 text-sm text-gray-800">
                                    <div class="font-medium">
                                        {{ task.task_no || task.title }}
                                    </div>
                                    <div class="text-xs text-gray-500">{{ task.title }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <Link
                                        v-if="task.project?.uuid"
                                        :href="route('projects.show', task.project.uuid)"
                                        class="text-indigo-700 hover:text-indigo-900"
                                    >
                                        {{ task.project.name }}
                                    </Link>
                                    <span v-else class="text-gray-500">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(task.status)">
                                        {{ statusLabel(task.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ formatCurrency(task.invoice_amount ?? task.amount ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ task.progress_percent ?? 0 }}%
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ formatDateTime(task.updated_at) || "-" }}
                                </td>
                            </tr>
                            <tr v-if="!recentTasks.length">
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                                    No recent task activity.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
