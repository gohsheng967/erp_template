<script setup>
import { computed, inject, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

const toast = inject("toast", null);

const props = defineProps({
    modelValue: Boolean,
    departments: {
        type: Array,
        default: () => [],
    },
    rolesByDept: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits([
    "update:modelValue",
    "created",
]);

const form = useForm({
    identity_no: "",
    name: "",
    email: "",
    status: 1,
    is_superadmin: false,
    is_general_manager: false,
    department_roles: [
        {
            department_id: "",
            role_id: "",
        },
    ],
});

const showAssignment = computed(() => !form.is_superadmin && !form.is_general_manager);

watch(
    () => form.is_superadmin,
    (value) => {
        if (value) {
            form.is_general_manager = false;
        }
    }
);

watch(
    () => form.is_general_manager,
    (value) => {
        if (value) {
            form.is_superadmin = false;
        }
    }
);

function addRow() {
    form.department_roles.push({
        department_id: "",
        role_id: "",
    });
}

function removeRow(index) {
    form.department_roles.splice(index, 1);
}

function submit() {
    form
        .transform((data) => ({
            ...data,
            department_roles: (data.is_superadmin || data.is_general_manager)
                ? []
                : (data.department_roles ?? []).filter(
                    (row) => row?.department_id || row?.role_id
                ),
        }))
        .post(route("users.store"), {
            preserveScroll: true,

            onSuccess: (page) => {
                const successMessage = page?.props?.flash?.success
                    ?? "User created successfully.";
                toast?.value?.show(successMessage, "success");

                emit("update:modelValue", false);
                emit("created");
                form.reset();
                form.status = 1;
                form.is_superadmin = false;
                form.is_general_manager = false;
                form.department_roles = [{ department_id: "", role_id: "" }];
            },

            onError: () => {
                toast?.value?.show(
                    "Failed to create user. Please check the form.",
                    "error"
                );
            },
        });
}
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40"
    >
        <div class="bg-white w-full max-w-lg rounded-lg shadow p-6 relative">

            <!-- CLOSE -->
            <button
                type="button"
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-700"
                @click="emit('update:modelValue', false)"
            >
                ✕
            </button>

            <h2 class="text-xl font-semibold mb-1">Create User</h2>
            <p class="text-sm text-gray-500 mb-4">
                Set basic account info and access level.
            </p>

            <!-- ✅ FORM START -->
            <form @submit.prevent="submit">

                <!-- IDENTITY -->
                <div class="mb-4">
                    <label class="label">Identity No</label>
                    <input v-model="form.identity_no" class="input" />
                    <div v-if="form.errors.identity_no" class="error">
                        {{ form.errors.identity_no }}
                    </div>
                </div>

                <!-- NAME -->
                <div class="mb-4">
                    <label class="label">Name</label>
                    <input v-model="form.name" class="input" />
                    <div v-if="form.errors.name" class="error">
                        {{ form.errors.name }}
                    </div>
                </div>

                <!-- EMAIL -->
                <div class="mb-4">
                    <label class="label">Email</label>
                    <input v-model="form.email" type="email" class="input" />
                    <div v-if="form.errors.email" class="error">
                        {{ form.errors.email }}
                    </div>
                </div>

                <!-- STATUS -->
                <div class="mb-4">
                    <label class="label">Status</label>
                    <select v-model="form.status" class="input">
                        <option :value="1">Active</option>
                        <option :value="0">Suspended</option>
                    </select>
                </div>

                <!-- SUPERADMIN TOGGLE -->
                <div class="mb-6">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input
                            v-model="form.is_superadmin"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            :disabled="form.is_general_manager"
                        />
                        Is superadmin account
                    </label>
                </div>

                <!-- GENERAL MANAGER TOGGLE -->
                <div class="mb-6">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input
                            v-model="form.is_general_manager"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            :disabled="form.is_superadmin"
                        />
                        Is general manager account
                    </label>
                </div>

                <div v-if="showAssignment" class="mb-6">
                    <label class="label mb-2 block">Department & Role</label>

                    <div
                        v-for="(row, index) in form.department_roles"
                        :key="index"
                        class="flex items-center gap-3 mb-3"
                    >
                        <select v-model="row.department_id" class="input w-1/2">
                            <option value="">Department</option>
                            <option
                                v-for="d in props.departments"
                                :key="d.id"
                                :value="d.id"
                            >
                                {{ d.name }}
                            </option>
                        </select>

                        <select v-model="row.role_id" class="input w-1/2">
                            <option value="">Role</option>
                            <option
                                v-for="r in props.rolesByDept[row.department_id] ?? []"
                                :key="r.id"
                                :value="r.id"
                            >
                                {{ r.name }}
                            </option>
                        </select>

                        <button
                            v-if="form.department_roles.length > 1"
                            type="button"
                            class="text-red-600"
                            @click="removeRow(index)"
                        >
                            ×
                        </button>
                    </div>

                    <button
                        type="button"
                        class="text-sm text-indigo-600"
                        @click="addRow"
                    >
                        + Add Department & Role
                    </button>

                    <div v-if="form.errors.department_roles" class="error mt-2">
                        {{ form.errors.department_roles }}
                    </div>
                </div>

                <!-- ACTION -->
                <div class="text-right">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:bg-gray-400"
                    >
                        {{ form.processing ? "Creating..." : "Create" }}
                    </button>
                </div>

            </form>
            <!-- ✅ FORM END -->

        </div>
    </div>
</template>


<style scoped>
.label {
    @apply text-sm font-medium text-gray-700;
}
.input {
    @apply w-full border rounded px-3 py-2;
}
.error {
    @apply text-red-600 text-xs mt-1;
}
</style>
