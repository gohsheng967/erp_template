<script setup>
import { useForm } from "@inertiajs/vue3"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue"

const props = defineProps({
    document: Object
})

const form = useForm({
    fields: JSON.parse(JSON.stringify(props.document.fields))
})

function addField() {
    form.fields.push({
        id: null,
        label: "",
        field_name: "",
        field_type: "text",
        is_required: false
    })
}

function save() {
    form.post(route("documents.fields.save", props.document.code), { preserveScroll: true })
}

function remove(i) {
    form.fields.splice(i, 1)
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                Field Builder — {{ props.document.name }}
            </h2>
        </template>

        <div class="p-6 max-w-4xl mx-auto">
            <button @click="addField" class="mb-4 px-3 py-2 bg-blue-600 text-white rounded">
                + Add Field
            </button>

            <div class="space-y-4">
                <div
                    v-for="(f, i) in form.fields"
                    :key="i"
                    class="border p-4 bg-white rounded shadow"
                >
                    <div class="grid grid-cols-3 gap-3">
                        <input v-model="f.label" class="input" placeholder="Field Label" />
                        <input v-model="f.field_name" class="input" placeholder="Key (client_name)" />

                        <select v-model="f.field_type" class="input">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                        </select>
                    </div>

                    <div class="flex justify-between mt-3">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="f.is_required" /> Required
                        </label>

                        <button @click="remove(i)" class="text-red-600">Delete</button>
                    </div>
                </div>
            </div>

            <button
                @click="save"
                class="mt-6 px-4 py-2 bg-green-600 text-white rounded"
                :disabled="form.processing"
            >
                Save Fields
            </button>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.input { @apply border p-2 rounded w-full; }
</style>
