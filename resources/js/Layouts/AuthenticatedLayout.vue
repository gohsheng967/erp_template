<script setup>
import { ref, inject, onMounted, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Sidebar from '@/Components/Layout/Sidebar.vue'
import TopBar from '@/Components/Layout/TopBar.vue'

const page = usePage()
const showBackupPopup = ref(false)
const toast = inject("toast", null)
const lockButtonPos = ref({ x: 0, y: 0 })
const lockButtonSuppressClick = ref(false)

const LOCK_BTN_SIZE = 56
const LOCK_BTN_MARGIN = 24
const LOCK_BTN_STORAGE_KEY = 'erp-lock-btn-position'

const dragState = {
    dragging: false,
    pointerId: null,
    offsetX: 0,
    offsetY: 0,
    startX: 0,
    startY: 0,
    moved: false,
}

function copyBackupCode() {
    const code = page.props.auth.user.data.backup_plain
    if (!code) return

    navigator.clipboard.writeText(code)
    toast?.value?.show("Backup code copied!", "success")
}

function clampLockButtonPosition(x, y) {
    const maxX = Math.max(LOCK_BTN_MARGIN, window.innerWidth - LOCK_BTN_SIZE - LOCK_BTN_MARGIN)
    const maxY = Math.max(LOCK_BTN_MARGIN, window.innerHeight - LOCK_BTN_SIZE - LOCK_BTN_MARGIN)

    return {
        x: Math.min(maxX, Math.max(LOCK_BTN_MARGIN, x)),
        y: Math.min(maxY, Math.max(LOCK_BTN_MARGIN, y)),
    }
}

function saveLockButtonPosition() {
    try {
        localStorage.setItem(LOCK_BTN_STORAGE_KEY, JSON.stringify(lockButtonPos.value))
    } catch (error) {
        // Ignore storage restrictions.
    }
}

function loadLockButtonPosition() {
    try {
        const saved = localStorage.getItem(LOCK_BTN_STORAGE_KEY)
        if (saved) {
            const parsed = JSON.parse(saved)
            if (typeof parsed?.x === 'number' && typeof parsed?.y === 'number') {
                lockButtonPos.value = clampLockButtonPosition(parsed.x, parsed.y)
                return
            }
        }
    } catch (error) {
        // Ignore invalid saved position.
    }

    lockButtonPos.value = clampLockButtonPosition(
        window.innerWidth - LOCK_BTN_SIZE - LOCK_BTN_MARGIN,
        window.innerHeight - LOCK_BTN_SIZE - LOCK_BTN_MARGIN
    )
}

function onPointerMove(event) {
    if (!dragState.dragging || event.pointerId !== dragState.pointerId) return

    const next = clampLockButtonPosition(
        event.clientX - dragState.offsetX,
        event.clientY - dragState.offsetY
    )
    lockButtonPos.value = next

    if (
        Math.abs(event.clientX - dragState.startX) > 4 ||
        Math.abs(event.clientY - dragState.startY) > 4
    ) {
        dragState.moved = true
    }
}

function stopDragging() {
    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerup', onPointerUp)
    window.removeEventListener('pointercancel', onPointerUp)
}

function onPointerUp(event) {
    if (!dragState.dragging || event.pointerId !== dragState.pointerId) return

    dragState.dragging = false
    dragState.pointerId = null
    stopDragging()
    saveLockButtonPosition()

    if (dragState.moved) {
        lockButtonSuppressClick.value = true
        setTimeout(() => {
            lockButtonSuppressClick.value = false
        }, 0)
    }
}

function startLockButtonDrag(event) {
    if (event.button !== undefined && event.button !== 0) return

    dragState.dragging = true
    dragState.pointerId = event.pointerId
    dragState.startX = event.clientX
    dragState.startY = event.clientY
    dragState.moved = false
    dragState.offsetX = event.clientX - lockButtonPos.value.x
    dragState.offsetY = event.clientY - lockButtonPos.value.y

    window.addEventListener('pointermove', onPointerMove)
    window.addEventListener('pointerup', onPointerUp)
    window.addEventListener('pointercancel', onPointerUp)
}

function handleLockButtonClick() {
    if (lockButtonSuppressClick.value) return
    showBackupPopup.value = true
}

function handleViewportResize() {
    lockButtonPos.value = clampLockButtonPosition(lockButtonPos.value.x, lockButtonPos.value.y)
    saveLockButtonPosition()
}

onMounted(() => {
    loadLockButtonPosition()
    window.addEventListener('resize', handleViewportResize)
})

onBeforeUnmount(() => {
    stopDragging()
    window.removeEventListener('resize', handleViewportResize)
})

</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-100">

        <!-- Sidebar -->
        <Sidebar />

        <!-- Main Section -->
        <div class="flex flex-1 flex-col overflow-hidden">

            <!-- Top Navigation Bar -->
            <TopBar />

            <!-- Page Header -->
            <header v-if="$slots.header" class="bg-white shadow sticky top-14 z-30">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <slot />
            </main>

        </div>
    </div>

    <!-- Floating Backup Button -->
    <button
        v-if="$page.props.auth.user.data.backup_plain"
        @click="handleLockButtonClick"
        @pointerdown="startLockButtonDrag"
        class="fixed z-50 bg-yellow-400 text-white rounded-full w-14 h-14 shadow-lg flex items-center justify-center text-xl hover:bg-yellow-700 transition touch-none select-none cursor-grab active:cursor-grabbing"
        :style="{ left: `${lockButtonPos.x}px`, top: `${lockButtonPos.y}px` }"
    >
        <img src="/asset/img/lock.png" class="w-8 h-8 object-contain" />
    </button>

    <!-- MFA Backup Popup -->
    <div
        v-if="showBackupPopup"
        class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50"
    >
        <div class="bg-white w-80 rounded-lg shadow-xl p-6 relative">

            <!-- Close -->
            <button
                @click="showBackupPopup = false"
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-700"
            >
                ✕
            </button>

            <h2 class="text-lg font-semibold text-center text-gray-800 mb-4">
                Your MFA Backup Code
            </h2>

            <div
                class="text-blue-600 font-mono text-xl text-center mb-4 select-all cursor-pointer hover:underline"
                @click="copyBackupCode"
            >
                {{ $page.props.auth.user.data.backup_plain }}
            </div>

            <p class="text-xs text-gray-500 text-center">
                Click the code to copy it. Keep it safe — this code allows login if you lose your phone.
            </p>
        </div>
    </div>
</template>
