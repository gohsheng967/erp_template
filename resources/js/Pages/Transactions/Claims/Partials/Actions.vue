<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    claim: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        required: true, // draft | submitted | approved | rejected | paid
    },
})

const emit = defineEmits(['delete'])
</script>

<template>
    <div class="flex justify-center gap-3 text-lg">

        <!-- VIEW (ALL STATUSES) -->
        <button
            type="button"
            @click="emit('view', claim)"
            class="text-indigo-600 hover:text-indigo-800"
            title="View"
        >
            <i class="mdi mdi-eye"></i>
        </button>

        <!-- EDIT (DRAFT ONLY) -->
        <Link
            v-if="status === 'draft'"
            :href="route('claims.edit', claim.uuid)"
            class="text-blue-600 hover:text-blue-800"
            title="Edit"
        >
            <i class="mdi mdi-pencil"></i>
        </Link>

        <!-- DELETE (DRAFT ONLY) -->
        <button
            v-if="status === 'draft'"
            @click="emit('delete', claim)"
            class="text-red-600 hover:text-red-800"
            title="Delete"
        >
            <i class="mdi mdi-delete"></i>
        </button>

    </div>
</template>
