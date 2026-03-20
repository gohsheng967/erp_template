<script setup>
import { ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

// Import tab components
import OverviewTab from "./Tabs/OverviewTab.vue";
import DocumentationTab from "./Tabs/DocumentationTab.vue";
import MilestonesTab from "./Tabs/MilestonesTab.vue";
import AccountingTab from "./Tabs/AccountingTab.vue";
import SubConTasksTab from "./Tabs/SubConTasksTab.vue";
import SubConsTab from "./Tabs/SubConsTab.vue";
import SubConClaimsTab from "./Tabs/SubConClaimsTab.vue";
import { useFormat } from '@/Composables/useFormat'

const { capitalize, formatCurrency } = useFormat()

const page = usePage()
const project = ref(page.props.project)

const tabs = [
    { key: "overview", label: "Overview" },
    { key: "documentation", label: "Documentation" },
    { key: "accounting", label: "Accounting" },
    { key: "milestones", label: "Milestones" },
    { key: "subcons", label: "Sub Con" },
    { key: "subcon-tasks", label: "Sub Con Tasks" },
    { key: "subcon-claims", label: "Sub Con Claims" },
];

const tabStorageKey = `project-show-active-tab:${project.value.uuid}`
const allowedTabs = new Set(tabs.map((tab) => tab.key))

function resolveInitialTab() {
    const remembered = localStorage.getItem(tabStorageKey)
    if (remembered && allowedTabs.has(remembered)) {
        return remembered
    }

    return "overview"
}

const activeTab = ref(resolveInitialTab());

watch(activeTab, (tab) => {
    if (allowedTabs.has(tab)) {
        localStorage.setItem(tabStorageKey, tab)
    }
})

function onBudgetUpdated(newBudget) {
    project.value = {
        ...project.value,
        budget: Number(newBudget),
    }
}

function toggleFinished() {
    const nextState = !project.value.is_finished;

    router.patch(
        route("projects.toggle-finished", project.value.id),
        { is_finished: nextState },
        {
            preserveScroll: true,
            onSuccess: () => {
                project.value = {
                    ...project.value,
                    is_finished: nextState,
                };
            },
        }
    );
}

</script>

<template>
    <Head :title="project.name" />

    <AuthenticatedLayout>
        <div class="p-6 max-w-7xl mx-auto">

            <!-- HEADER -->
            <div class="mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">{{ project.name }}</h2>
                        <p class="text-gray-500 text-sm">
                            Project Code: {{ project.code }} · Status: {{ capitalize(project.status) }}
                        </p>
                        <p class="text-gray-500 text-sm">
                            End Date: {{ project.end_date || "-" }} · Extension Date: {{ project.extension_date || "-" }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                :checked="!!project.is_finished"
                                @change="toggleFinished"
                            />
                            Project Finished
                        </label>
                        <Link
                            :href="route('projects.index')"
                            class="px-3 py-1.5 text-sm bg-gray-200 rounded hover:bg-gray-300"
                        >
                            Back
                        </Link>
                    </div>
                </div>
            </div>

            <div class="project-tabs-compact">
            <!-- TABS -->
            <div class="border-b mb-4">
                <nav class="flex gap-6">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        class="pb-2 text-sm font-medium"
                        :class="activeTab === tab.key
                            ? 'text-indigo-600 border-b-2 border-indigo-600'
                            : 'text-gray-600 hover:text-gray-800'"
                    >
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- TAB CONTENT -->
            <div>
                <OverviewTab
                    v-if="activeTab === 'overview'"
                    :project="project"
                />

                <DocumentationTab
                    v-if="activeTab === 'documentation'"
                    :project="project"
                    :documents="page.props.documents"
                    :categories="page.props.categories"
                />

                <AccountingTab
                    v-if="activeTab === 'accounting'"
                    :project="project"
                    @budget-updated="onBudgetUpdated"
                />

                <MilestonesTab
                    v-if="activeTab === 'milestones'"
                    :project="project"
                />

                <div v-if="activeTab === 'subcons'" class="space-y-6">
                    <SubConsTab
                        :project="project"
                        :project-sub-cons="page.props.projectSubCons"
                        :sub-con-options="page.props.subConOptions"
                        :bank-options="page.props.bankOptions"
                    />
                </div>

                <div v-if="activeTab === 'subcon-tasks'" class="space-y-6">
                    <SubConTasksTab
                        :project="project"
                        :sub-cons="page.props.subCons"
                        :sub-con-tasks="page.props.subConTasks"
                        :project-purchase-orders="page.props.projectPurchaseOrders"
                    />
                </div>

                <div v-if="activeTab === 'subcon-claims'" class="space-y-6">
                    <SubConClaimsTab
                        :project="project"
                        :sub-cons="page.props.subCons"
                        :sub-con-claims="page.props.subConClaims"
                    />
                </div>
            </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
:deep(.project-tabs-compact button[class*="px-"]) {
    padding: 0.25rem 0.5rem !important;
    font-size: 0.75rem !important;
    line-height: 1rem !important;
}

:deep(.project-tabs-compact a[class*="px-"]) {
    padding: 0.25rem 0.5rem !important;
    font-size: 0.75rem !important;
    line-height: 1rem !important;
}
</style>
