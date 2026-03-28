<script setup>
const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['edit'])

function toggle() {
    props.item._expanded = !props.item._expanded
}

function editCurrent() {
    emit('edit', props.item)
}

function editChild(item) {
    emit('edit', item)
}
</script>

<template>
    <li class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
        <div class="flex items-center gap-2">
            <button
                v-if="Array.isArray(item.children) && item.children.length"
                type="button"
                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                @click="toggle"
            >
                <i :class="item._expanded ? 'mdi mdi-chevron-down' : 'mdi mdi-chevron-right'"></i>
            </button>

            <span v-else class="inline-flex h-7 w-7 items-center justify-center text-slate-300">
                <i class="mdi mdi-circle-small"></i>
            </span>

            <span class="font-medium text-slate-800">{{ item.name }}</span>

            <button
                type="button"
                class="ml-auto inline-flex items-center rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-indigo-600 transition hover:border-indigo-200 hover:bg-indigo-50"
                @click="editCurrent"
            >
                <i class="mdi mdi-pencil-outline mr-1"></i>
                Edit
            </button>
        </div>

        <ul v-if="item._expanded && Array.isArray(item.children) && item.children.length" class="mt-3 space-y-2 pl-5">
            <TreeItem
                v-for="child in item.children"
                :key="child.id"
                :item="child"
                @edit="editChild"
            />
        </ul>
    </li>
</template>

<script>
export default {
    name: 'TreeItem',
}
</script>
