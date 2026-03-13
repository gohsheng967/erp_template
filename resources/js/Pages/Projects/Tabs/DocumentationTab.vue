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
const uploadType = ref("media") // media | office | other | url

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

function uploadAccept() {
    if (uploadType.value === "media") {
        return "image/*,application/pdf"
    }

    if (uploadType.value === "office") {
        return ".doc,.docx,.xls,.xlsx"
    }

    if (uploadType.value === "other") {
        // Common non-office formats (archives, text/data, CAD-like docs).
        return ".txt,.csv,.zip,.rar,.7z,.xml,.json,.rtf,.ppt,.pptx,.odt,.ods,.dwg,.dxf"
    }

    return ""
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

const fileInputRef = ref(null)

function openFilePicker() {
    fileInputRef.value?.click()
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
    if (!selectedCategory.value) {
        return documents.value
    }

    if (selectedCategory.value === "others") {
        return documents.value.filter((d) => !d.category_id)
    }

    return documents.value.filter((d) =>
        String(d.category_id) === String(selectedCategory.value)
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
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-5">
            <div>
                <h3 class="text-lg font-semibold text-slate-800">Add Document</h3>
                <p class="text-sm text-slate-500">Upload files or attach external links to this project.</p>
            </div>
        </div>

        <!-- TYPE -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-5">
            <label
                class="cursor-pointer border rounded-lg px-3 py-2 text-sm text-center transition"
                :class="uploadType === 'media' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            >
                <input type="radio" value="media" v-model="uploadType" class="sr-only" />
                Image / PDF
            </label>
            <label
                class="cursor-pointer border rounded-lg px-3 py-2 text-sm text-center transition"
                :class="uploadType === 'office' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            >
                <input type="radio" value="office" v-model="uploadType" class="sr-only" />
                Word / Excel
            </label>
            <label
                class="cursor-pointer border rounded-lg px-3 py-2 text-sm text-center transition"
                :class="uploadType === 'other' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            >
                <input type="radio" value="other" v-model="uploadType" class="sr-only" />
                Other File
            </label>
            <label
                class="cursor-pointer border rounded-lg px-3 py-2 text-sm text-center transition"
                :class="uploadType === 'url' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            >
                <input type="radio" value="url" v-model="uploadType" class="sr-only" />
                URL
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 rounded-lg border border-slate-200 bg-slate-50/60">

            <!-- FILE -->
            <div v-if="uploadType !== 'url'">
                <label class="block mb-1 text-sm font-medium">File</label>
                <input
                    ref="fileInputRef"
                    type="file"
                    @change="onFileChange"
                    :accept="uploadAccept()"
                    class="hidden"
                />
                <div class="border border-slate-300 bg-white rounded-md p-2.5 flex items-center gap-3">
                    <button
                        type="button"
                        class="px-3 py-1.5 text-sm rounded-md border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-700 whitespace-nowrap"
                        @click="openFilePicker"
                    >
                        Choose File
                    </button>
                    <span class="text-sm text-slate-600 truncate">
                        {{ uploadForm.file?.name ?? 'No file selected' }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Max size and extension rules depend on selected category.
                </p>
                <p v-if="uploadType === 'other'" class="text-xs text-gray-500 mt-1">
                    For uncommon formats, choose a category with matching extension rules or use Others category.
                </p>
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
                    class="border border-slate-300 bg-white rounded-md px-3 py-2 w-full"
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
                    class="border border-slate-300 bg-white rounded-md px-3 py-2 w-full"
                >
                    <option value="">Select Category</option>
                    <option value="others">Others</option>
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
                    class="px-4 py-2.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 w-full font-medium shadow-sm"
                    :disabled="uploadForm.processing"
                >
                    {{ uploadForm.processing ? 'Adding...' : 'Add Document' }}
                </button>
            </div>

        </div>
    </div>

    <!-- FILTER -->
    <div class="bg-white shadow rounded-xl p-4 border flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
        <label class="font-medium text-slate-700">Filter by Category</label>
        <select v-model="selectedCategory" class="border border-slate-300 px-3 py-2 rounded-md md:min-w-64">
            <option value="">All</option>
            <option value="others">Others</option>
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

                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center gap-3 justify-end">
                        <a
                            v-if="!isExternalLink(doc.filepath)"
                            :href="route('projects.documents.download', doc.id)"
                            class="text-indigo-600 hover:text-indigo-800"
                            title="Download"
                            aria-label="Download"
                        >
                            <i class="mdi mdi-download text-lg"></i>
                        </a>

                        <a
                            v-else
                            :href="doc.filepath"
                            target="_blank"
                            class="text-indigo-600 hover:text-indigo-800"
                            title="Open"
                            aria-label="Open"
                        >
                            <i class="mdi mdi-open-in-new text-lg"></i>
                        </a>

                        <Link
                            :href="route('projects.documents.destroy', doc.id)"
                            method="delete"
                            as="button"
                            preserve-scroll
                            @success="toast?.value?.show('Deleted', 'success')"
                            class="text-red-600 hover:text-red-800"
                            title="Delete"
                            aria-label="Delete"
                        >
                            <i class="mdi mdi-trash-can-outline text-lg"></i>
                        </Link>
                        </div>
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
