<script setup>
import { ref } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

// Import tab components
import OverviewTab from "./Tabs/OverviewTab.vue";
import DocumentationTab from "./Tabs/DocumentationTab.vue";
import FunctionalityTab from "./Tabs/AccountingTab.vue";
import MilestonesTab from "./Tabs/MilestonesTab.vue";
import AccountingTab from "./Tabs/AccountingTab.vue";

const page = usePage();
const project = page.props.project;

// Current active tab
const activeTab = ref("overview");

const tabs = [
    { key: "overview", label: "Overview" },
    { key: "documentation", label: "Documentation" },
    { key: "accounting", label: "Accounting" },
    { key: "milestones", label: "Milestones" },
];
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
                            Project Code: {{ project.code }} · Status: {{ project.status }}
                        </p>
                    </div>

                    <Link
                        :href="route('projects.index')"
                        class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    >
                        Back
                    </Link>
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
                />

                <MilestonesTab
                    v-if="activeTab === 'milestones'"
                    :project="project"
                />
            </div>

        </div>
    </AuthenticatedLayout>
</template>
