<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useFormat } from "@/Composables/useFormat";

const props = defineProps({
    client: {
        type: Object,
        required: true,
    },
    projects: {
        type: Object,
        required: true,
    },
    arSummary: {
        type: Object,
        required: true,
    },
    overdueInvoices: {
        type: Array,
        required: true,
    },
    dueSoonInvoices: {
        type: Array,
        required: true,
    },
    recentReceipts: {
        type: Array,
        required: true,
    },
    projectStatusCounts: {
        type: Object,
        required: true,
    },
    recentProjects: {
        type: Array,
        required: true,
    },
});

const { formatCurrency, formatDate, formatDateTime, capitalize } = useFormat();

const totalOutstanding = computed(() =>
    Math.max(
        Number(props.arSummary?.total_invoiced ?? 0) -
        Number(props.arSummary?.total_received ?? 0),
        0
    )
);

function statusBadgeClass(status) {
    switch ((status || "").toLowerCase()) {
        case "active":
        case "in_progress":
            return "bg-emerald-100 text-emerald-700";
        case "completed":
            return "bg-indigo-100 text-indigo-700";
        case "paused":
        case "on_hold":
            return "bg-amber-100 text-amber-700";
        case "cancelled":
            return "bg-red-100 text-red-700";
        default:
            return "bg-gray-100 text-gray-700";
    }
}

function budgetBarClass(percent) {
    if (percent > 100) return "bg-red-500";
    if (percent >= 80) return "bg-amber-500";
    return "bg-emerald-500";
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">Client Details</h2>
                    <p class="text-sm text-gray-500">
                        {{ client.name }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('ar-invoices.index', { customer_id: client.id, create: 1 })"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm"
                    >
                        <i class="mdi mdi-file-plus-outline text-base"></i>
                        Create AR Invoice
                    </Link>
                    <Link
                        :href="route('ar-invoices.index', { customer_id: client.id })"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm"
                    >
                        <i class="mdi mdi-file-eye-outline text-base"></i>
                        View AR Invoices
                    </Link>
                    <Link
                        :href="route('clients.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm"
                    >
                        <i class="mdi mdi-arrow-left text-base"></i>
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 space-y-6">
            <div class="bg-white border rounded-2xl p-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-start gap-6">
                    <div
                        class="w-14 h-14 rounded-xl bg-indigo-100 flex items-center justify-center
                               text-indigo-600 font-bold text-lg shrink-0"
                    >
                        {{ client.name?.charAt(0) || "C" }}
                    </div>

                    <div class="flex-1 space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">
                                {{ client.name }}
                            </h2>
                        </div>

                        <p class="text-sm text-gray-500">
                            Company:
                            <span class="text-gray-700 font-medium">
                                {{ client.company_name || "-" }}
                            </span>
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm mt-4">
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="font-medium text-gray-800 break-all">
                                    {{ client.email || "-" }}
                                </p>
                            </div>

                            <div>
                                <p class="text-gray-500">Phone</p>
                                <p class="font-medium text-gray-800">
                                    {{ client.phone || "-" }}
                                </p>
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t">
                            <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                                Address
                            </p>
                            <p class="text-sm text-gray-700 whitespace-pre-line">
                                {{ client.address || "-" }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AR SUMMARY -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Total Invoiced</div>
                    <div class="text-2xl font-semibold text-gray-800">
                        {{ formatCurrency(arSummary.total_invoiced) }}
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Total Received</div>
                    <div class="text-2xl font-semibold text-emerald-700">
                        {{ formatCurrency(arSummary.total_received) }}
                    </div>
                </div>
                <div class="bg-white border rounded-2xl p-5 shadow-sm">
                    <div class="text-xs text-gray-500">Outstanding</div>
                    <div class="text-2xl font-semibold text-red-700">
                        {{ formatCurrency(totalOutstanding) }}
                    </div>
                </div>
            </div>

            <!-- AR DUE -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white border rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Overdue AR</h3>
                        <span class="text-xs text-red-600">
                            {{ overdueInvoices.length }} item(s)
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="invoice in overdueInvoices"
                            :key="invoice.uuid"
                            class="flex items-center justify-between border rounded-lg px-4 py-3 bg-red-50"
                        >
                            <div>
                                <div class="text-sm font-semibold text-gray-800">
                                    {{ invoice.invoice_no || invoice.title }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Due: {{ formatDate(invoice.due_date) }} -
                                    Project: {{ invoice.project?.name ?? "-" }}
                                </div>
                            </div>
                            <div class="text-sm font-semibold text-red-700">
                                {{ formatCurrency(Math.max(Number(invoice.total_amount) - Number(invoice.receipts_sum_amount ?? 0), 0)) }}
                            </div>
                        </div>

                        <div
                            v-if="!overdueInvoices.length"
                            class="text-sm text-gray-500 text-center py-4"
                        >
                            No overdue invoices.
                        </div>
                    </div>
                </div>

                <div class="bg-white border rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Due Soon (5 days)</h3>
                        <span class="text-xs text-amber-600">
                            {{ dueSoonInvoices.length }} item(s)
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="invoice in dueSoonInvoices"
                            :key="invoice.uuid"
                            class="flex items-center justify-between border rounded-lg px-4 py-3 bg-amber-50"
                        >
                            <div>
                                <div class="text-sm font-semibold text-gray-800">
                                    {{ invoice.invoice_no || invoice.title }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Due: {{ formatDate(invoice.due_date) }} -
                                    Project: {{ invoice.project?.name ?? "-" }}
                                </div>
                            </div>
                            <div class="text-sm font-semibold text-amber-700">
                                {{ formatCurrency(Math.max(Number(invoice.total_amount) - Number(invoice.receipts_sum_amount ?? 0), 0)) }}
                            </div>
                        </div>

                        <div
                            v-if="!dueSoonInvoices.length"
                            class="text-sm text-gray-500 text-center py-4"
                        >
                            No invoices due soon.
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECEIPTS + PROJECT HEALTH -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white border rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Receipts</h3>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="receipt in recentReceipts"
                            :key="receipt.id"
                            class="flex items-center justify-between border rounded-lg px-4 py-3"
                        >
                            <div>
                                <div class="text-sm font-semibold text-gray-800">
                                    {{ receipt.invoice?.invoice_no ?? "AR Receipt" }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ formatDateTime(receipt.received_at) }} -
                                    Ref: {{ receipt.reference || "-" }}
                                </div>
                            </div>
                            <div class="text-sm font-semibold text-emerald-700">
                                {{ formatCurrency(receipt.amount) }}
                            </div>
                        </div>

                        <div
                            v-if="!recentReceipts.length"
                            class="text-sm text-gray-500 text-center py-4"
                        >
                            No receipts found.
                        </div>
                    </div>
                </div>

                <div class="bg-white border rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Project Health</h3>
                    </div>

                    <div class="flex flex-wrap gap-2 mb-4">
                        <span
                            v-for="(count, status) in projectStatusCounts"
                            :key="status"
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="statusBadgeClass(status)"
                        >
                            {{ capitalize(status || "unknown") }}: {{ count }}
                        </span>
                        <span
                            v-if="!Object.keys(projectStatusCounts).length"
                            class="text-sm text-gray-500"
                        >
                            No projects found.
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="proj in recentProjects"
                            :key="proj.id"
                            class="border rounded-lg px-4 py-3 space-y-2"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800">
                                        {{ proj.name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Updated: {{ formatDateTime(proj.updated_at) }}
                                    </div>
                                </div>
                                <span
                                    class="text-xs font-semibold rounded-full px-2 py-1"
                                    :class="statusBadgeClass(proj.status)"
                                >
                                    {{ capitalize(proj.status || "unknown") }}
                                </span>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span>Budget Health</span>
                                    <span class="font-semibold text-gray-700">
                                        {{ proj.budget_percent ?? 0 }}%
                                    </span>
                                </div>
                                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                                    <div
                                        class="h-2 rounded-full"
                                        :class="budgetBarClass(proj.budget_percent ?? 0)"
                                        :style="{ width: `${Math.min(proj.budget_percent ?? 0, 100)}%` }"
                                    ></div>
                                </div>
                                <div class="text-xs text-gray-600">
                                    Used {{ formatCurrency(proj.budget_used ?? 0) }} /
                                    {{ formatCurrency(proj.budget ?? 0) }}
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">Overdue Tasks</span>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 font-semibold"
                                    :class="proj.overdue_tasks > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                >
                                    {{ proj.overdue_tasks ?? 0 }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border rounded-2xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Projects</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    Code
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    Name
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    Dates
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="project in projects.data" :key="project.id">
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ project.code || "-" }}
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ project.name || "-" }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 capitalize">
                                    {{ project.status || "-" }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ formatDate(project.start_date) }} - {{ formatDate(project.end_date) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Link
                                        :href="route('projects.show', project.uuid)"
                                        class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800"
                                    >
                                        <i class="mdi mdi-eye-outline text-base"></i>
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="projects.data.length === 0">
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                    No projects found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex gap-1">
                    <Link
                        v-for="link in projects.links"
                        :key="link.label"
                        :href="link.url ?? ''"
                        v-html="link.label"
                        class="px-3 py-1 rounded border text-sm"
                        :class="{
                            'bg-indigo-600 text-white': link.active,
                            'text-gray-500 cursor-not-allowed': !link.url
                        }"
                        :disabled="!link.url"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
