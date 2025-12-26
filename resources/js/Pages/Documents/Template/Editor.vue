<script setup>
import { useForm } from "@inertiajs/vue3"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue"

const props = defineProps({
    template: Object,
    type: Object
})

const form = useForm({
    html_template: props.template.html_template,
    css: props.template.css
})

function save() {
    form.post(route("documents.template.update", props.type.code), { preserveScroll: true })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                Template Editor — {{ props.type.name }}
            </h2>
        </template>

        <div class="p-6 max-w-6xl mx-auto">

            <div class="grid grid-cols-2 gap-6">
                <!-- HTML -->
                <div>
                    <h3 class="font-semibold mb-2">HTML Template</h3>
                    <textarea v-model="form.html_template" class="editor"></textarea>
                </div>

                <!-- CSS -->
                <div>
                    <h3 class="font-semibold mb-2">CSS Styles</h3>
                    <textarea v-model="form.css" class="editor"></textarea>
                </div>
            </div>

            <button
                @click="save"
                class="mt-6 px-4 py-2 bg-green-600 text-white rounded"
            >
                Save Template
            </button>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.editor {
    @apply border w-full h-96 p-3 font-mono text-sm rounded;
}
</style>
