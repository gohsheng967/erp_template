<script setup>
import { ref } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateUserModal from "./Partials/CreateUserModal.vue";
import EditUserModal from "./Partials/EditUserModal.vue";

const page = usePage();

const users = page.props.users;
const filters = page.props.filters;
const departments = page.props.departments;
const rolesByDept = page.props.rolesByDept;

// UI States
const showCreate = ref(false);
const showEdit = ref(false);
const selectedUser = ref(null);

// Search box composable
const search = ref(filters.search ?? "");
const statusFilter = ref(filters.status ?? "");

// Open edit modal
function openEdit(user) {
    selectedUser.value = user;
    showEdit.value = true;
}

</script>

<template>
    <AuthenticatedLayout>

        <!-- PAGE HEADER -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">User Management</h2>

                <button
                    @click="showCreate = true"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                >
                    + Create User
                </button>
            </div>
        </template>

        <!-- PAGE CONTENT -->
        <div class="p-6 space-y-6">

            <!-- FILTERS -->
            <div class="bg-white p-4 rounded-lg shadow flex items-end gap-4 w-full">

                <!-- SEARCH -->
                <div class="flex flex-col w-full">
                    <label class="block text-sm font-small text-gray-700">Search</label>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Name / Email / Identity No"
                        @keyup.enter="$inertia.get(route('users.index'), { search, status: statusFilter })"
                        class="border rounded-md px-3 py-2 w-full"
                    />
                </div>

                <!-- STATUS -->
                <div class="flex flex-col w-40">
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select
                        v-model="statusFilter"
                        class="border rounded-md px-3 py-2 w-full"
                        @change="$inertia.get(route('users.index'), { search, status: statusFilter })"
                    >
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Suspended</option>
                    </select>
                </div>

                <!-- APPLY BUTTON -->
                <button
                    class="px-4 py-2 h-10 bg-gray-200 rounded hover:bg-gray-300"
                    @click="$inertia.get(route('users.index'), { search, status: statusFilter })"
                >
                    Apply
                </button>

            </div>


            <!-- USERS TABLE -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Identity No</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departments / Roles</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="user in users.data" :key="user.id">

                            <!-- Identity No -->
                            <td class="px-4 py-3 text-sm">{{ user.identity_no }}</td>

                            <!-- Name -->
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                {{ user.name }}
                            </td>

                            <!-- Email -->
                            <td class="px-4 py-3 text-sm">{{ user.email }}</td>

                            <!-- Departments / Roles -->
                            <td class="px-4 py-3 text-sm">
                                <div v-for="a in user.assignments" :key="a.department_id">
                                    <span class="font-semibold">{{ a.department }}</span> —
                                    <span>{{ a.role }}</span>
                                </div>
                            </td>

                            <!-- STATUS -->
                            <td class="px-4 py-3 text-center">
                                <span
                                    :class="user.status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                    class="px-2 py-1 rounded text-xs"
                                >
                                    {{ user.status ? 'Active' : 'Suspended' }}
                                </span>
                            </td>

                            <!-- ACTIONS -->
                            <td class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-2">

                                    <!-- EDIT -->
                                    <button
                                        v-if="!user.is_protected"
                                        @click="openEdit(user)"
                                        class="action-btn text-indigo-600 hover:bg-indigo-50"
                                        title="Edit user"
                                    >
                                        <i class="mdi mdi-pencil-outline"></i>
                                    </button>

                                    <span
                                        v-else
                                        class="action-btn text-gray-300 cursor-not-allowed"
                                        title="Protected user"
                                    >
                                        <i class="mdi mdi-pencil-outline"></i>
                                    </span>

                                    <!-- RESET PASSWORD -->
                                    <Link
                                        v-if="!user.is_protected"
                                        :href="route('users.reset-password', user.id)"
                                        method="post"
                                        as="button"
                                        class="action-btn text-amber-600 hover:bg-amber-50"
                                        title="Reset password"
                                    >
                                        <i class="mdi mdi-key-outline"></i>
                                    </Link>

                                    <span
                                        v-else
                                        class="action-btn text-gray-300 cursor-not-allowed"
                                    >
                                        <i class="mdi mdi-key-outline"></i>
                                    </span>

                                    <!-- ACTIVATE / SUSPEND -->
                                    <Link
                                        v-if="!user.is_protected"
                                        :href="route('users.toggle-status', user.id)"
                                        method="post"
                                        as="button"
                                        class="action-btn text-red-600 hover:bg-red-50"
                                        title="Suspend / Activate"
                                    >
                                        <i class="mdi mdi-account-cancel-outline"></i>
                                    </Link>

                                    <span
                                        v-else
                                        class="action-btn text-gray-300 cursor-not-allowed"
                                    >
                                        <i class="mdi mdi-account-cancel-outline"></i>
                                    </span>

                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div class="mt-4 flex gap-1">

                <Link
                    v-for="link in users.links"
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

        <!-- MODALS -->
        <CreateUserModal
            v-model="showCreate"
            :departments="departments"
            :roles-by-dept="rolesByDept"
        />

        <EditUserModal
            v-model="showEdit"
            :user="selectedUser"
            :departments="departments"
            :roles-by-dept="rolesByDept"
        />

    </AuthenticatedLayout>
</template>
