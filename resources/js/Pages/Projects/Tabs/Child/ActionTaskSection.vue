<script setup>
import { ref } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'

import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'
import MarkStatusModal from '@/Components/MarkStatusModal.vue'

/* =========================
   PROPS
========================= */
const props = defineProps({
    projectId: [Number, String],
    milestone: Object,
    reload: Function,
})

/* =========================
   PAGE DATA
========================= */
const page = usePage()
const users = page.props.users ?? []

/* =========================
   CREATE FORM
========================= */
const form = useForm({
    title: '',
    priority: 'moderate',
    assigned_to: '',
})

/* =========================
   DELETE STATE
========================= */
const showDelete = ref(false)
const deletingTask = ref(null)

/* =========================
   REMARK / REVIEW MODAL
========================= */
const showRemarkModal = ref(false)
const remarkMode = ref('assignee') // assignee | reviewer
const activeTask = ref(null)

/* =========================
   CREATE TASK
========================= */
function addTask() {
    if (!form.title) return

    form.post(
        route('milestone.action-tasks.store', {
            project: props.projectId,
            milestone: props.milestone.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                form.reset()
                props.reload()
            },
        }
    )
}

/* =========================
   ASSIGNEE CHECK (DEMO)
========================= */
function onAssigneeCheck(task) {
    activeTask.value = task
    remarkMode.value = 'assignee'
    showRemarkModal.value = true
}

/* =========================
   REVIEWER REVIEW (DEMO)
========================= */
function onReviewerReview(task) {
    activeTask.value = task
    remarkMode.value = 'reviewer'
    showRemarkModal.value = true
}

/* =========================
   SAVE REMARK / STATUS
========================= */
function saveRemark({ status, remark }) {
    const payload = {
        remark,
    }

    // Assignee flow: mark done + remark
    if (remarkMode.value === 'assignee') {
        payload.is_done = true
    }

    // Reviewer flow: status + remark
    if (remarkMode.value === 'reviewer') {
        payload.status = status
        payload.is_done = status === 'completed'
    }

    router.patch(
        route('milestone.action-tasks.update', {
            project: props.projectId,
            task: activeTask.value.id,
        }),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                showRemarkModal.value = false
                activeTask.value = null
                props.reload()
            },
        }
    )
}

/* =========================
   INLINE UPDATE (DEMO)
========================= */
function updateTask(task) {
    router.patch(
        route('milestone.action-tasks.update', {
            project: props.projectId,
            task: task.id,
        }),
        {
            title: task.title,
            priority: task.priority,
            assigned_to: task.assigned_to,
        },
        { preserveScroll: true }
    )
}

/* =========================
   DELETE
========================= */
function confirmDelete(task) {
    deletingTask.value = task
    showDelete.value = true
}

function deleteTask() {
    router.delete(
        route('milestone.action-tasks.destroy', {
            project: props.projectId,
            task: deletingTask.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                showDelete.value = false
                deletingTask.value = null
                props.reload()
            },
        }
    )
}
</script>

<template>
<div class="bg-white p-6 rounded-xl border shadow space-y-5">

    <h3 class="text-lg font-semibold">Action Tasks</h3>

    <!-- ADD TASK -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <input
            v-model="form.title"
            class="border px-3 py-2 md:col-span-2"
            placeholder="Immediate task…"
        />

        <select v-model="form.assigned_to" class="border px-3 py-2">
            <option value="">Unassigned</option>
            <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.name }}
            </option>
        </select>

        <select v-model="form.priority" class="border px-3 py-2">
            <option value="low">Low</option>
            <option value="moderate">Moderate</option>
            <option value="urgent">Urgent</option>
        </select>

        <button
            @click="addTask"
            class="md:col-span-4 bg-indigo-600 text-white py-2 rounded"
        >
            Add Task
        </button>
    </div>

    <!-- TASK ITEM -->
    <div
        v-for="task in milestone.action_tasks"
        :key="task.id"
        class="border rounded-lg p-4 space-y-2 hover:bg-gray-50"
    >
        <!-- ROW 1: MAIN -->
        <div class="flex items-start gap-3">

            <!-- CHECKBOX -->
            <input
                type="checkbox"
                :checked="task.is_done"
                @change="onAssigneeCheck(task)"
                class="mt-1"
            />

            <!-- TITLE -->
            <input
                v-model="task.title"
                @blur="updateTask(task)"
                class="flex-1 font-medium border-b focus:outline-none bg-transparent"
            />

            <!-- ACTIONS -->
            <div class="flex items-center gap-2">
                <button
                    @click="onReviewerReview(task)"
                    class="text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-100"
                >
                    Review
                </button>

                <button
                    @click="confirmDelete(task)"
                    class="text-red-500 hover:text-red-700"
                    title="Remove"
                >
                    ✕
                </button>
            </div>
        </div>

        <!-- ROW 2: META -->
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600 ml-6">

            <!-- ASSIGNEE -->
            <div class="flex items-center gap-1">
                <span class="text-gray-400">Assignee:</span>
                <select
                    v-model="task.assigned_to"
                    @change="updateTask(task)"
                    class="border rounded px-2 py-1 text-sm"
                >
                    <option value="">Unassigned</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">
                        {{ u.name }}
                    </option>
                </select>
            </div>

            <!-- PRIORITY -->
            <div class="flex items-center gap-1">
                <span class="text-gray-400">Priority:</span>
                <select
                    v-model="task.priority"
                    @change="updateTask(task)"
                    class="border rounded px-2 py-1 text-sm"
                >
                    <option value="low">Low</option>
                    <option value="moderate">Moderate</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>

            <!-- STATUS BADGE -->
            <span
                class="px-2 py-1 rounded text-xs"
                :class="{
                    'bg-gray-100 text-gray-700': task.status === 'open',
                    'bg-green-100 text-green-700': task.status === 'completed',
                    'bg-yellow-100 text-yellow-700': task.status === 'blocked',
                    'bg-red-100 text-red-700': task.status === 'cancelled',
                }"
            >
                {{ task.status || 'open' }}
            </span>
        </div>

        <!-- ROW 3: REMARK -->
        <div
            v-if="task.remark"
            class="ml-6 text-xs text-gray-500 bg-gray-50 border-l-2 border-gray-300 pl-3 py-2 rounded"
        >
            <span class="font-medium text-gray-600">Remark:</span>
            {{ task.remark }}
        </div>
    </div>


    <!-- EMPTY -->
    <div
        v-if="!milestone.action_tasks.length"
        class="text-sm text-gray-400"
    >
        No action tasks yet.
    </div>

    <!-- REMARK / REVIEW MODAL -->
    <MarkStatusModal
        :show="showRemarkModal"
        :task="activeTask"
        :mode="remarkMode"
        @confirm="saveRemark"
        @close="showRemarkModal = false"
    />

    <!-- DELETE CONFIRM -->
    <DeleteConfirmation
        v-if="showDelete"
        title="Delete Action Task"
        message="This action task will be permanently removed."
        @confirm="deleteTask"
        @close="showDelete = false"
    />

</div>
</template>
