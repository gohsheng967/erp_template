<script setup>
import { ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const company = page.props.company

const open = ref(true)
const settingsOpen = ref(false)

// Auto-close submenu when sidebar collapses
watch(open, (val) => {
    if (!val) settingsOpen.value = false
})

// MAIN MENU
const menu = [
    {
        name: "Dashboard",
        route: "dashboard",
        active: () => route().current("dashboard"),
        icon: `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 12l9-9 9 9M4.5 10.5v9A1.5 1.5 0 006 21h12a1.5 1.5 0 001.5-1.5v-9" />
            </svg>
        `
    }
]

// SETTINGS SUBMENU
const settingsMenu = [
    {
        name: "Company Profile",
        route: "company.profile",
        active: () => route().current("company.profile")
    },
    {
        name: "Department",
        route: "permissions.index",
        active: () => route().current("permissions.index")
    },
    {
        name: "Permission",
        route: "departments.index",
        active: () => route().current("departments.index")
    },
    {
        name: "User Management",
        route: "users.index",
        active: () => route().current("users.index")
    },
    
]
</script>

<template>
    <aside
        :class="[
            open ? 'w-56' : 'w-20',
            'backdrop-blur-xl bg-white/70 border-r border-white/40 h-screen transition-all duration-300 flex flex-col',
        ]"
        style="
            background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 50%, #e5e7eb 100%);
        "
    >
        <!-- LOGO TOGGLE -->
        <!-- Logo + Collapse Button -->
        <div
            class="flex items-center gap-3 px-4 py-4 cursor-pointer select-none border-b"
            @click="open = !open"
        >
            <!-- Dynamic Company Logo -->
            <img
                :src="company?.logo
                ? (company.logo.startsWith('http') ? company.logo : '/storage/' + company.logo)
                : '/asset/img/sample-logo.png'"
                class="h-10 w-10 object-contain rounded"
                alt="Company Logo"
            />

            <!-- Company Name (only when open) -->
            <span
                v-if="open"
                class="text-lg font-semibold text-gray-700 whitespace-nowrap"
            >
                {{ company?.name ?? 'Company' }}
            </span>
        </div>


        <!-- NAVIGATION -->
        <nav class="mt-4 flex-1 space-y-1">

            <!-- MAIN MENU ITEMS -->
            <Link
                v-for="item in menu"
                :key="item.name"
                :href="route(item.route)"
                class="flex items-center px-4 py-3 gap-3 hover:bg-gray-100 transition rounded-md"
                :class="[
                    !open ? 'justify-center' : '',
                    item.active() ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-700'
                ]"
            >
                <span v-html="item.icon"></span>
                <span v-if="open" class="text-sm">{{ item.name }}</span>
            </Link>

            <!-- SETTINGS WITH SUBMENU -->
            <div>
                <!-- SETTINGS BUTTON -->
                <div
                    class="flex items-center px-4 py-3 gap-3 hover:bg-gray-100 transition rounded-md cursor-pointer"
                    :class="{ 'justify-center': !open, 'bg-gray-200 font-semibold': settingsOpen }"
                    @click="settingsOpen = !settingsOpen"
                >
                    <!-- Cog Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.591 1.066c1.543-.89 3.318.884 2.428 2.428a1.724 1.724 0 001.066 2.59c1.756.426 1.756 2.925 0 3.351a1.724 1.724 0 00-1.066 2.591c.89 1.543-.885 3.318-2.428 2.428a1.724 1.724 0 00-2.59 1.066c-.426 1.756-2.925 1.756-3.351 0a1.724 1.724 0 00-2.591-1.066c-1.543.89-3.318-.885-2.428-2.428a1.724 1.724 0 00-1.066-2.59c-1.756-.426-1.756-2.925 0-3.351a1.724 1.724 0 001.066-2.59c-.89-1.544.885-3.319 2.428-2.429.922.533 2.086.097 2.591-1.066z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z" />
                    </svg>

                    <span v-if="open" class="text-sm">Settings</span>

                    <!-- Chevron -->
                    <svg
                        v-if="open"
                        :class="['h-4 w-4 ml-auto transition-transform', settingsOpen ? 'rotate-90' : '']"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 5l7 7-7 7" />
                    </svg>
                </div>

                <!-- SUBMENU ITEMS -->
                <transition name="fade">
                    <div v-if="settingsOpen && open" class="pl-14 mt-1 space-y-1">
                        <Link
                            v-for="item in settingsMenu"
                            :key="item.name"
                            :href="route(item.route)"
                            class="block text-sm py-1 hover:text-gray-900"
                            :class="item.active() ? 'text-gray-900 font-semibold' : 'text-gray-600'"
                        >
                            {{ item.name }}
                        </Link>
                    </div>
                </transition>
            </div>

        </nav>
    </aside>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
