<script setup>
import { ref } from "vue";
import { useForm, Link } from "@inertiajs/vue3";

const props = defineProps({
    project: Object,
    documents: Array,     // NEW + OLD format supported
    categories: Array,
});

// Upload form
const uploadForm = useForm({
    file: null,
    category_id: "",
});

// Filter
const selectedCategory = ref("");

// Handle file select
function onFileChange(e) {
    uploadForm.file = e.target.files[0];
}

// Upload handler
function uploadDocument() {
    uploadForm.post(
        route("projects.documents.upload", props.project.id),
        { onSuccess: () => uploadForm.reset() }
    );
}

// Helper: normalize document fields for both legacy + new formats
function normalizeDoc(doc) {
    return {
        id: doc.id,
        filename: doc.filename ?? doc.name ?? "Unnamed File",
        filepath: doc.filepath ?? doc.file_path ?? null,
        category_id: doc.category_id ?? null,
        category_name: doc.category?.name ?? "-",
        user_name: doc.user?.name ?? "System",
        created_at: doc.created_at ?? "-",
    };
}
</script>

<template>
    <div class="space-y-8">

        <!-- DEBUG (optional) -->
        <!-- <pre>{{ documents }}</pre> -->

        <!-- ========================== -->
        <!-- UPLOAD DOCUMENT AREA       -->
        <!-- ========================== -->
        <div class="bg-white shadow-md rounded-xl p-6 border">
            <h3 class="text-lg font-semibold mb-4">Upload Document</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <!-- FILE -->
                <div>
                    <label class="block mb-1 text-sm font-medium">File</label>
                    <input type="file" @change="onFileChange" class="border rounded-md px-3 py-2 w-full">
                </div>

                <!-- CATEGORY -->
                <div>
                    <label class="block mb-1 text-sm font-medium">Category</label>
                    <select
                        v-model="uploadForm.category_id"
                        class="border rounded-md px-3 py-2 w-full"
                    >
                        <option value="">Select Category</option>
                        <option
                            v-for="c in categories"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                </div>

                <!-- BUTTON -->
                <div class="flex items-end">
                    <button
                        @click="uploadDocument"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 w-full"
                    >
                        Upload
                    </button>
                </div>

            </div>
        </div>

        <!-- ========================== -->
        <!-- FILTERS                    -->
        <!-- ========================== -->
        <div class="bg-white shadow rounded-xl p-4 border flex items-center gap-4">
            <label class="font-medium">Category:</label>

            <select
                v-model="selectedCategory"
                class="border px-3 py-2 rounded-md"
            >
                <option value="">All</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">
                    {{ c.name }}
                </option>
            </select>
        </div>

        <!-- ========================== -->
        <!-- DOCUMENT TABLE             -->
        <!-- ========================== -->
        <div class="bg-white shadow-md rounded-xl p-6 border overflow-x-auto">

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium">File</th>
                        <th class="px-4 py-3 text-left text-xs font-medium">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium">Uploaded By</th>
                        <th class="px-4 py-3 text-left text-xs font-medium">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white">

                    <tr
                        v-for="doc in documents
                            .map(d => normalizeDoc(d))
                            .filter(d => !selectedCategory || Number(selectedCategory) === Number(d.category_id))"
                        :key="doc.id"
                    >
                        <!-- FILE -->
                        <td class="px-4 py-3 text-sm font-medium">
                            {{ doc.filename }}
                        </td>

                        <!-- CATEGORY -->
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-1 bg-gray-100 rounded text-xs">
                                {{ doc.category_name }}
                            </span>
                        </td>

                        <!-- USER -->
                        <td class="px-4 py-3 text-sm">
                            {{ doc.user_name }}
                        </td>

                        <!-- DATE -->
                        <td class="px-4 py-3 text-sm">
                            {{ doc.created_at }}
                        </td>

                        <!-- ACTIONS -->
                        <td class="px-4 py-3 text-right flex gap-3 justify-end">

                            <a
                                v-if="doc.filepath"
                                :href="route('projects.documents.download', doc.id)"
                                class="text-indigo-600 hover:text-indigo-800"
                            >
                                Download
                            </a>

                            <Link
                                :href="route('projects.documents.delete', doc.id)"
                                method="delete"
                                as="button"
                                class="text-red-600 hover:text-red-800"
                            >
                                Delete
                            </Link>
                        </td>

                    </tr>

                    <!-- EMPTY STATE -->
                    <tr v-if="documents.length === 0">
                        <td colspan="5" class="text-center py-6 text-gray-500">
                            No documents uploaded yet.
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
</template>
