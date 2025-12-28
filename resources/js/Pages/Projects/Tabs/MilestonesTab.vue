<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import ActionTaskSection from './Child/ActionTaskSection.vue'
import PhaseSection from './Child/PhaseSection.vue'

/* =========================
   PROPS
========================= */
const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
})

/* =========================
   STATE
========================= */
const milestones = ref([])
const activeMilestoneId = ref(null)
const loading = ref(true)

/* =========================
   LOAD DATA
========================= */
function loadMilestones(autoSelect = false) {
    router.reload({
        only: ['milestones'],
        preserveScroll: true,
        onFinish: () => {
            const page = usePage()

            milestones.value = Array.isArray(page.props.milestones)
                ? page.props.milestones
                : []

            if (milestones.value.length) {
                if (autoSelect || !activeMilestoneId.value) {
                    activeMilestoneId.value = milestones.value[0].id
                }
            } else {
                activeMilestoneId.value = null
            }

            loading.value = false
        },
    })
}

onMounted(loadMilestones)

/* =========================
   ACTIVE MILESTONE
========================= */
const milestone = computed(() => {
    if (!activeMilestoneId.value) return null
    return milestones.value.find(m => m.id === activeMilestoneId.value) || null
})

/* =========================
   CREATE MILESTONE
========================= */
function createMilestone() {
    router.post(
        route('milestones.store', {
            project: props.project.id,
        }),
        { title: 'New Milestone' },
        {
            preserveScroll: true,
            onSuccess: () => loadMilestones(true),
        }
    )
}
</script>

<template>
<div class="space-y-8">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-3">
        </div>

        <button
            v-if="milestones.length == 0"
            @click="createMilestone"
            class="px-4 py-2 bg-indigo-600 text-white rounded"
        >
            + Create Milestone
        </button>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="text-sm text-gray-500">
        Loading milestones…
    </div>

    <!-- EMPTY -->
    <div
        v-else-if="!milestone"
        class="bg-white p-6 rounded-xl border text-gray-500"
    >
        No milestone selected.
    </div>

    <!-- CONTENT -->
    <div v-else class="space-y-8">

        <!-- SECTION 1: ACTION TASKS -->
        <ActionTaskSection
            :project-id="project.id"
            :milestone="milestone"
            :reload="loadMilestones"
        />

        <!-- SECTION 2: PHASES -->
        <PhaseSection
            :project-id="project.id"
            :milestone="milestone"
            :reload="loadMilestones"
        />

    </div>
</div>
</template>
