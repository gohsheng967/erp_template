<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    pr: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        required: true,
    },
})

const emit = defineEmits(['delete', 'view'])
</script>

<template>
    <div class="flex justify-center gap-3 text-lg">

        <!-- =====================
             DRAFT ACTIONS
        ====================== -->
        <template v-if="status === 'draft'">

            <Link
                :href="route('purchase-request.edit', pr.uuid)"
                class="text-blue-600 hover:text-blue-800"
                title="Edit"
            >
                <i class="mdi mdi-pencil"></i>
            </Link>

            <button
                @click="emit('delete', pr)"
                class="text-red-600 hover:text-red-800"
                title="Delete"
            >
                <i class="mdi mdi-delete"></i>
            </button>

        </template>

        <!-- =====================
             SUBMITTED / APPROVED / REJECTED
        ====================== -->
        <template v-else>

            <!-- 👁 OPEN MODAL -->
            <button
                @click="emit('view', pr)"
                class="text-indigo-600 hover:text-indigo-800"
                title="View"
            >
                <i class="mdi mdi-eye"></i>
            </button>

        </template>

    </div>
</template>
