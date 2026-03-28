<script setup>
import { ref, computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import FileCategoryModal from './Partials/FileCategoryModal.vue'
import TreeItem from './Partials/TreeItem.vue'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    allCategories: {
        type: Array,
        default: () => [],
    },
})

const showModal = ref(false)
const editCategory = ref(null)
const search = ref('')

const totalCategories = computed(() => props.allCategories.length)
const rootCategories = computed(() => props.categories.length)
const nestedCategories = computed(() => Math.max(0, totalCategories.value - rootCategories.value))

function openCreate() {
    editCategory.value = null
    showModal.value = true
}

function openEdit(category) {
    editCategory.value = category
    showModal.value = true
}

function setExpanded(nodes, expanded) {
    nodes.forEach((node) => {
        node._expanded = expanded
        if (Array.isArray(node.children) && node.children.length) {
            setExpanded(node.children, expanded)
        }
    })
}

function expandAll() {
    setExpanded(props.categories, true)
}

function collapseAll() {
    setExpanded(props.categories, false)
}

function filterTree(nodes, keyword) {
    return nodes.reduce((acc, node) => {
        const name = String(node.name ?? '').toLowerCase()
        const children = Array.isArray(node.children) ? node.children : []
        const filteredChildren = filterTree(children, keyword)
        const matched = name.includes(keyword)

        if (!matched && !filteredChildren.length) {
            return acc
        }

        acc.push({
            ...node,
            _expanded: true,
            children: filteredChildren,
        })

        return acc
    }, [])
}

const visibleCategories = computed(() => {
    const keyword = search.value.trim().toLowerCase()
    if (!keyword) {
        return props.categories
    }

    return filterTree(props.categories, keyword)
})
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">File Categories</h2>
                    <p class="text-sm text-slate-500">Organize document categories with parent-child structure.</p>
                </div>

                <button
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    @click="openCreate"
                >
                    <i class="mdi mdi-plus mr-1"></i>
                    Add Category
                </button>
            </div>
        </template>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Categories</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ totalCategories }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Root Categories</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ rootCategories }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sub-Categories</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-700">{{ nestedCategories }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search category name"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        />
                    </div>

                    <div class="lg:col-span-4">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tree Controls</label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="expandAll"
                                :disabled="!!search"
                            >
                                Expand All
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="collapseAll"
                                :disabled="!!search"
                            >
                                Collapse All
                            </button>
                        </div>
                    </div>
                </div>

                <p class="mt-3 text-sm text-slate-500">
                    Showing {{ visibleCategories.length }} root result{{ visibleCategories.length === 1 ? '' : 's' }}
                    <span v-if="search">for "{{ search }}"</span>
                </p>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div v-if="!visibleCategories.length" class="py-8 text-center text-slate-500">
                    No categories matched your search.
                </div>

                <ul v-else class="space-y-2">
                    <TreeItem
                        v-for="cat in visibleCategories"
                        :key="cat.id"
                        :item="cat"
                        @edit="openEdit"
                    />
                </ul>
            </div>

            <FileCategoryModal
                v-if="showModal"
                :show="showModal"
                :category="editCategory"
                :all-categories="allCategories"
                @close="showModal = false"
            />
        </div>
    </AuthenticatedLayout>
</template>
