<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

const props = defineProps({
    modelValue: Boolean,
    user: Object,
    branches: Array,
});

const emit = defineEmits(["update:modelValue", "updated"]);

const isProtected = computed(() => !!props.user?.is_protected);

const form = useForm({
    branch_ids: [],
    active_branch_id: "",
});

watch(
    () => props.user,
    (u) => {
        if (!u) return;
        form.branch_ids = (u.branches ?? []).map((b) => b.id);
        form.active_branch_id = u.active_branch_id ?? "";
    },
    { immediate: true }
);

watch(
    () => form.branch_ids,
    (ids) => {
        if (!ids.length) {
            form.active_branch_id = "";
            return;
        }

        const active = Number(form.active_branch_id);
        if (!ids.map(Number).includes(active)) {
            form.active_branch_id = ids[0];
        }
    },
    { deep: true }
);

function close() {
    emit("update:modelValue", false);
}

function submit() {
    if (isProtected.value) return;

    form.patch(route("users.branches.update", props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit("updated");
            close();
        },
    });
}
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40"
    >
        <div class="bg-white w-full max-w-xl rounded-lg shadow p-6 relative">
            <button
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-700"
                @click="close"
            >
                x
            </button>

            <h2 class="text-xl font-semibold mb-1">
                Manage Branches
            </h2>
            <p class="text-sm text-gray-500 mb-4">
                {{ user?.name }} ({{ user?.email }})
            </p>

            <p
                v-if="isProtected"
                class="text-xs text-gray-500 mb-4"
            >
                Super Admin accounts cannot be modified.
            </p>

            <div class="space-y-3 max-h-56 overflow-y-auto border rounded p-3">
                <label
                    v-for="branch in branches"
                    :key="branch.id"
                    class="flex items-center gap-3 text-sm"
                >
                    <input
                        v-model="form.branch_ids"
                        :value="branch.id"
                        type="checkbox"
                        :disabled="isProtected"
                    >
                    <span class="font-medium">{{ branch.name }}</span>
                    <span class="text-xs text-gray-500 uppercase">({{ branch.slug }})</span>
                </label>
            </div>

            <div class="mt-4">
                <label class="text-sm font-medium text-gray-700">Active Branch</label>
                <select
                    v-model="form.active_branch_id"
                    class="mt-1 w-full border rounded px-3 py-2"
                    :disabled="isProtected || !form.branch_ids.length"
                >
                    <option value="" disabled>Select active branch</option>
                    <option
                        v-for="branch in branches.filter(b => form.branch_ids.map(Number).includes(Number(b.id)))"
                        :key="branch.id"
                        :value="branch.id"
                    >
                        {{ branch.name }} ({{ branch.slug }})
                    </option>
                </select>
                <div v-if="form.errors.active_branch_id" class="text-red-600 text-xs mt-1">
                    {{ form.errors.active_branch_id }}
                </div>
                <div v-if="form.errors.branch_ids" class="text-red-600 text-xs mt-1">
                    {{ form.errors.branch_ids }}
                </div>
            </div>

            <div class="mt-6 text-right">
                <button
                    @click="submit"
                    :disabled="form.processing || isProtected || !form.branch_ids.length || !form.active_branch_id"
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:bg-gray-400"
                >
                    Save Branches
                </button>
            </div>
        </div>
    </div>
</template>

