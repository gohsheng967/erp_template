<script setup>
import { inject } from "vue";
import { useForm } from "@inertiajs/vue3";

const toast = inject("toast", null);

const SUPER_ADMIN_ROLE_ID = 1;

const props = defineProps({
    modelValue: Boolean,
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
    department_roles: [
        {
            department_id: null,
            role_id: SUPER_ADMIN_ROLE_ID,
        },
    ],
});

function submit() {
    form.post(route("users.store"), {
        preserveScroll: true,

        onSuccess: () => {
            toast?.show?.(
                "Super Admin created successfully",
                "success"
            );

            emit("update:modelValue", false);
            emit("created");
            form.reset();
        },

        onError: () => {
            toast?.show?.(
                "Failed to create Super Admin. Please check the form.",
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

            <h2 class="text-xl font-semibold mb-1">Create Super Admin</h2>
            <p class="text-sm text-gray-500 mb-4">
                This user will have full system access.
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

                <!-- ROLE (LOCKED) -->
                <div class="mb-6">
                    <label class="label">Role</label>
                    <input
                        value="Super Admin"
                        disabled
                        class="input bg-gray-100 cursor-not-allowed font-semibold"
                    />
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
