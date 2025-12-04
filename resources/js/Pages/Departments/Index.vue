<script setup>
import { ref } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

// Modals
import CreateDepartmentModal from "./Partials/CreateDepartmentModal.vue";
import EditDepartmentModal from "./Partials/EditDepartmentModal.vue";
import CreateRoleModal from "./Partials/CreateRoleModal.vue";
import EditRoleModal from "./Partials/EditRoleModal.vue";

const page = usePage();
const departments = page.props.departments;

// UI Controls
const expanded = ref(null);

const showCreateDept = ref(false);
const showEditDept = ref(false);
const showCreateRole = ref(false);
const showEditRole = ref(false);

// Selected items
const selectedDepartment = ref(null);
const selectedRole = ref(null);

function openEditDepartment(dept) {
    selectedDepartment.value = dept;
    showEditDept.value = true;
}

function openCreateRole(dept) {
    selectedDepartment.value = dept;
    showCreateRole.value = true;
}

function openEditRole(role, dept) {
    selectedRole.value = role;
    selectedDepartment.value = dept;
    showEditRole.value = true;
}
</script>

<template>
    <AuthenticatedLayout>

        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">
                    Department & Role Management
                </h2>

                <button
                    @click="showCreateDept = true"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                >
                    + Create Department
                </button>
            </div>
        </template>

        <div class="p-6 space-y-6">

            <!-- ACCORDION LIST -->
            <div class="bg-white rounded-lg shadow divide-y">
                <div
                    v-for="dept in departments"
                    :key="dept.id"
                    class="border-b last:border-0"
                >
                    <!-- HEADER ROW -->
                    <div
                        class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50"
                        @click="expanded = expanded === dept.id ? null : dept.id"
                    >
                        <div class="flex items-center gap-3">

                            <!-- Chevron -->
                            <svg
                                :class="['h-5 transition', expanded === dept.id ? 'rotate-90' : '']"
                                fill="none" stroke="currentColor" stroke-width="1.5"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M9 5l7 7-7 7"/>
                            </svg>

                            <!-- Department Name -->
                            <span class="font-semibold text-gray-800 text-lg">
                                {{ dept.name }}
                            </span>

                            <!-- User count -->
                            <span class="text-sm text-gray-500">
                                ({{ dept.users_count }} users)
                            </span>
                        </div>

                        <div class="flex items-center gap-3">

                            <!-- Edit -->
                            <button
                                @click.stop="openEditDepartment(dept)"
                                class="text-indigo-600 hover:text-indigo-800"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M16.862 3.487l3.651 3.65M10.5 9.75l6.362-6.263m-6.362 6.263L4.5 15.75v3.75h3.75l6.363-6.263m-3.888-3.738l3.887 3.738"/>
                                </svg>
                            </button>

                            <!-- Delete -->
                            <Link
                                :href="route('departments.destroy', dept.id)"
                                method="delete"
                                as="button"
                                class="text-red-600 hover:text-red-800"
                                @click.stop
                            >
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1H9a1 1 0 00-1 1v3m12 0H4"/>
                                </svg>
                            </Link>
                        </div>
                    </div>

                    <!-- ROLES LIST -->
                    <div
                        v-if="expanded === dept.id"
                        class="px-10 pb-5 space-y-3 animate-fade"
                    >
                        <!-- Create Role button -->
                        <button
                            @click="openCreateRole(dept)"
                            class="mt-2 px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-sm"
                        >
                            + Add Role
                        </button>

                        <!-- Roles Loop -->
                        <div
                            v-for="role in dept.roles"
                            :key="role.id"
                            class="flex justify-between items-center bg-gray-50 border px-4 py-2 rounded"
                        >
                            <span>{{ role.name }}</span>

                            <div class="flex gap-3">

                                <!-- Edit role -->
                                <button
                                    @click="openEditRole(role, dept)"
                                    class="text-indigo-600 hover:text-indigo-800"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                         stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M16.862 3.487l3.651 3.65M10.5 9.75l6.362-6.263m-6.362 6.263L4.5 15.75v3.75h3.75l6.363-6.263m-3.888-3.738l3.887 3.738"/>
                                    </svg>
                                </button>

                                <!-- Delete role -->
                                <Link
                                    :href="route('roles.destroy', role.id)"
                                    method="delete"
                                    as="button"
                                    class="text-red-600 hover:text-red-800"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                         stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1H9a1 1 0 00-1 1v3m12 0H4"/>
                                    </svg>
                                </Link>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODALS -->
        <CreateDepartmentModal v-model="showCreateDept" />
        <EditDepartmentModal v-model="showEditDept" :department="selectedDepartment" />

        <CreateRoleModal v-model="showCreateRole" :department="selectedDepartment" />
        <EditRoleModal v-model="showEditRole" :role="selectedRole" />

    </AuthenticatedLayout>
</template>

<style>
.animate-fade {
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
