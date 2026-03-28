<script setup>
import { ref, computed, inject } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
})

const toast = inject('toast', null)

const createForm = useForm({
    name: '',
})

const editForm = useForm({
    id: null,
    name: '',
})

const editingId = ref(null)
const deletingCategory = ref(null)
const showDelete = ref(false)
const search = ref('')
const usageFilter = ref('all')
const sortBy = ref('name_asc')

const totalCount = computed(() => props.categories.length)
const usedCount = computed(() => props.categories.filter((c) => Number(c.movements_count) > 0).length)
const unusedCount = computed(() => props.categories.filter((c) => Number(c.movements_count) === 0).length)

const filteredCategories = computed(() => {
    let rows = [...props.categories]

    const keyword = search.value.trim().toLowerCase()
    if (keyword) {
        rows = rows.filter((category) => String(category.name ?? '').toLowerCase().includes(keyword))
    }

    if (usageFilter.value === 'used') {
        rows = rows.filter((category) => Number(category.movements_count) > 0)
    } else if (usageFilter.value === 'unused') {
        rows = rows.filter((category) => Number(category.movements_count) === 0)
    }

    if (sortBy.value === 'name_desc') {
        rows.sort((a, b) => String(b.name ?? '').localeCompare(String(a.name ?? '')))
    } else if (sortBy.value === 'usage_desc') {
        rows.sort((a, b) => Number(b.movements_count) - Number(a.movements_count) || String(a.name ?? '').localeCompare(String(b.name ?? '')))
    } else {
        rows.sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? '')))
    }

    return rows
})

function resetFilters() {
    search.value = ''
    usageFilter.value = 'all'
    sortBy.value = 'name_asc'
}

function submitCreate() {
    createForm.post(route('stock-categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Stock category created successfully.', 'success')
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
    editForm.patch(route('stock-categories.update', editForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Stock category updated successfully.', 'success')
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

    router.delete(route('stock-categories.destroy', deletingCategory.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Stock category deleted successfully.', 'success')
            showDelete.value = false
            deletingCategory.value = null
        },
        onError: (errors) => {
            if (errors?.delete) {
                toast?.value?.show(errors.delete, 'error')
            }
        },
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Stock Categories</h2>
                <p class="text-sm text-slate-500">Maintain stock groupings used by inventory movements.</p>
            </div>
        </template>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Categories</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ totalCount }}</p>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Used</p>
                    <p class="mt-1 text-2xl font-semibold text-indigo-700">{{ usedCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unused</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-700">{{ unusedCount }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Add Stock Category</h3>

                <form @submit.prevent="submitCreate" class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-start">
                    <div>
                        <input
                            v-model="createForm.name"
                            placeholder="Category name (e.g. Electrical)"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        />
                        <div v-if="createForm.errors.name" class="mt-1 text-xs text-red-500">
                            {{ createForm.errors.name }}
                        </div>
                    </div>

                    <button
                        class="inline-flex h-11 w-full items-center justify-center rounded-md bg-indigo-600 px-5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-60 md:w-auto md:min-w-[150px]"
                        :disabled="createForm.processing"
                    >
                        <i class="mdi mdi-plus mr-1"></i>
                        Add
                    </button>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-6">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search category"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        />
                    </div>

                    <div class="lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Usage</label>
                        <select
                            v-model="usageFilter"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            <option value="all">All</option>
                            <option value="used">Used</option>
                            <option value="unused">Unused</option>
                        </select>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sort</label>
                        <select
                            v-model="sortBy"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            <option value="name_asc">Name (A-Z)</option>
                            <option value="name_desc">Name (Z-A)</option>
                            <option value="usage_desc">Most Used</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <p class="text-sm text-slate-500">Showing {{ filteredCategories.length }} result{{ filteredCategories.length === 1 ? '' : 's' }}</p>
                    <button type="button" @click="resetFilters" class="text-sm font-medium text-slate-600 hover:text-slate-900">Reset filters</button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Name</th>
                                <th class="px-4 py-3 text-right font-semibold">Used</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="category in filteredCategories" :key="category.id" class="border-t border-slate-200 hover:bg-slate-50">
                                <template v-if="editingId === category.id">
                                    <td class="px-4 py-3">
                                        <input v-model="editForm.name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                                        <div v-if="editForm.errors.name" class="mt-1 text-xs text-red-500">{{ editForm.errors.name }}</div>
                                    </td>

                                    <td class="px-4 py-3 text-right tabular-nums">{{ category.movements_count }}</td>

                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex justify-end gap-2">
                                            <button
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 text-indigo-700 transition hover:bg-indigo-100"
                                                title="Save"
                                                @click="submitEdit"
                                            >
                                                <i class="mdi mdi-content-save-outline"></i>
                                            </button>
                                            <button
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-600 transition hover:bg-slate-100"
                                                title="Cancel"
                                                @click="cancelEdit"
                                            >
                                                <i class="mdi mdi-close"></i>
                                            </button>
                                        </div>
                                    </td>
                                </template>

                                <template v-else>
                                    <td class="px-4 py-3 text-slate-800">{{ category.name }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ category.movements_count }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex justify-end gap-2">
                                            <button
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-indigo-600 transition hover:border-indigo-200 hover:bg-indigo-50"
                                                title="Edit"
                                                @click="startEdit(category)"
                                            >
                                                <i class="mdi mdi-pencil-outline"></i>
                                            </button>
                                            <button
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-red-600 transition hover:border-red-200 hover:bg-red-50"
                                                title="Delete"
                                                @click="confirmDelete(category)"
                                            >
                                                <i class="mdi mdi-delete-outline"></i>
                                            </button>
                                        </div>
                                    </td>
                                </template>
                            </tr>

                            <tr v-if="!filteredCategories.length">
                                <td colspan="3" class="px-4 py-10 text-center text-slate-500">No stock categories matched your filters.</td>
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
