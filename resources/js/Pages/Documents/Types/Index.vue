<script setup>
import { ref } from "vue"
import { Link, useForm } from "@inertiajs/vue3"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue"

const props = defineProps({
    types: Array
})

const form = useForm({
    name: "",
    code: ""
})

function submit() {
    form.post(route("documents.types.store"), {
        preserveScroll: true,
        onSuccess: () => form.reset()
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Document Types</h2>
        </template>

        <div class="p-6 max-w-4xl mx-auto">

            <!-- Add Type -->
            <div class="bg-white shadow p-4 rounded mb-6">
                <h2 class="font-semibold mb-3">Add New Document Type</h2>

                <form @submit.prevent="submit" class="grid grid-cols-3 gap-3">
                    <input v-model="form.name" placeholder="Display Name" class="input" />
                    <input v-model="form.code" placeholder="Code (e.g., INVOICE)" class="input uppercase" />

                    <button class="px-4 bg-blue-600 text-white rounded"
                            :disabled="form.processing">
                        Add
                    </button>
                </form>
            </div>

            <!-- List -->
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b bg-gray-100">
                        <th class="p-2 text-left">Name</th>
                        <th class="p-2 text-left">Code</th>
                        <th class="p-2 text-left">Fields</th>
                        <th class="p-2 text-right"></th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="t in props.types" :key="t.id" class="border-b">
                        <td class="p-2">{{ t.name }}</td>
                        <td class="p-2">{{ t.code }}</td>
                        <td class="p-2">{{ t.fields_count }}</td>

                        <td class="p-2 text-right">
                            <Link :href="route('documents.fields', t.code)" class="text-blue-600 mr-3">Fields</Link>
                            <Link :href="route('documents.template.edit', t.code)" class="text-green-600">Template</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.input { @apply border p-2 rounded w-full; }
</style>
