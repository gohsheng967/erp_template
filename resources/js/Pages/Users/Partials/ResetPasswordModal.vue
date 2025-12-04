<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
    user: Object,
    rolesByDept: Object,   // { dept_id: [{id, name}] }
    departments: Array     // [{id, name}]
})

const emit = defineEmits(['close'])

// ---------- STEP 1: Build initial rows from user.assignments ----------
// Expected user.assignments = [
//   { department_id: 1, department: "HR", role_id: 4, role: "Manager" },
//   ...
// ]

const rows = ref([])

onMounted(() => {
    rows.value = props.user.assignments.length
        ? props.user.assignments.map(a => ({
            department_id: a.department_id,
            role_id: a.role_id,
            availableRoles: props.rolesByDept[a.department_id] || []
        }))
        : [{
            department_id: "",
            role_id: "",
            availableRoles: []
        }]
})

// Prevent duplicate department selection
function usedDepartmentIds() {
    return rows.value.map(r => r.department_id).filter(Boolean)
}

// Add Row
function addRow() {
    rows.value.push({
        department_id: "",
        role_id: "",
        availableRoles: []
    })
}

// Remove Row
function removeRow(index) {
    if (rows.value.length === 1) return
    rows.value.splice(index, 1)
}

// Update roles when department changes
watch(rows, (newRows) => {
    newRows.forEach(row => {
        if (row.department_id) {
            row.availableRoles = props.rolesByDept[row.department_id] || []

            // Reset role if invalid
            if (!row.availableRoles.find(r => r.id === row.role_id)) {
                row.role_id = ""
            }
        }
    })
}, { deep: true })

// ---------- STEP 2: Form ----------
const form = useForm({
    name: props.user.name,
    email: props.user.email,
    status: props.user.status,
    department_roles: rows
})

// ---------- STEP 3: Submit ----------
function submit() {
    form.department_roles = rows.value

    router.patch(route('users.update', props.user.id), form, {
        preserveScroll: true,
        onSuccess: () => emit('close')
    })
}
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-[480px] max-h-screen overflow-auto p-6">

            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Edit User</h2>
                <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700 text-xl">×</button>
            </div>

            <!-- Name -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Name</label>
                <input v-model="form.name" type="text"
                       class="w-full border rounded px-3 py-2 text-sm">
                <div v-if="form.errors.name" class="text-red-600 text-xs mt-1">
                    {{ form.errors.name }}
                </div>
            </div>

            <!-- Email -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Email</label>
                <input v-model="form.email" type="email"
                       class="w-full border rounded px-3 py-2 text-sm">
                <div v-if="form.errors.email" class="text-red-600 text-xs mt-1">
                    {{ form.errors.email }}
                </div>
            </div>

            <!-- Status -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Status</label>
                <select v-model="form.status"
                        class="w-full border rounded px-3 py-2 text-sm">
                    <option value="1">Active</option>
                    <option value="0">Suspended</option>
                </select>
            </div>

            <!-- Department-Role Section -->
            <h3 class="font-semibold text-sm mb-2 mt-6">Department & Role</h3>

            <div class="space-y-4">
                <div v-for="(row, index) in rows" :key="index"
                     class="border p-3 rounded-md bg-gray-50">

                    <div class="grid grid-cols-1 gap-3">

                        <!-- Department -->
                        <div>
                            <label class="text-xs font-medium">Department</label>
                            <select v-model="row.department_id"
                                    class="w-full border rounded px-2 py-1 text-sm"
                            >
                                <option value="">Select Department</option>
                                <option
                                    v-for="d in departments"
                                    :key="d.id"
                                    :disabled="usedDepartmentIds().includes(d.id) && row.department_id !== d.id"
                                    :value="d.id"
                                >
                                    {{ d.name }}
                                </option>
                            </select>

                            <div v-if="form.errors[`department_roles.${index}.department_id`]"
                                 class="text-red-600 text-xs mt-1">
                                {{ form.errors[`department_roles.${index}.department_id`] }}
                            </div>
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="text-xs font-medium">Role</label>
                            <select v-model="row.role_id"
                                    class="w-full border rounded px-2 py-1 text-sm"
                                    :disabled="!row.department_id"
                            >
                                <option value="">Select Role</option>
                                <option
                                    v-for="r in row.availableRoles"
                                    :key="r.id"
                                    :value="r.id"
                                >
                                    {{ r.name }}
                                </option>
                            </select>

                            <div v-if="form.errors[`department_roles.${index}.role_id`]"
                                 class="text-red-600 text-xs mt-1">
                                {{ form.errors[`department_roles.${index}.role_id`] }}
                            </div>
                        </div>
                    </div>

                    <!-- Remove row -->
                    <div class="flex justify-end mt-2">
                        <button @click="removeRow(index)"
                                class="text-xs text-red-600 hover:underline"
                                :disabled="rows.length === 1">
                            Remove
                        </button>
                    </div>

                </div>
            </div>

            <button @click="addRow"
                    class="text-sm mt-3 px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">
                + Add Department Role
            </button>

            <!-- Footer -->
            <div class="flex justify-end space-x-3 mt-6">
                <button @click="$emit('close')"
                        class="px-4 py-2 text-sm bg-gray-200 rounded hover:bg-gray-300">
                    Cancel
                </button>

                <button @click="submit"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">
                    Save Changes
                </button>
            </div>

        </div>
    </div>
</template>
