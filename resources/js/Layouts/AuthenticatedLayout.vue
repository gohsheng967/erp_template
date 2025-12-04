<script setup>
import { ref, provide } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Sidebar from '@/Components/Layout/Sidebar.vue'
import TopBar from '@/Components/Layout/TopBar.vue'
import Toast from '@/Components/Toast.vue'

const page = usePage()
const showBackupPopup = ref(false)
const toastRef = ref(null)

function copyBackupCode() {
    const code = page.props.auth.user.data.backup_plain
    if (!code) return

    navigator.clipboard.writeText(code)
    toastRef.value.show("Backup code copied!", "success")
}

provide("toast", toastRef)
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
        @click="showBackupPopup = true"
        class="fixed bottom-6 right-6 z-50 bg-yellow-400 text-white rounded-full w-14 h-14 shadow-lg flex items-center justify-center text-xl hover:bg-yellow-700 transition"
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

    <Toast ref="toastRef" />
</template>
