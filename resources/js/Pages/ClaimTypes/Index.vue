<script setup>
import { ref, inject } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue"
import DeleteConfirmation from "@/Components/DeleteConfirmation.vue"

const props = defineProps({
    types: Array,
})

const toast = inject("toast", null)

const createForm = useForm({
    name: "",
    code: "",
})

const editForm = useForm({
    id: null,
    name: "",
    code: "",
})

const editingId = ref(null)
const deletingType = ref(null)
const showDelete = ref(false)

function submitCreate() {
    createForm.post(route("claim-types.store"), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Claim type created successfully.", "success")
            createForm.reset()
        },
    })
}

function startEdit(type) {
    editingId.value = type.id
    editForm.id = type.id
    editForm.name = type.name
    editForm.code = type.code
    editForm.clearErrors()
}

function cancelEdit() {
    editingId.value = null
    editForm.reset()
}

function submitEdit() {
    editForm.patch(route("claim-types.update", editForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Claim type updated successfully.", "success")
            cancelEdit()
        },
    })
}

function confirmDelete(type) {
    deletingType.value = type
    showDelete.value = true
}

function deleteType() {
    if (!deletingType.value) return

    router.delete(route("claim-types.destroy", deletingType.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Claim type deleted successfully.", "success")
            showDelete.value = false
            deletingType.value = null
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
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">Claim Types</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">

                <div class="bg-white overflow-hidden shadow rounded-lg p-6">
                    <h3 class="font-semibold mb-3">Add Claim Type</h3>

                    <form @submit.prevent="submitCreate" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <input
                                v-model="createForm.name"
                                placeholder="Display Name"
                                class="input w-full"
                            />
                            <div v-if="createForm.errors.name" class="text-xs text-red-500 mt-1">
                                {{ createForm.errors.name }}
                            </div>
                        </div>

                        <div>
                            <input
                                v-model="createForm.code"
                                placeholder="Code (e.g., food_beverage)"
                                class="input w-full"
                            />
                            <div v-if="createForm.errors.code" class="text-xs text-red-500 mt-1">
                                {{ createForm.errors.code }}
                            </div>
                        </div>

                        <button
                            class="px-4 py-2 bg-blue-600 text-white rounded shadow"
                            :disabled="createForm.processing"
                        >
                            Add
                        </button>
                    </form>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b bg-gray-100">
                                <th class="p-3 text-left">Name</th>
                                <th class="p-3 text-left">Code</th>
                                <th class="p-3 text-right">Used</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="type in props.types" :key="type.id" class="border-b">
                                <template v-if="editingId === type.id">
                                    <td class="p-3">
                                        <input v-model="editForm.name" class="input w-full" />
                                        <div v-if="editForm.errors.name" class="text-xs text-red-500 mt-1">
                                            {{ editForm.errors.name }}
                                        </div>
                                    </td>

                                    <td class="p-3">
                                        <input
                                            v-model="editForm.code"
                                            class="input w-full"
                                            :disabled="type.claim_items_count > 0"
                                        />
                                        <div v-if="type.claim_items_count > 0" class="text-xs text-gray-500 mt-1">
                                            Code is locked because it is used in claims.
                                        </div>
                                        <div v-if="editForm.errors.code" class="text-xs text-red-500 mt-1">
                                            {{ editForm.errors.code }}
                                        </div>
                                    </td>

                                    <td class="p-3 text-right tabular-nums">
                                        {{ type.claim_items_count }}
                                    </td>

                                <td class="p-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded"
                                            title="Save"
                                            @click="submitEdit"
                                        >
                                            <i class="mdi mdi-content-save-outline text-base"></i>
                                        </button>
                                        <button
                                            class="px-3 py-1.5 text-sm bg-gray-200 rounded"
                                            title="Cancel"
                                            @click="cancelEdit"
                                        >
                                            <i class="mdi mdi-close text-base"></i>
                                        </button>
                                    </div>
                                </td>
                            </template>

                            <template v-else>
                                <td class="p-3">{{ type.name }}</td>
                                <td class="p-3 font-mono text-xs">{{ type.code }}</td>
                                <td class="p-3 text-right tabular-nums">{{ type.claim_items_count }}</td>
                                <td class="p-3 text-right">
                                    <div class="flex justify-end gap-3">
                                        <button
                                            class="text-blue-600 hover:text-blue-800"
                                            title="Edit"
                                            @click="startEdit(type)"
                                        >
                                            <i class="mdi mdi-pencil-outline text-lg"></i>
                                        </button>
                                        <button
                                            class="text-red-600 hover:text-red-800"
                                            title="Delete"
                                            @click="confirmDelete(type)"
                                        >
                                            <i class="mdi mdi-delete-outline text-lg"></i>
                                        </button>
                                    </div>
                                </td>
                            </template>
                            </tr>

                            <tr v-if="!props.types?.length">
                                <td colspan="4" class="p-6 text-center text-gray-500">
                                    No claim types found.
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
        title="Delete Claim Type"
        message="Are you sure you want to delete this claim type?"
        @confirm="deleteType"
        @close="showDelete = false"
    />
</template>

<style scoped>
.input { @apply border p-2 rounded w-full; }
</style>
