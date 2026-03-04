<script setup>
import DangerButton from "@/Components/DangerButton.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { computed, inject, reactive } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";

const page = usePage();
const toast = inject("toast", null);

const branches = computed(() => page.props.branches ?? []);
const isSuperAdmin = computed(() => {
    const user = page.props.auth?.user?.data;
    const departments = user?.departments ?? [];
    return departments.some((d) =>
        ["superadmin", "super admin"].includes(String(d.name ?? "").toLowerCase())
    );
});

const createForm = useForm({
    name: "",
    slug: "",
    is_active: true,
});

const editState = reactive({
    id: null,
    name: "",
    slug: "",
    is_active: true,
});

function submitCreate() {
    createForm.post(route("company.branches.store"), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.is_active = true;
            toast?.value?.show("Branch added!", "success");
            router.reload({ preserveScroll: true, only: ["branches"] });
        },
    });
}

function startEdit(branch) {
    editState.id = branch.id;
    editState.name = branch.name;
    editState.slug = branch.slug;
    editState.is_active = !!branch.is_active;
}

function cancelEdit() {
    editState.id = null;
    editState.name = "";
    editState.slug = "";
    editState.is_active = true;
}

function saveEdit(branch) {
    router.patch(
        route("company.branches.update", branch.id),
        {
            name: editState.name,
            slug: editState.slug,
            is_active: editState.is_active,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Branch updated!", "success");
                cancelEdit();
                router.reload({ preserveScroll: true, only: ["branches"] });
            },
        }
    );
}

function removeBranch(branch) {
    if (!confirm(`Delete branch "${branch.name}"?`)) return;

    router.delete(route("company.branches.destroy", branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Branch deleted!", "success");
            router.reload({ preserveScroll: true, only: ["branches"] });
        },
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-gray-900">Branches</h2>
            <p class="mt-1 text-sm text-gray-600">
                Add, edit and delete branches. Deletion is allowed only if branch is unused.
            </p>
        </header>

        <div v-if="!isSuperAdmin" class="mt-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-500">
            Only Superadmin can manage branches.
        </div>

        <div v-else class="mt-6 space-y-6">
            <form @submit.prevent="submitCreate" class="rounded-lg border border-gray-200 bg-gray-50/60 p-4">
                <div class="mb-3 text-sm font-medium text-gray-700">Add New Branch</div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Branch Name</label>
                        <input v-model="createForm.name" class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <InputError :message="createForm.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Slug</label>
                        <input v-model="createForm.slug" class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="kl" />
                        <InputError :message="createForm.errors.slug" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Status</label>
                        <select v-model="createForm.is_active" class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option :value="true">Active</option>
                            <option :value="false">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <PrimaryButton :disabled="createForm.processing">Add Branch</PrimaryButton>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="branch in branches" :key="branch.id">
                            <td class="px-4 py-3">
                                <input
                                    v-if="editState.id === branch.id"
                                    v-model="editState.name"
                                    class="w-full rounded-md border-gray-300 px-2 py-1 text-sm"
                                />
                                <span v-else>{{ branch.name }}</span>
                            </td>
                            <td class="px-4 py-3 uppercase">
                                <input
                                    v-if="editState.id === branch.id"
                                    v-model="editState.slug"
                                    class="w-full rounded-md border-gray-300 px-2 py-1 text-sm"
                                />
                                <span v-else>{{ branch.slug }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="editState.id === branch.id">
                                    <select v-model="editState.is_active" class="rounded-md border-gray-300 px-2 py-1 text-sm">
                                        <option :value="true">Active</option>
                                        <option :value="false">Inactive</option>
                                    </select>
                                </template>
                                <template v-else>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="branch.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                                    >
                                        {{ branch.is_active ? "Active" : "Inactive" }}
                                    </span>
                                </template>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <template v-if="editState.id === branch.id">
                                    <SecondaryButton class="text-xs" @click="saveEdit(branch)">Save</SecondaryButton>
                                    <SecondaryButton class="text-xs" @click="cancelEdit">Cancel</SecondaryButton>
                                </template>
                                <template v-else>
                                    <SecondaryButton class="text-xs" @click="startEdit(branch)">Edit</SecondaryButton>
                                    <DangerButton class="text-xs" @click="removeBranch(branch)">Delete</DangerButton>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
