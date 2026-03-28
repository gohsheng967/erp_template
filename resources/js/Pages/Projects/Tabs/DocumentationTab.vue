<script setup>
import { ref, onMounted, inject, computed, watch } from "vue"
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
const documentSearch = ref("")
const uploadType = ref("media") // media | office | other | url
const showUploadCategoryPicker = ref(false)
const showFilterCategoryPicker = ref(false)

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
    const selected = allowedExtensionsForUpload.value

    if (selected.length) {
        if (uploadType.value === "media") {
            const mediaSet = new Set(["pdf", ...imageExtensions])
            const mediaAllowed = selected.filter((ext) => mediaSet.has(ext))
            return mediaAllowed.length ? mediaAllowed.map((ext) => `.${ext}`).join(",") : ""
        }

        if (uploadType.value === "office") {
            const officeSet = new Set(officeExtensions)
            const officeAllowed = selected.filter((ext) => officeSet.has(ext))
            return officeAllowed.length ? officeAllowed.map((ext) => `.${ext}`).join(",") : ""
        }

        if (uploadType.value === "other") {
            const typedSet = new Set(["pdf", ...imageExtensions, ...officeExtensions])
            const otherAllowed = selected.filter((ext) => !typedSet.has(ext))
            return otherAllowed.length ? otherAllowed.map((ext) => `.${ext}`).join(",") : ""
        }
    }

    if (uploadType.value === "media") return "image/*,application/pdf"
    if (uploadType.value === "office") return ".doc,.docx,.xls,.xlsx"
    if (uploadType.value === "other") return ".txt,.csv,.zip,.rar,.7z,.xml,.json,.rtf,.ppt,.pptx,.odt,.ods,.dwg,.dxf"
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
    if (!uploadForm.category_id) {
        uploadForm.setError("category_id", "Please select a category first.")
        toast?.value?.show("Please select category first", "error")
        return
    }

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
const filteredDocuments = computed(() => {
    let rows = documents.value

    if (selectedCategory.value === "others") {
        rows = rows.filter((d) => !d.category_id)
    } else if (selectedCategory.value) {
        rows = rows.filter((d) =>
            String(d.category_id) === String(selectedCategory.value)
        )
    }

    const query = documentSearch.value.trim().toLowerCase()
    if (!query) {
        return rows
    }

    return rows.filter((doc) => {
        return [
            doc.filename,
            doc.category_name,
            doc.user_name,
            doc.type,
        ]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query))
    })
})

const categoryById = computed(() => {
    const map = new Map()

    for (const category of props.categories ?? []) {
        map.set(String(category.id), category)
    }

    return map
})

const childrenByParent = computed(() => {
    const map = new Map()

    for (const category of props.categories ?? []) {
        const parentKey = category.parent_id ? String(category.parent_id) : "__root__"

        if (!map.has(parentKey)) {
            map.set(parentKey, [])
        }

        map.get(parentKey).push(category)
    }

    for (const value of map.values()) {
        value.sort((a, b) => String(a.name).localeCompare(String(b.name)))
    }

    return map
})

const flattenedCategories = computed(() => {
    const rows = []
    const roots = [...(childrenByParent.value.get("__root__") ?? [])]

    // Include orphan categories as roots for safety.
    for (const category of props.categories ?? []) {
        if (!category.parent_id) continue

        if (!categoryById.value.has(String(category.parent_id))) {
            if (!roots.some((root) => String(root.id) === String(category.id))) {
                roots.push(category)
            }
        }
    }

    roots.sort((a, b) => String(a.name).localeCompare(String(b.name)))

    const walk = (items, depth = 0) => {
        for (const item of items) {
            rows.push({
                id: item.id,
                name: item.name,
                depth,
            })

            const children = childrenByParent.value.get(String(item.id)) ?? []
            if (children.length) {
                walk(children, depth + 1)
            }
        }
    }

    walk(roots, 0)

    return rows
})

const selectedUploadCategoryLabel = computed(() => {
    if (!uploadForm.category_id) return "Select Category"
    if (String(uploadForm.category_id) === "others") return "Others (Any Format)"

    return categoryById.value.get(String(uploadForm.category_id))?.name ?? "Select Category"
})

const selectedFilterCategoryLabel = computed(() => {
    if (!selectedCategory.value) return "All"
    if (String(selectedCategory.value) === "others") return "Others (Any Format)"

    return categoryById.value.get(String(selectedCategory.value))?.name ?? "All"
})

const imageExtensions = ["jpg", "jpeg", "png", "gif", "webp", "svg", "bmp"]
const officeExtensions = ["doc", "docx", "xls", "xlsx"]

const uploadTypeOptions = [
    { key: "media", label: "Image / PDF" },
    { key: "office", label: "Word / Excel" },
    { key: "other", label: "Other File" },
    { key: "url", label: "URL" },
]

const selectedUploadCategory = computed(() => {
    if (!uploadForm.category_id || String(uploadForm.category_id) === "others") return null
    return categoryById.value.get(String(uploadForm.category_id)) ?? null
})

const allowedExtensionsForUpload = computed(() => {
    const list = selectedUploadCategory.value?.allowed_extensions ?? []
    return list
        .map((ext) => String(ext).toLowerCase().replace(/^\./, "").trim())
        .filter(Boolean)
})

const availableUploadTypes = computed(() => {
    if (!uploadForm.category_id) return []
    if (String(uploadForm.category_id) === "others") return uploadTypeOptions

    const ext = allowedExtensionsForUpload.value
    if (!ext.length) return uploadTypeOptions

    const hasMedia = ext.some((item) => item === "pdf" || imageExtensions.includes(item))
    const hasOffice = ext.some((item) => officeExtensions.includes(item))
    const hasOther = ext.some((item) => !["pdf", ...imageExtensions, ...officeExtensions].includes(item))

    const keys = new Set(["url"])
    if (hasMedia) keys.add("media")
    if (hasOffice) keys.add("office")
    if (hasOther) keys.add("other")

    return uploadTypeOptions.filter((opt) => keys.has(opt.key))
})

watch(
    availableUploadTypes,
    (types) => {
        if (!types.length) {
            uploadType.value = "media"
            return
        }

        if (!types.some((t) => t.key === uploadType.value)) {
            uploadType.value = types[0].key
            uploadForm.file = null
            uploadForm.url = ""
        }
    },
    { immediate: true }
)

function pickUploadCategory(value) {
    uploadForm.category_id = value
    showUploadCategoryPicker.value = false
    uploadForm.clearErrors("category_id", "file", "url")
    uploadForm.file = null
    uploadForm.url = ""
}

function pickFilterCategory(value) {
    selectedCategory.value = value
    showFilterCategoryPicker.value = false
}

function fileExt(filename) {
    if (!filename || typeof filename !== "string") return ""
    const dotIndex = filename.lastIndexOf(".")
    if (dotIndex < 0 || dotIndex === filename.length - 1) return ""
    return filename.slice(dotIndex + 1).toLowerCase()
}

function fileTypeLabel(doc) {
    if (isExternalLink(doc.filepath)) return "Link"
    const ext = fileExt(doc.filename)
    return ext ? ext.toUpperCase() : "File"
}

function fileIconClass(doc) {
    if (isExternalLink(doc.filepath)) return "mdi-link-variant"

    const ext = fileExt(doc.filename)
    const imageExt = ["jpg", "jpeg", "png", "gif", "webp", "svg", "bmp"]
    const pdfExt = ["pdf"]
    const sheetExt = ["xls", "xlsx", "csv"]
    const docExt = ["doc", "docx", "rtf", "odt"]
    const archiveExt = ["zip", "rar", "7z"]

    if (imageExt.includes(ext)) return "mdi-file-image-outline"
    if (pdfExt.includes(ext)) return "mdi-file-pdf-box"
    if (sheetExt.includes(ext)) return "mdi-file-excel-box"
    if (docExt.includes(ext)) return "mdi-file-word-box"
    if (archiveExt.includes(ext)) return "mdi-folder-zip-outline"

    return "mdi-file-document-outline"
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
        <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/60 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- CATEGORY -->
                <div class="relative md:col-span-2">
                    <label class="block mb-1 text-xs font-medium">Category</label>
                    <button
                        type="button"
                        class="h-10 w-full border border-slate-300 bg-white rounded-lg px-3 text-left text-sm flex items-center justify-between"
                        @click="showUploadCategoryPicker = !showUploadCategoryPicker"
                    >
                        <span class="truncate">{{ selectedUploadCategoryLabel }}</span>
                        <i class="mdi mdi-chevron-down text-base text-slate-500"></i>
                    </button>

                    <div
                        v-if="showUploadCategoryPicker"
                        class="absolute z-20 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg max-h-72 overflow-y-auto"
                    >
                        <button
                            type="button"
                            class="w-full border-b px-3 py-2.5 text-left text-sm hover:bg-slate-50"
                            :class="!uploadForm.category_id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700'"
                            @click="pickUploadCategory('')"
                        >
                            Select Category
                        </button>
                        <button
                            type="button"
                            class="w-full border-b px-3 py-2.5 text-left text-sm hover:bg-slate-50"
                            :class="String(uploadForm.category_id) === 'others' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700'"
                            @click="pickUploadCategory('others')"
                        >
                            Others (Any Format)
                        </button>
                        <button
                            v-for="item in flattenedCategories"
                            :key="item.id"
                            type="button"
                            class="w-full px-3 py-2.5 text-left text-sm hover:bg-slate-50"
                            :class="String(uploadForm.category_id) === String(item.id) ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700'"
                            :style="{ paddingLeft: `${10 + item.depth * 14}px` }"
                            @click="pickUploadCategory(String(item.id))"
                        >
                            <span v-if="item.depth" class="mr-2 text-slate-300">-</span>{{ item.name }}
                        </button>
                    </div>

                    <p v-if="uploadForm.errors.category_id" class="text-sm text-red-600 mt-1">
                        {{ uploadForm.errors.category_id }}
                    </p>
                </div>

                <!-- BUTTON -->
                <div class="flex items-end">
                    <button
                        @click="uploadDocument"
                        class="h-10 px-4 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 w-full text-sm font-semibold shadow-sm disabled:opacity-60 disabled:cursor-not-allowed"
                        :disabled="uploadForm.processing || !uploadForm.category_id"
                    >
                        {{ uploadForm.processing ? 'Adding...' : 'Add Document' }}
                    </button>
                </div>
            </div>

            <div v-if="!uploadForm.category_id" class="rounded-lg border border-dashed border-slate-300 bg-white px-3 py-3 text-sm text-slate-500">
                Select category first. Available upload types will appear after category is chosen.
            </div>
            <div v-else-if="String(uploadForm.category_id) === 'others'" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-800">
                <span class="font-medium">Others</span> is for formats not defined in your category extension rules.
            </div>

            <template v-else>
                <!-- TYPE -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <label
                        v-for="option in availableUploadTypes"
                        :key="option.key"
                        class="cursor-pointer border rounded-lg px-3 py-2 text-xs text-center transition"
                        :class="uploadType === option.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-semibold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                    >
                        <input type="radio" :value="option.key" v-model="uploadType" class="sr-only" />
                        {{ option.label }}
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- FILE -->
                    <div v-if="uploadType !== 'url'" class="md:col-span-2">
                        <label class="block mb-1 text-xs font-medium">File</label>
                        <input
                            ref="fileInputRef"
                            type="file"
                            @change="onFileChange"
                            :accept="uploadAccept()"
                            class="hidden"
                        />
                        <div class="h-9 border border-slate-300 bg-white rounded-lg px-2 flex items-center gap-2">
                            <button
                                type="button"
                                class="h-8 px-3 text-sm rounded-md border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-700 whitespace-nowrap"
                                @click="openFilePicker"
                            >
                                Choose File
                            </button>
                            <span class="text-xs text-slate-600 truncate">
                                {{ uploadForm.file?.name ?? 'No file selected' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-4">
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
                    <div v-else class="md:col-span-2">
                        <label class="block mb-1 text-xs font-medium">URL</label>
                        <input
                            type="url"
                            v-model="uploadForm.url"
                            placeholder="https://..."
                            class="h-10 border border-slate-300 bg-white rounded-lg px-3 text-sm w-full"
                        />
                        <p v-if="uploadForm.errors.url" class="text-sm text-red-600 mt-1">
                            {{ uploadForm.errors.url }}
                        </p>
                    </div>
                </div>
            </template>

        </div>
    </div>

    <!-- FILTER -->
    <div class="bg-white shadow rounded-xl p-4 border flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
        <label class="font-medium text-slate-700">Filter by Category</label>
        <div class="relative md:min-w-64">
            <button
                type="button"
                class="h-10 w-full border border-slate-300 bg-white rounded-lg px-3 text-left text-sm flex items-center justify-between"
                @click="showFilterCategoryPicker = !showFilterCategoryPicker"
            >
                <span class="truncate">{{ selectedFilterCategoryLabel }}</span>
                <i class="mdi mdi-chevron-down text-base text-slate-500"></i>
            </button>

            <div
                v-if="showFilterCategoryPicker"
                class="absolute z-20 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg max-h-72 overflow-y-auto"
            >
                <button
                    type="button"
                    class="w-full border-b px-3 py-2.5 text-left text-sm hover:bg-slate-50"
                    :class="!selectedCategory ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700'"
                    @click="pickFilterCategory('')"
                >
                    All
                </button>
                <button
                    type="button"
                    class="w-full border-b px-3 py-2.5 text-left text-sm hover:bg-slate-50"
                    :class="String(selectedCategory) === 'others' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700'"
                    @click="pickFilterCategory('others')"
                >
                    Others (Any Format)
                </button>
                <button
                    v-for="item in flattenedCategories"
                    :key="item.id"
                    type="button"
                    class="w-full px-3 py-2.5 text-left text-sm hover:bg-slate-50"
                    :class="String(selectedCategory) === String(item.id) ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700'"
                    :style="{ paddingLeft: `${10 + item.depth * 14}px` }"
                    @click="pickFilterCategory(String(item.id))"
                >
                    <span v-if="item.depth" class="mr-2 text-slate-300">-</span>{{ item.name }}
                </button>
            </div>
        </div>

        <input
            v-model="documentSearch"
            type="text"
            placeholder="Search file, category, uploader..."
            class="h-10 border border-slate-300 bg-white rounded-lg px-3 text-sm md:ml-auto md:w-80"
        />
    </div>

    <!-- LISTING -->
    <div class="bg-white shadow-md rounded-xl p-6 border">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-800">Documents</h3>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                {{ filteredDocuments.length }} result<span v-if="filteredDocuments.length !== 1">s</span>
            </span>
        </div>

        <div v-if="loading" class="py-8 text-center text-sm text-slate-500">
            Loading documents...
        </div>

        <div
            v-else-if="filteredDocuments.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500"
        >
            No documents match current filter.
        </div>

        <div v-else class="space-y-2">
            <div
                v-for="doc in filteredDocuments"
                :key="doc.id"
                class="rounded-lg border border-slate-200 px-3 py-2 hover:border-indigo-200 hover:bg-indigo-50/40 transition"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex items-start gap-3">
                        <span class="mt-0.5 rounded-md bg-slate-100 p-1.5 text-slate-600">
                            <i class="mdi text-base" :class="fileIconClass(doc)"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ doc.filename }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-medium text-indigo-700">
                                    {{ doc.category_name || "Others" }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                                    {{ fileTypeLabel(doc) }}
                                </span>
                                <span class="text-[11px] text-slate-500">
                                    by {{ doc.user_name }} • {{ formatDate(doc.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <a
                            v-if="!isExternalLink(doc.filepath)"
                            :href="route('projects.documents.download', doc.id)"
                            class="h-7 w-7 inline-flex items-center justify-center rounded-md text-indigo-600 hover:bg-indigo-100"
                            title="Download"
                            aria-label="Download"
                        >
                            <i class="mdi mdi-download text-base"></i>
                        </a>

                        <a
                            v-else
                            :href="doc.filepath"
                            target="_blank"
                            class="h-7 w-7 inline-flex items-center justify-center rounded-md text-indigo-600 hover:bg-indigo-100"
                            title="Open"
                            aria-label="Open"
                        >
                            <i class="mdi mdi-open-in-new text-base"></i>
                        </a>

                        <Link
                            :href="route('projects.documents.destroy', doc.id)"
                            method="delete"
                            as="button"
                            preserve-scroll
                            @success="toast?.value?.show('Deleted', 'success')"
                            class="h-7 w-7 inline-flex items-center justify-center rounded-md text-rose-600 hover:bg-rose-100"
                            title="Delete"
                            aria-label="Delete"
                        >
                            <i class="mdi mdi-trash-can-outline text-base"></i>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</template>


