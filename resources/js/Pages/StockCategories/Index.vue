<script setup>
import { ref, inject } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue"
import DeleteConfirmation from "@/Components/DeleteConfirmation.vue"

const props = defineProps({
    categories: Array,
})

const toast = inject("toast", null)

const createForm = useForm({
    name: "",
})

const editForm = useForm({
    id: null,
    name: "",
})

const editingId = ref(null)
const deletingCategory = ref(null)
const showDelete = ref(false)

function submitCreate() {
    createForm.post(route("stock-categories.store"), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Stock category created successfully.", "success")
            createForm.reset()
        },
    })
}

function startEdit(category) {
    editingId.value = category.id
    editForm.id = category.id
    editForm.name = category.name
    editForm.clearErrors()
}

function cancelEdit() {
    editingId.value = null
    editForm.reset()
}

function submitEdit() {
    editForm.patch(route("stock-categories.update", editForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Stock category updated successfully.", "success")
            cancelEdit()
        },
    })
}

function confirmDelete(category) {
    deletingCategory.value = category
    showDelete.value = true
}

function deleteCategory() {
    if (!deletingCategory.value) return

    router.delete(route("stock-categories.destroy", deletingCategory.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Stock category deleted successfully.", "success")
            showDelete.value = false
            deletingCategory.value = null
        },
        onError: (errors) => {
            if (errors?.delete) {
                toast?.value?.show(errors.delete, "error")
            }
        },
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Stock Categories</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-3 font-semibold">Add Stock Category</h3>

                    <form @submit.prevent="submitCreate" class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <input
                                v-model="createForm.name"
                                placeholder="Category name (e.g. Electrical)"
                                class="input w-full"
                            />
                            <div v-if="createForm.errors.name" class="mt-1 text-xs text-red-500">
                                {{ createForm.errors.name }}
                            </div>
                        </div>

                        <button
                            class="rounded bg-blue-600 px-4 py-2 text-white shadow"
                            :disabled="createForm.processing"
                        >
                            Add
                        </button>
                    </form>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b bg-gray-100">
                                <th class="p-3 text-left">Name</th>
                                <th class="p-3 text-right">Used</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="category in props.categories" :key="category.id" class="border-b">
                                <template v-if="editingId === category.id">
                                    <td class="p-3">
                                        <input v-model="editForm.name" class="input w-full" />
                                        <div v-if="editForm.errors.name" class="mt-1 text-xs text-red-500">
                                            {{ editForm.errors.name }}
                                        </div>
                                    </td>

                                    <td class="p-3 text-right tabular-nums">
                                        {{ category.movements_count }}
                                    </td>

                                    <td class="p-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                class="rounded bg-blue-600 px-3 py-1.5 text-sm text-white"
                                                title="Save"
                                                @click="submitEdit"
                                            >
                                                <i class="mdi mdi-content-save-outline text-base"></i>
                                            </button>
                                            <button
                                                class="rounded bg-gray-200 px-3 py-1.5 text-sm"
                                                title="Cancel"
                                                @click="cancelEdit"
                                            >
                                                <i class="mdi mdi-close text-base"></i>
                                            </button>
                                        </div>
                                    </td>
                                </template>

                                <template v-else>
                                    <td class="p-3">{{ category.name }}</td>
                                    <td class="p-3 text-right tabular-nums">{{ category.movements_count }}</td>
                                    <td class="p-3 text-right">
                                        <div class="flex justify-end gap-3">
                                            <button
                                                class="text-blue-600 hover:text-blue-800"
                                                title="Edit"
                                                @click="startEdit(category)"
                                            >
                                                <i class="mdi mdi-pencil-outline text-lg"></i>
                                            </button>
                                            <button
                                                class="text-red-600 hover:text-red-800"
                                                title="Delete"
                                                @click="confirmDelete(category)"
                                            >
                                                <i class="mdi mdi-delete-outline text-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </template>
                            </tr>

                            <tr v-if="!props.categories?.length">
                                <td colspan="3" class="p-6 text-center text-gray-500">
                                    No stock categories found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <DeleteConfirmation
        v-if="showDelete"
        title="Delete Stock Category"
        message="Are you sure you want to delete this stock category?"
        @confirm="deleteCategory"
        @close="showDelete = false"
    />
</template>

<style scoped>
.input { @apply border p-2 rounded w-full; }
</style>
