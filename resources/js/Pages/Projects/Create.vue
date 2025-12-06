<script setup>
import { ref } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();
const clients = page.props.clients;
const departments = page.props.departments;
const managers = page.props.managers;

const form = useForm({
    name: "",
    code: "",
    client_id: "",
    start_date: "",
    end_date: "",
    budget: "",
    department_id: "",
    manager_id: "",
    description: "",
});
</script>

<template>
    <Head title="Create Project" />

    <AuthenticatedLayout>

        <div class="p-6 max-w-5xl mx-auto">

            <!-- PAGE HEADER -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Create New Project</h2>

                <Link
                    :href="route('projects.index')"
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                    Back
                </Link>
            </div>

            <!-- FORM CARD -->
            <div class="bg-white shadow rounded-lg p-6 space-y-6">

                <!-- FORM GRID -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Project Name -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Project Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- Project Code -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Project Code</label>
                        <input
                            v-model="form.code"
                            type="text"
                            placeholder="Auto or manual"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- Client -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Client</label>
                        <select
                            v-model="form.client_id"
                            class="w-full border rounded px-3 py-2"
                        >
                            <option value="">Select Client</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Start Date</label>
                        <input
                            type="date"
                            v-model="form.start_date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- End Date -->
                    <div>
                        <label class="block text-sm font-medium mb-1">End Date</label>
                        <input
                            type="date"
                            v-model="form.end_date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- Budget -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Budget (RM)</label>
                        <input
                            type="number"
                            v-model="form.budget"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <!-- Department -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Department</label>
                        <select
                            v-model="form.department_id"
                            class="w-full border rounded px-3 py-2"
                        >
                            <option value="">Select Department</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">
                                {{ d.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Manager -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Project Manager</label>
                        <select
                            v-model="form.manager_id"
                            class="w-full border rounded px-3 py-2"
                        >
                            <option value="">Select Manager</option>
                            <option v-for="m in managers" :key="m.id" :value="m.id">
                                {{ m.name }}
                            </option>
                        </select>
                    </div>

                </div>

                <!-- DESCRIPTION -->
                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="w-full border rounded px-3 py-2"
                    ></textarea>
                </div>

                <!-- SUBMIT -->
                <div class="flex justify-end">
                    <button
                        @click="form.post(route('projects.store'))"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                        :disabled="form.processing"
                    >
                        Create Project
                    </button>
                </div>

            </div>
        </div>

    </AuthenticatedLayout>
</template>
