<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        required: true, // draft | issued | approved | received | cancelled
    },
})

const emit = defineEmits(['view', 'cancel'])
</script>

<template>
    <div class="flex justify-center gap-3 text-lg">

        <!-- VIEW -->
        <button
            type="button"
            @click="emit('view', invoice)"
            class="text-indigo-600 hover:text-indigo-800"
            title="View"
        >
            <i class="mdi mdi-eye"></i>
        </button>

        <!-- EDIT (DRAFT ONLY) -->
        <Link
            v-if="status === 'draft'"
            :href="route('ar-invoices.edit', invoice.uuid)"
            class="text-blue-600 hover:text-blue-800"
            title="Edit"
        >
            <i class="mdi mdi-pencil"></i>
        </Link>

        <!-- CANCEL (DRAFT + ISSUED ONLY) -->
        <button
            v-if="status === 'draft' || status === 'issued'"
            @click="emit('cancel', invoice)"
            class="text-orange-600 hover:text-orange-800"
            title="Cancel Invoice"
        >
            <i class="mdi mdi-cancel"></i>
        </button>

    </div>
</template>
