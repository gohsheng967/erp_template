<script setup>
const props = defineProps({
    item: { type: Object, required: true }
});

const emit = defineEmits(["edit"]);

function toggle() {
    props.item._expanded = !props.item._expanded;
}
</script>

<template>
    <li class="border-l pl-4 mb-2">

        <div class="flex items-center gap-2">

            <!-- Expand / Collapse -->
            <button
                v-if="Array.isArray(item.children) && item.children.length"
                class="text-gray-600 hover:text-gray-800"
                @click="toggle"
            >
                <span v-if="!item._expanded">▶</span>
                <span v-else>▼</span>
            </button>

            <span class="font-medium text-gray-700">{{ item.name }}</span>

            <button
                class="ml-auto px-2 py-1 text-xs bg-blue-500 text-white rounded"
                @click="emit('edit', item)"
            >
                Edit
            </button>
        </div>

        <!-- CHILDREN -->
        <ul v-if="item._expanded" class="ml-4 mt-2">
            <TreeItem
                v-for="child in item.children"
                :key="child.id"
                :item="child"
                @edit="emit('edit')"
            />
        </ul>

    </li>
</template>

<script>
export default {
    name: "TreeItem" // ← recursion works automatically
};
</script>
