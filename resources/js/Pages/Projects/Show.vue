<script setup>
import { ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

// Import tab components
import OverviewTab from "./Tabs/OverviewTab.vue";
import DocumentationTab from "./Tabs/DocumentationTab.vue";
import MilestonesTab from "./Tabs/MilestonesTab.vue";
import AccountingTab from "./Tabs/AccountingTab.vue";
import SubConTasksTab from "./Tabs/SubConTasksTab.vue";
import SubConsTab from "./Tabs/SubConsTab.vue";
import { useFormat } from '@/Composables/useFormat'

const { capitalize, formatCurrency } = useFormat()

const page = usePage()
const project = ref(page.props.project)

// Current active tab
const activeTab = ref("overview");

const tabs = [
    { key: "overview", label: "Overview" },
    { key: "documentation", label: "Documentation" },
    { key: "accounting", label: "Accounting" },
    { key: "milestones", label: "Milestones" },
    { key: "subcons", label: "Sub Con" },
    { key: "subcon_tasks", label: "Sub Con's Task" },
];

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
                            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                        >
                            Back
                        </Link>
                    </div>
                </div>
            </div>

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

                <SubConsTab
                    v-if="activeTab === 'subcons'"
                    :project="project"
                    :project-sub-cons="page.props.projectSubCons"
                    :sub-con-options="page.props.subConOptions"
                    :bank-options="page.props.bankOptions"
                />

                <SubConTasksTab
                    v-if="activeTab === 'subcon_tasks'"
                    :project="project"
                    :sub-cons="page.props.subCons"
                    :sub-con-tasks="page.props.subConTasks"
                />
            </div>

        </div>
    </AuthenticatedLayout>
</template>
