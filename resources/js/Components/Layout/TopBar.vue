<script setup>
import { usePage, Link, router } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

const page = usePage()
const user = page.props.auth.user.data

/* =========================
   COUNTS
========================= */
const urgent = ref(0)
const moderate = ref(0)
const low = ref(0)
const loading = ref(false)

/* =========================
   POPUP STATE
========================= */
const showPopup = ref(false)
const activePriority = ref(null)
const taskList = ref([])

let pollTimer = null

/* =========================
   HELPERS
========================= */
function initials(name) {
    if (!name) return 'U'
    const parts = name.split(' ')
    return parts.length === 1
        ? parts[0][0].toUpperCase()
        : (parts[0][0] + parts[1][0]).toUpperCase()
}

/* =========================
   FETCH COUNTS
========================= */
async function loadPrioritySummary() {
    if (loading.value) return
    loading.value = true

    try {
        const { data } = await axios.get(
            route('priority-summary')
        )

        urgent.value = data.urgent ?? 0
        moderate.value = data.moderate ?? 0
        low.value = data.low ?? 0
    } catch (e) {
        console.error('Topbar count error', e)
    } finally {
        loading.value = false
    }
}

/* =========================
   POPUP LOGIC
========================= */
async function openPriorityPopup(priority) {
    activePriority.value = priority
    showPopup.value = true
    taskList.value = []

    try {
        const { data } = await axios.get(
            route('priority-list'),
            { params: { priority } }
        )
        taskList.value = data
    } catch (e) {
        console.error('Popup list error', e)
    }
}

function closePopup() {
    showPopup.value = false
    activePriority.value = null
    taskList.value = []
}

function goToTask(task) {
    closePopup()

    router.visit(
        route('projects.show', task.project_uuid),
        {
            data: { task: task.id }
        }
    )
}

/* =========================
   REAL-TIME MECHANISM
========================= */
onMounted(() => {
    loadPrioritySummary()

    // Poll every 30s
    pollTimer = setInterval(loadPrioritySummary, 30000)

    // Instant refresh on task update
    window.addEventListener(
        'action-task-updated',
        loadPrioritySummary
    )
})

onBeforeUnmount(() => {
    clearInterval(pollTimer)
    window.removeEventListener(
        'action-task-updated',
        loadPrioritySummary
    )
})
</script>

<template>
<header
    class="
        h-14 backdrop-blur-xl bg-white/70
        border-b border-white/40
        px-4 flex items-center justify-between
        sticky top-0 z-30 shadow-sm
    "
    style="
        background: linear-gradient(
            135deg,
            #ffffff 0%,
            #f3f4f6 50%,
            #e5e7eb 100%
        );
    "
>
    <!-- Mobile Sidebar Toggle -->
    <button class="text-gray-600 hover:text-black sm:hidden">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <!-- PRIORITY BAR -->
    <div class="hidden sm:flex items-center gap-6">

        <!-- Urgent -->
        <div
            class="flex items-center gap-2 cursor-pointer"
            @click="openPriorityPopup('urgent')"
        >
            <span class="h-3 w-3 rounded-full bg-red-500"></span>
            <span class="text-sm text-gray-700">Urgent</span>
            <span
                class="text-sm font-semibold"
                :class="urgent > 0 ? 'text-red-600' : 'text-gray-900'"
            >
                {{ urgent }}
            </span>
        </div>

        <!-- Moderate -->
        <div
            class="flex items-center gap-2 cursor-pointer"
            @click="openPriorityPopup('moderate')"
        >
            <span class="h-3 w-3 rounded-full bg-yellow-500"></span>
            <span class="text-sm text-gray-700">Moderate</span>
            <span class="text-sm font-semibold text-gray-900">
                {{ moderate }}
            </span>
        </div>

        <!-- Low -->
        <div
            class="flex items-center gap-2 cursor-pointer"
            @click="openPriorityPopup('low')"
        >
            <span class="h-3 w-3 rounded-full bg-green-500"></span>
            <span class="text-sm text-gray-700">Low</span>
            <span class="text-sm font-semibold text-gray-900">
                {{ low }}
            </span>
        </div>

    </div>

    <!-- RIGHT -->
    <div class="flex items-center gap-4 text-gray-600">

        <!-- Notification Bell -->
        <button class="relative hover:text-black">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M14 21a2 2 0 11-4 0m8-7v-2a6 6 0 10-12 0v2l-1.293 1.293A1 1 0 006 17h12a1 1 0 00.707-1.707L18 14z" />
            </svg>

            <span
                v-if="urgent > 0"
                class="
                    absolute -top-1 -right-1
                    bg-red-600 text-white text-xs
                    rounded-full px-1.5
                "
            >
                {{ urgent }}
            </span>
        </button>

        <!-- Avatar -->
        <Link :href="route('profile.edit')">
            <div
                class="
                    w-9 h-9 rounded-full
                    bg-indigo-100
                    flex items-center justify-center
                    shadow cursor-pointer
                "
            >
                <img
                    v-if="user.profile_photo_url"
                    :src="user.profile_photo_url"
                    class="w-full h-full object-cover rounded-full"
                />
                <span
                    v-else
                    class="text-indigo-700 font-semibold text-sm"
                >
                    {{ initials(user.name) }}
                </span>
            </div>
        </Link>
    </div>
</header>

<!-- PRIORITY POPUP -->
<div
    v-if="showPopup"
    class="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm"
    @click.self="closePopup"
>
    <div
        class="
            absolute top-16 right-4 w-[420px]
            bg-white rounded-2xl shadow-2xl
            border overflow-hidden
            animate-fade-in
        "
    >
        <!-- HEADER -->
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span
                    class="h-2.5 w-2.5 rounded-full"
                    :class="{
                        'bg-red-500': activePriority === 'urgent',
                        'bg-yellow-500': activePriority === 'moderate',
                        'bg-green-500': activePriority === 'low',
                    }"
                />
                <h4 class="font-semibold capitalize text-gray-800">
                    {{ activePriority }} tasks
                </h4>
            </div>

            <button
                @click="closePopup"
                class="text-gray-400 hover:text-gray-700 transition"
            >
                ✕
            </button>
        </div>

        <!-- LIST -->
        <div class="max-h-[360px] overflow-y-auto">
            <div
                v-for="task in taskList"
                :key="task.id"
                class="
                    group px-5 py-3 cursor-pointer
                    border-l-4 border-transparent
                    hover:bg-gray-50
                    hover:border-indigo-500
                    transition
                "
                @click="goToTask(task)"
            >
                <div class="text-xs text-gray-500 mb-0.5">
                    {{ task.project }}
                </div>

                <div
                    class="
                        text-sm font-medium text-gray-800
                        group-hover:text-indigo-600
                        transition
                    "
                >
                    {{ task.title }}
                </div>
            </div>

            <!-- EMPTY STATE -->
            <div
                v-if="!taskList.length"
                class="px-6 py-12 text-center"
            >
                <div class="text-sm text-gray-400">
                    No {{ activePriority }} tasks 🎉
                </div>
            </div>
        </div>

        <!-- FOOTER (optional hint) -->
        <div class="px-5 py-3 border-t text-xs text-gray-400">
            Click a task to view details
        </div>
    </div>
</div>

</template>
