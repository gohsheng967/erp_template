<script setup>
import { ref } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateUserModal from "./Partials/CreateUserModal.vue";
import EditUserModal from "./Partials/EditUserModal.vue";

const page = usePage();

const users = page.props.users;
const filters = page.props.filters;
const departments = page.props.departments;
const rolesByDept = page.props.rolesByDept;

/* =====================
   UI STATE
===================== */
const showCreate = ref(false);
const showEdit = ref(false);
const selectedUser = ref(null);

const search = ref(filters.search ?? "");
const statusFilter = ref(filters.status ?? "");

/* =====================
   ACTIONS
===================== */
function openEdit(user) {
    selectedUser.value = user;
    showEdit.value = true;
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

        <!-- HEADER -->
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

            <!-- FILTERS -->
            <div class="bg-white p-4 rounded shadow flex gap-4">
                <input
                    v-model="search"
                    placeholder="Search"
                    class="input w-full"
                    @keyup.enter="refreshUsers"
                />

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

            <!-- TABLE -->
            <div class="bg-white shadow rounded overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="th">Identity</th>
                            <th class="th">Name</th>
                            <th class="th">Email</th>
                            <th class="th">Departments / Roles</th>
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
                                <div v-for="a in user.assignments">
                                    <strong>{{ a.department }}</strong> — {{ a.role }}
                                </div>
                            </td>
                            <td class="td text-center">
                                <span
                                    :class="user.status ? 'ok' : 'bad'"
                                    class="badge"
                                >
                                    {{ user.status ? "Active" : "Suspended" }}
                                </span>
                            </td>
                            <td class="td text-center">
                                <button
                                    @click="openEdit(user)"
                                    class="action text-indigo-600"
                                >
                                    ✎
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div class="flex gap-1">
                <Link
                    v-for="l in users.links"
                    :href="l.url ?? ''"
                    v-html="l.label"
                    class="px-3 py-1 border rounded"
                    :class="{ 'bg-indigo-600 text-white': l.active }"
                />
            </div>
        </div>

        <!-- MODALS -->
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
