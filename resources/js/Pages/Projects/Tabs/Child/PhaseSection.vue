<script setup>
import { router } from '@inertiajs/vue3'
import { reactive } from 'vue'

const props = defineProps({
    projectId: {
        type: [Number, String],
        required: true,
    },
    milestone: {
        type: Object,
        required: true,
    },
    reload: {
        type: Function,
        required: true,
    },
})

/* =========================
   LOCAL UI STATE (SAFE)
========================= */
const phaseState = reactive({})

props.milestone.phases.forEach(phase => {
    if (!phaseState[phase.id]) {
        phaseState[phase.id] = {
            open: true,
            originalTitle: phase.title,
            newTask: '',
        }
    }
})

/* =========================
   PHASE CRUD
========================= */
function createPhase() {
    router.post(
        route('milestone.phases.store', {
            project: props.projectId,
            milestone: props.milestone.id,
        }),
        { title: 'New Phase' },
        { onSuccess: props.reload }
    )
}

function updatePhase(phase) {
    if (phase.status === 'approved') return

    router.patch(
        route('milestone.phases.update', {
            project: props.projectId,
            phase: phase.id,
        }),
        { title: phase.title },
        {
            preserveScroll: true,
            onSuccess: () => {
                phaseState[phase.id].originalTitle = phase.title
            },
        }
    )
}

function removePhase(phase) {
    if (phase.status === 'approved') return
    if (!confirm('Remove this phase and all its tasks?')) return

    router.delete(
        route('milestone.phases.destroy', {
            project: props.projectId,
            phase: phase.id,
        }),
        { onSuccess: props.reload }
    )
}

function approvePhase(phase) {
    router.post(
        route('milestone.phases.status', {
            project: props.projectId,
            phase: phase.id,
        }),
        { status: 'approved' },
        { onSuccess: props.reload }
    )
}

/* =========================
   TASK CRUD (OPTIMISTIC)
========================= */
function addPhaseTask(phase) {
    const state = phaseState[phase.id]
    if (!state.newTask || phase.status === 'approved') return

    router.post(
        route('milestone.phase-tasks.store', {
            project: props.projectId,
            phase: phase.id,
        }),
        { title: state.newTask },
        {
            onSuccess: () => {
                state.newTask = ''
                props.reload()
            },
        }
    )
}

function togglePhaseTask(task, phase) {
    if (phase.status === 'approved') return

    // ✅ optimistic update (fixes progress bar)
    task.is_done = !task.is_done

    router.patch(
        route('milestone.phase-tasks.update', {
            project: props.projectId,
            task: task.id,
        }),
        { is_done: task.is_done },
        {
            preserveScroll: true,
            onError: () => {
                // rollback on failure
                task.is_done = !task.is_done
            },
        }
    )
}

function updatePhaseTask(task, phase) {
    if (phase.status === 'approved') return

    router.patch(
        route('milestone.phase-tasks.update', {
            project: props.projectId,
            task: task.id,
        }),
        { title: task.title },
        { preserveScroll: true }
    )
}

function removePhaseTask(task, phase) {
    if (phase.status === 'approved') return
    if (!confirm('Remove this task?')) return

    router.delete(
        route('milestone.phase-tasks.destroy', {
            project: props.projectId,
            task: task.id,
        }),
        { onSuccess: props.reload }
    )
}

/* =========================
   UI HELPERS
========================= */
function togglePhase(phase) {
    phaseState[phase.id].open = !phaseState[phase.id].open
}

function phaseDirty(phase) {
    return phase.title !== phaseState[phase.id].originalTitle
}

function completedCount(phase) {
    return phase.tasks.filter(t => t.is_done).length
}

function phaseProgress(phase) {
    if (!phase.tasks.length) return 0
    return Math.round(
        (completedCount(phase) / phase.tasks.length) * 100
    )
}
</script>

<template>
<div class="bg-white p-6 rounded-xl border shadow space-y-6">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold">Phases & Actions</h3>
        <button
            @click="createPhase"
            class="px-3 py-1 bg-indigo-600 text-white rounded"
        >
            + Add Phase
        </button>
    </div>

    <!-- PHASE LIST -->
    <div
        v-for="phase in milestone.phases"
        :key="phase.id"
        class="border rounded-xl overflow-hidden"
    >
        <!-- PHASE HEADER -->
        <div
            class="flex justify-between items-center px-4 py-3 bg-gray-50 cursor-pointer"
            @click="togglePhase(phase)"
        >
            <div class="flex items-center gap-3">
                <span class="text-gray-400">
                    {{ phaseState[phase.id].open ? '▾' : '▸' }}
                </span>

                <input
                    v-model="phase.title"
                    class="font-semibold bg-transparent border-b focus:outline-none"
                    :disabled="phase.status === 'approved'"
                    @click.stop
                />

                <button
                    v-if="phaseDirty(phase) && phase.status !== 'approved'"
                    @click.stop="updatePhase(phase)"
                    class="text-xs px-2 py-1 bg-indigo-600 text-white rounded"
                >
                    Save
                </button>

                <span class="text-xs text-gray-500">
                    {{ completedCount(phase) }}/{{ phase.tasks.length }}
                </span>

                <span
                    v-if="phase.status === 'approved'"
                    class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded"
                >
                    Locked
                </span>
            </div>

            <div class="flex gap-2">
                <button
                    v-if="phase.status !== 'approved'"
                    @click.stop="approvePhase(phase)"
                    class="px-2 py-1 text-xs bg-green-600 text-white rounded"
                >
                    Approve
                </button>

                <button
                    v-if="phase.status !== 'approved'"
                    @click.stop="removePhase(phase)"
                    class="text-red-500"
                >
                    ✕
                </button>
            </div>
        </div>

        <!-- PROGRESS BAR -->
        <div class="px-4 pt-2">
            <div class="h-2 bg-gray-200 rounded">
                <div
                    class="h-2 rounded bg-indigo-500 transition-all duration-200"
                    :style="{ width: phaseProgress(phase) + '%' }"
                />
            </div>
        </div>

        <!-- TASK LIST -->
        <div
            v-show="phaseState[phase.id].open"
            class="px-4 py-3 space-y-2"
        >
            <div
                v-for="task in phase.tasks"
                :key="task.id"
                class="flex items-center gap-3"
            >
                <input
                    type="checkbox"
                    :checked="task.is_done"
                    @change="togglePhaseTask(task, phase)"
                    :disabled="phase.status === 'approved'"
                />

                <input
                    v-model="task.title"
                    @blur="updatePhaseTask(task, phase)"
                    class="flex-1 bg-transparent border-b focus:outline-none"
                    :class="task.is_done ? 'line-through text-gray-400' : ''"
                    :disabled="phase.status === 'approved'"
                />

                <button
                    v-if="phase.status !== 'approved'"
                    @click="removePhaseTask(task, phase)"
                    class="text-red-500"
                >
                    ✕
                </button>
            </div>

            <!-- ADD TASK -->
            <div class="flex gap-2 pt-2">
                <input
                    v-model="phaseState[phase.id].newTask"
                    class="border px-2 py-1 flex-1 rounded"
                    placeholder="+ Add action item"
                    :disabled="phase.status === 'approved'"
                    @keyup.enter="addPhaseTask(phase)"
                />
                <button
                    @click="addPhaseTask(phase)"
                    class="bg-gray-700 text-white px-3 rounded"
                    :disabled="phase.status === 'approved'"
                >
                    Add
                </button>
            </div>
        </div>
    </div>
</div>
</template>
