<script setup>
import { ref } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateUserModal from "./Partials/CreateUserModal.vue";
import EditUserModal from "./Partials/EditUserModal.vue";
import ManageBranchesModal from "./Partials/ManageBranchesModal.vue";

const page = usePage();

const users = page.props.users;
const filters = page.props.filters;
const departments = page.props.departments;
const rolesByDept = page.props.rolesByDept;
const branches = page.props.branches;

const showCreate = ref(false);
const showEdit = ref(false);
const showBranches = ref(false);
const selectedUser = ref(null);

const search = ref(filters.search ?? "");
const statusFilter = ref(filters.status ?? "");

function openEdit(user) {
    selectedUser.value = user;
    showEdit.value = true;
}

function openBranches(user) {
    selectedUser.value = user;
    showBranches.value = true;
}

function refreshUsers() {
    router.get(
        route("users.index"),
        {
            search: search.value,
            status: statusFilter.value,
        },
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
            only: ["users"],
        }
    );
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold">User Management</h2>
                <button
                    @click="showCreate = true"
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                >
                    + Create User
                </button>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <div class="bg-white p-4 rounded shadow flex gap-4">
                <input
                    v-model="search"
                    placeholder="Search"
                    class="input w-full"
                    @keyup.enter="refreshUsers"
                >

                <select
                    v-model="statusFilter"
                    class="input w-40"
                    @change="refreshUsers"
                >
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Suspended</option>
                </select>

                <button class="btn" @click="refreshUsers">Apply</button>
            </div>

            <div class="bg-white shadow rounded overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="th">Identity</th>
                            <th class="th">Name</th>
                            <th class="th">Email</th>
                            <th class="th">Departments / Roles</th>
                            <th class="th">Branches</th>
                            <th class="th text-center">Status</th>
                            <th class="th text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="td">{{ user.identity_no }}</td>
                            <td class="td font-semibold">{{ user.name }}</td>
                            <td class="td">{{ user.email }}</td>
                            <td class="td">
                                <div v-for="a in user.assignments" :key="`${user.id}-${a.department_id}-${a.role_id}`">
                                    <strong>{{ a.department }}</strong> - {{ a.role }}
                                </div>
                            </td>
                            <td class="td">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="b in (user.branches ?? [])"
                                        :key="b.id"
                                        class="px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-700 uppercase"
                                    >
                                        {{ b.slug }}
                                    </span>
                                    <span v-if="!(user.branches ?? []).length" class="text-xs text-gray-400">
                                        -
                                    </span>
                                </div>
                            </td>
                            <td class="td text-center">
                                <span :class="user.status ? 'ok' : 'bad'" class="badge">
                                    {{ user.status ? "Active" : "Suspended" }}
                                </span>
                            </td>
                            <td class="td text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button
                                        @click="openEdit(user)"
                                        class="action text-indigo-600"
                                        title="Edit User"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="openBranches(user)"
                                        class="action text-emerald-700"
                                        title="Manage Branches"
                                    >
                                        Branches
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex gap-1">
                <Link
                    v-for="l in users.links"
                    :key="l.label"
                    :href="l.url ?? ''"
                    v-html="l.label"
                    class="px-3 py-1 border rounded"
                    :class="{ 'bg-indigo-600 text-white': l.active }"
                />
            </div>
        </div>

        <CreateUserModal
            v-model="showCreate"
            :departments="departments"
            :roles-by-dept="rolesByDept"
            @created="refreshUsers"
        />

        <EditUserModal
            v-model="showEdit"
            :user="selectedUser"
            :departments="departments"
            :roles-by-dept="rolesByDept"
            @updated="refreshUsers"
        />

        <ManageBranchesModal
            v-model="showBranches"
            :user="selectedUser"
            :branches="branches"
            @updated="refreshUsers"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.input { @apply border rounded px-3 py-2; }
.btn { @apply px-4 py-2 bg-gray-200 rounded hover:bg-gray-300; }
.th { @apply px-4 py-3 text-left text-xs uppercase text-gray-500; }
.td { @apply px-4 py-3 text-sm; }
.badge { @apply px-2 py-1 text-xs rounded; }
.ok { @apply bg-green-100 text-green-700; }
.bad { @apply bg-red-100 text-red-700; }
.action { @apply hover:bg-gray-100 px-2 py-1 rounded; }
</style>
