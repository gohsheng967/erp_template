<script setup>
import { ref, onMounted, inject } from "vue"
import { useForm, Link } from "@inertiajs/vue3"
import axios from "axios"
import { useFormat } from "@/Composables/useFormat"
import {
    Chart,
    ArcElement,
    Tooltip,
    Legend,
    PieController
} from "chart.js"

Chart.register(
    ArcElement,
    Tooltip,
    Legend,
    PieController
)


/* =========================
   PROPS
========================= */
const props = defineProps({
    project: Object,      // must contain uuid
    categories: Array,
})

/* =========================
   COMPOSABLES
========================= */
const toast = inject("toast", null)
const { formatDate } = useFormat()

/* =========================
   STATE
========================= */
const loading = ref(true)
const selectedCategory = ref("")
const uploadType = ref("media") // media | office | url

const documents = ref([])
const summary = ref({
    total: 0,
    by_category: [],
})

/* =========================
   HELPERS
========================= */
function isExternalLink(path) {
    return typeof path === "string" &&
        (path.startsWith("http://") || path.startsWith("https://"))
}

/* =========================
   UPLOAD FORM
========================= */
const uploadForm = useForm({
    file: null,
    url: "",
    category_id: "",
})

function onFileChange(e) {
    uploadForm.file = e.target.files[0]
}

/* =========================
   UPLOAD HANDLER
========================= */
function uploadDocument() {
    if (uploadType.value === "url") {
        uploadForm.post(
            route("projects.documents.upload-url", props.project.uuid),
            {
                preserveScroll: true,
                onSuccess: onUploadSuccess,
                onError: showUploadErrors,
            }
        )
    } else {
        uploadForm.post(
            route("projects.documents.upload", props.project.uuid),
            {
                preserveScroll: true,
                onSuccess: onUploadSuccess,
                onError: showUploadErrors,
            }
        )
    }
}

function onUploadSuccess() {
    uploadForm.reset()
    uploadType.value = "media"
    loadDocuments()
    toast?.value?.show("Document added successfully", "success")
}

function showUploadErrors(errors) {
    const first = Object.values(errors)[0]
    toast?.value?.show(first ?? "Upload failed", "error")
}

/* =========================
   LOAD DOCUMENTS API
========================= */
async function loadDocuments() {
    loading.value = true

    try {
        const { data } = await axios.get(
            route("projects.documents.index", props.project.uuid)
        )

        documents.value = data.documents
        summary.value = data.summary

        renderPie()
    } catch (e) {
        toast?.value?.show("Failed to load documents", "error")
        console.log(e)
    } finally {
        loading.value = false
    }
}

/* =========================
   PIE CHART
========================= */
const pieRef = ref(null)
let pieChart = null

function renderPie() {
    if (
        !pieRef.value ||
        !Array.isArray(summary.value.by_category) ||
        summary.value.by_category.length === 0
    ) {
        return
    }

    if (pieChart) {
        pieChart.destroy()
    }

    pieChart = new Chart(pieRef.value, {
        type: "pie",
        data: {
            labels: summary.value.by_category.map(c => c.name),
            datasets: [{
                data: summary.value.by_category.map(c => c.count),
                backgroundColor: [
                    "#6366f1",
                    "#22c55e",
                    "#f59e0b",
                    "#ef4444",
                    "#06b6d4",
                    "#8b5cf6",
                ],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: "bottom" }
            }
        }
    })
}


/* =========================
   FILTER
========================= */
function filteredDocuments() {
    return documents.value.filter(d =>
        !selectedCategory.value ||
        Number(d.category_id) === Number(selectedCategory.value)
    )
}

/* =========================
   LIFECYCLE
========================= */
onMounted(loadDocuments)
</script>

<template>
<div class="space-y-8">

    <!-- SUMMARY -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white shadow-md rounded-xl p-6 border
                    flex flex-col items-center justify-center text-center">
            <p class="text-gray-500 text-sm">
                Total Documents
            </p>

            <p class="text-4xl font-bold mt-2 text-indigo-600">
                {{ summary.total }}
            </p>

            <div class="mt-4 text-sm text-gray-500 space-y-1">
                <p>
                    Categories:
                    <span class="font-medium text-gray-700">
                        {{ summary.by_category.length }}
                    </span>
                </p>

                <p v-if="documents.length">
                    Latest upload:
                    <span class="font-medium text-gray-700">
                        {{ formatDate(documents[0].created_at) }}
                    </span>
                </p>
            </div>
        </div>

        <div class="bg-white shadow-md rounded-xl p-6 border
                    flex flex-col items-center justify-center text-center">
            <h3 class="text-sm font-semibold mb-3">
                Documents by Category
            </h3>

            <div class="h-[220px] flex items-center justify-center w-full">
                <canvas ref="pieRef"></canvas>
            </div>
        </div>
    </div>

    <!-- UPLOAD -->
    <div class="bg-white shadow-md rounded-xl p-6 border">
        <h3 class="text-lg font-semibold mb-4">Add Document</h3>

        <!-- TYPE -->
        <div class="flex gap-6 mb-4">
            <label><input type="radio" value="media" v-model="uploadType" /> Image / PDF</label>
            <label><input type="radio" value="office" v-model="uploadType" /> Word / Excel</label>
            <label><input type="radio" value="url" v-model="uploadType" /> URL</label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- FILE -->
            <div v-if="uploadType !== 'url'">
                <label class="block mb-1 text-sm font-medium">File</label>
                <input
                    type="file"
                    @change="onFileChange"
                    :accept="uploadType === 'media'
                        ? 'image/*,application/pdf'
                        : '.doc,.docx,.xls,.xlsx'"
                    class="border rounded-md px-3 py-2 w-full"
                />
                <p v-if="uploadForm.errors.file" class="text-sm text-red-600 mt-1">
                    {{ uploadForm.errors.file }}
                </p>
            </div>

            <!-- URL -->
            <div v-else>
                <label class="block mb-1 text-sm font-medium">URL</label>
                <input
                    type="url"
                    v-model="uploadForm.url"
                    placeholder="https://..."
                    class="border rounded-md px-3 py-2 w-full"
                />
                <p v-if="uploadForm.errors.url" class="text-sm text-red-600 mt-1">
                    {{ uploadForm.errors.url }}
                </p>
            </div>

            <!-- CATEGORY -->
            <div>
                <label class="block mb-1 text-sm font-medium">Category</label>
                <select
                    v-model="uploadForm.category_id"
                    class="border rounded-md px-3 py-2 w-full"
                >
                    <option value="">Select Category</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>
                <p v-if="uploadForm.errors.category_id" class="text-sm text-red-600 mt-1">
                    {{ uploadForm.errors.category_id }}
                </p>
            </div>

            <!-- BUTTON -->
            <div class="flex items-end">
                <button
                    @click="uploadDocument"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 w-full"
                    :disabled="uploadForm.processing"
                >
                    Add
                </button>
            </div>

        </div>
    </div>

    <!-- FILTER -->
    <div class="bg-white shadow rounded-xl p-4 border flex items-center gap-4">
        <label class="font-medium">Category:</label>
        <select v-model="selectedCategory" class="border px-3 py-2 rounded-md">
            <option value="">All</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">
                {{ c.name }}
            </option>
        </select>
    </div>

    <!-- TABLE -->
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
                <tr v-for="doc in filteredDocuments()" :key="doc.id">
                    <td class="px-4 py-3 font-medium">
                        {{ doc.filename }}
                        <span v-if="isExternalLink(doc.filepath)"
                              class="ml-2 text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded">
                            LINK
                        </span>
                    </td>

                    <td class="px-4 py-3">{{ doc.category_name }}</td>
                    <td class="px-4 py-3">{{ doc.user_name }}</td>
                    <td class="px-4 py-3">{{ formatDate(doc.created_at) }}</td>

                    <td class="px-4 py-3 text-right flex gap-3 justify-end">
                        <a
                            v-if="!isExternalLink(doc.filepath)"
                            :href="route('projects.documents.download', doc.id)"
                            class="text-indigo-600 hover:text-indigo-800"
                        >
                            Download
                        </a>

                        <a
                            v-else
                            :href="doc.filepath"
                            target="_blank"
                            class="text-indigo-600 hover:text-indigo-800"
                        >
                            Open
                        </a>

                        <Link
                            :href="route('projects.documents.destroy', doc.id)"
                            method="delete"
                            as="button"
                            preserve-scroll
                            @success="toast?.value?.show('Deleted', 'success')"
                            class="text-red-600 hover:text-red-800"
                        >
                            Delete
                        </Link>
                    </td>
                </tr>

                <tr v-if="!loading && documents.length === 0">
                    <td colspan="5" class="text-center py-6 text-gray-500">
                        No documents yet.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>
</template>
