<script setup>
import { ref, watch, reactive } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

// =============================
// PAGE DATA
// =============================
const page = usePage()
const company = page.props.company

// =============================
// SIDEBAR STATE
// =============================
const open = ref(true)
const settingsOpen = ref(false)

watch(open, (val) => {
    if (!val) {
        settingsOpen.value = false
        menu.forEach(m => { if (m.type === 'dropdown') m.open = false })
    }
})

// =============================
// SUBMENU DEFINITIONS
// =============================

// ---------------- DOCUMENT SUBMENU ----------------
const menuDocuments = [
    { name: "Document Types", route: "documents.types", active: () => route().current("documents.types") },
]

// ---------------- INVENTORY SUBMENU ----------------
const menuInventory = [
    { name: "Office Inventory", route: "inventory.office", active: () => route().current("inventory.office") },
    { name: "Operation Inventory", route: "inventory.operation", active: () => route().current("inventory.operation") }
]

// ---------------- TRANSACTIONS SUBMENU ----------------
const menuTransactions = [
    { name: "Purchase Order", route: "purchase.index", active: () => route().current("purchase.*") },
    { name: "Claims", route: "claims.index", active: () => route().current("claims.*") },
    { name: "Invoice", route: "invoice.index", active: () => route().current("invoice.*") },
]

// ---------------- STACKHOLDER SUBMENU ----------------
const menuStakeholders = [
    { name: "Clients", route: "clients.index", active: () => route().current("clients.*") },
    { name: "Supplier", route: "suppliers.index", active: () => route().current("suppliers.*") },
]

// ---------------- SETTINGS SUBMENU ----------------
const settingsMenu = [
    { name: "Company Profile", route: "company.profile", active: () => route().current("company.profile") },
    { name: "Department", route: "departments.index", active: () => route().current("departments.index") },
    { name: "File Categories", route: "file-categories.index", active: () => route().current("file-categories.index") },
    { name: "Permission", route: "permissions.index", active: () => route().current("permissions.index") },
    { name: "User Management", route: "users.index", active: () => route().current("users.index") },
]

// =============================
// MAIN MENU STRUCTURE
// =============================
const menu = reactive([
    {
        name: "Dashboard",
        route: "dashboard",
        type: "link",
        active: () => route().current("dashboard"),
        icon: `<i class="mdi mdi-view-dashboard-outline"></i>`,
    },

    {
        name: "Projects",
        route: "projects.index",
        type: "link",
        active: () => route().current("projects.*"),
        icon: `<i class="mdi mdi-clipboard-text-outline"></i>`,
    },

    // TRANSACTIONS
    {
        name: "Transactions",
        type: "dropdown",
        open: false,
        active: () =>
            route().current("purchase.*") ||
            route().current("claims.*") ||
            route().current("invoice.*"),
        icon: `<i class="mdi mdi-swap-horizontal"></i>`,
        children: menuTransactions,
    },

    // INVENTORY
    {
        name: "Inventory",
        type: "dropdown",
        open: false,
        active: () => route().current("inventory.*"),
        icon: `<i class="mdi mdi-warehouse"></i>`,
        children: menuInventory,
    },

    // Stakeholders
    {
        name: "Stakeholders",
        type: "dropdown",
        open: false,
        active: () => route().current("inventory.*"),
        icon: `<i class="mdi mdi-account-multiple-outline"></i>`,
        children: menuStakeholders,
    },


    // DOCUMENTS
    {
        name: "Documents",
        type: "dropdown",
        open: false,
        active: () => route().current("documents.*"),
        icon: `<i class="mdi mdi-file-document-outline"></i>`,
        children: menuDocuments,
    },
])
</script>

<!-- ============================= -->
<!-- TEMPLATE
<!-- ============================= -->
<template>
    <aside
        :class="[
            open ? 'w-56' : 'w-20',
            'backdrop-blur-xl bg-white/70 border-r border-white/40 h-screen transition-all duration-300 flex flex-col'
        ]"
        style="background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 50%, #e5e7eb 100%);"
    >

        <!-- LOGO -->
        <div class="flex items-center gap-3 px-4 py-4 cursor-pointer select-none border-b"
             @click="open = !open">
            <img
                :src="company?.logo
                    ? (company.logo.startsWith('http') ? company.logo : '/storage/' + company.logo)
                    : '/asset/img/sample-logo.png'"
                class="h-10 w-10 object-contain rounded"
            />

            <span v-if="open" class="text-lg font-semibold text-gray-700 whitespace-nowrap">
                {{ company?.name ?? 'INFINITE' }}
            </span>
        </div>

        <!-- MAIN MENU -->
        <nav class="mt-4 flex-1 space-y-1">
            <div v-for="item in menu" :key="item.name">

                <!-- DROPDOWN ITEM -->
                <template v-if="item.type === 'dropdown'">
                    <div
                        class="flex items-center px-4 py-3 gap-3 hover:bg-gray-100 transition rounded-md cursor-pointer"
                        :class="[
                            !open ? 'justify-center' : '',
                            item.active() ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-700'
                        ]"
                        @click="item.open = !item.open"
                    >
                        <span v-html="item.icon"></span>
                        <span v-if="open" class="text-sm">{{ item.name }}</span>

                        <svg
                            v-if="open"
                            :class="['h-4 w-4 ml-auto transition-transform', item.open ? 'rotate-90' : '']"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>

                    <transition name="fade">
                        <div v-if="item.open && open" class="pl-14 mt-1 space-y-1">
                            <Link
                                v-for="child in item.children"
                                :key="child.name"
                                :href="route(child.route)"
                                class="block text-sm py-1 hover:text-gray-900"
                                :class="child.active() ? 'text-gray-900 font-semibold' : 'text-gray-600'"
                            >
                                {{ child.name }}
                            </Link>
                        </div>
                    </transition>
                </template>

                <!-- SINGLE LINK -->
                <template v-else>
                    <Link
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
                </template>

            </div>

            <!-- SETTINGS -->
            <div>
                <div
                    class="flex items-center px-4 py-3 gap-3 hover:bg-gray-100 transition rounded-md cursor-pointer"
                    :class="{ 'justify-center': !open, 'bg-gray-200 font-semibold': settingsOpen }"
                    @click="settingsOpen = !settingsOpen"
                >
                    <i class="mdi mdi-cogs"></i>
                    <span v-if="open" class="text-sm">Settings</span>

                    <svg
                        v-if="open"
                        :class="['h-4 w-4 ml-auto transition-transform', settingsOpen ? 'rotate-90' : '']"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </div>

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

        <!-- LOGOUT -->
        <button
            @click.prevent="$inertia.post(route('logout'))"
            class="w-full flex items-center gap-3 px-4 py-3 bg-red-50 text-red-700 border border-red-200
                rounded-md hover:bg-red-100 transition"
            :class="{ 'justify-center': !open }"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 009 21h6a2.25 2.25 0 002.25-2.25V15m-6 0l3-3m0 0l-3-3m3 3H3" />
            </svg>

            <span v-if="open" class="text-sm font-semibold">Logout</span>
        </button>

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
