<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import FileCategoryModal from "./Partials/FileCategoryModal.vue";
import TreeItem from "./Partials/TreeItem.vue";

const props = defineProps({
    categories: Array,
    allCategories: Array,
});

const showModal = ref(false);
const editCategory = ref(null);

function openCreate() {
    editCategory.value = null;
    showModal.value = true;
}

function openEdit(category) {
    editCategory.value = category;
    showModal.value = true;
}
</script>

<template>
    <AuthenticatedLayout>
        <!-- PAGE HEADER -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">File Categories</h2>

                <button
                    class="px-4 py-2 bg-blue-600 text-white rounded shadow"
                    @click="openCreate"
                >
                    + Add Category
                </button>
            </div>
        </template>

        <!-- PAGE CONTENT -->
        <div class="py-6">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow rounded-lg p-6">

                    <!-- EMPTY STATE -->
                    <div
                        v-if="!categories || categories.length === 0"
                        class="text-gray-500 text-center py-5"
                    >
                        No categories found.
                    </div>

                    <!-- CATEGORY TREE -->
                    <ul v-else>
                        <TreeItem
                            v-for="cat in categories"
                            :key="cat.id"
                            :item="cat"
                            @edit="openEdit"
                        />
                    </ul>
                </div>
            </div>

            <!-- MODAL -->
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
