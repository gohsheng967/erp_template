<script setup>
import { ref, watch, reactive, computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const company = page.props.company
const SIDEBAR_OPEN_KEY = 'erp-sidebar-open'

const open = ref(true)
const settingsOpen = ref(false)

const menuInventory = [
    { name: 'Vehicles', route: 'inventory.vehicles.index', active: () => route().current('inventory.vehicles.index') },
    { name: 'Stock', route: 'inventory.stocks.index', active: () => route().current('inventory.stocks.index') },
]

const menuTransactions = [
    { name: 'Claims', route: 'claims.index', active: () => route().current('claims.*') },
    { name: 'Petty Cash', route: 'petty-cash.index', active: () => route().current('petty-cash.*') },
    { name: 'AR Invoice', route: 'ar-invoices.index', active: () => route().current('ar-invoices.*') },
    { name: 'Purchase Request', route: 'purchase-request.index', active: () => route().current('purchase-request.*') },
    { name: 'Delivery', route: 'deliveries.index', active: () => route().current('deliveries.*') },
    { name: 'Payment Slips', route: 'payment-slips.index', active: () => route().current('payment-slips.*') },
]

const menuStakeholders = [
    { name: 'Clients', route: 'clients.index', active: () => route().current('clients.*') },
    { name: 'Suppliers', route: 'suppliers.index', active: () => route().current('suppliers.*') },
    { name: 'Sub Con', route: 'sub-cons.index', active: () => route().current('sub-cons.*') },
    { name: 'Staff', route: 'users.index', active: () => route().current('users.index') },
]

const settingsMenu = [
    { name: 'Company Profile', route: 'company.profile', active: () => route().current('company.profile') },
    { name: 'Sites', route: 'sites.index', active: () => route().current('sites.*') },
    { name: 'Stock Categories', route: 'stock-categories.index', active: () => route().current('stock-categories.*') },
    { name: 'Warehouse', route: 'warehouses.index', active: () => route().current('warehouses.index') },
    { name: 'File Categories', route: 'file-categories.index', active: () => route().current('file-categories.index') },
    { name: 'Claim Types', route: 'claim-types.index', active: () => route().current('claim-types.*') },
]

const menu = reactive([
    {
        name: 'Dashboard',
        route: 'dashboard',
        type: 'link',
        active: () => route().current('dashboard'),
        icon: '<i class="mdi mdi-view-dashboard-outline"></i>',
    },
    {
        name: 'Projects',
        route: 'projects.index',
        type: 'link',
        active: () => route().current('projects.*'),
        icon: '<i class="mdi mdi-clipboard-text-outline"></i>',
    },
    {
        name: 'Transactions',
        type: 'dropdown',
        open: false,
        active: () =>
            route().current('purchase-request.*') ||
            route().current('purchase.*') ||
            route().current('claims.*') ||
            route().current('petty-cash.*') ||
            route().current('invoice.*') ||
            route().current('deliveries.*') ||
            route().current('payment-slips.*'),
        icon: '<i class="mdi mdi-swap-horizontal"></i>',
        children: menuTransactions,
    },
    {
        name: 'Inventory',
        type: 'dropdown',
        open: false,
        active: () => route().current('inventory.*'),
        icon: '<i class="mdi mdi-warehouse"></i>',
        children: menuInventory,
    },
    {
        name: 'Stakeholders',
        type: 'dropdown',
        open: false,
        active: () => route().current('suppliers.*') || route().current('clients.*') || route().current('sub-cons.*'),
        icon: '<i class="mdi mdi-account-multiple-outline"></i>',
        children: menuStakeholders,
    },
])

const isSettingsActive = computed(() => settingsMenu.some((item) => item.active()))

watch(open, (val) => {
    try {
        localStorage.setItem(SIDEBAR_OPEN_KEY, val ? '1' : '0')
    } catch (error) {
        // Ignore storage restrictions.
    }

    if (!val) {
        settingsOpen.value = false
        menu.forEach((item) => {
            if (item.type === 'dropdown') {
                item.open = false
            }
        })
    }
})

function syncActiveSections() {
    if (!open.value) return

    let foundActiveDropdown = false
    menu.forEach((item) => {
        if (item.type !== 'dropdown') return

        if (!foundActiveDropdown && item.active()) {
            item.open = true
            foundActiveDropdown = true
            return
        }

        item.open = false
    })

    settingsOpen.value = !foundActiveDropdown && isSettingsActive.value
}

function toggleSidebar() {
    open.value = !open.value
    if (open.value) {
        syncActiveSections()
    }
}

function toggleDropdown(targetItem) {
    if (!open.value) {
        open.value = true
        return
    }

    const next = !targetItem.open
    settingsOpen.value = false

    menu.forEach((item) => {
        if (item.type === 'dropdown') {
            item.open = false
        }
    })

    targetItem.open = next
}

function toggleSettings() {
    if (!open.value) {
        open.value = true
        return
    }

    const next = !settingsOpen.value

    menu.forEach((item) => {
        if (item.type === 'dropdown') {
            item.open = false
        }
    })

    settingsOpen.value = next
}

onMounted(() => {
    try {
        const saved = localStorage.getItem(SIDEBAR_OPEN_KEY)
        if (saved === '0') {
            open.value = false
        } else if (saved === '1') {
            open.value = true
        }
    } catch (error) {
        // Ignore storage restrictions.
    }

    syncActiveSections()
})
</script>

<template>
    <aside
        :class="[
            open ? 'w-64' : 'w-[86px]',
            'h-screen min-h-0 shrink-0 border-r border-slate-200/70 bg-gradient-to-b from-white via-slate-50 to-slate-100 transition-all duration-300 ease-out flex flex-col shadow-sm'
        ]"
    >
        <div class="border-b border-slate-200/80 px-3 py-3">
            <div class="flex items-center gap-3">
                <img
                    :src="company?.logo
                        ? (company.logo.startsWith('http') ? company.logo : '/storage/' + company.logo)
                        : '/asset/img/sample-logo.png'"
                    class="h-10 w-10 rounded-lg border border-slate-200 bg-white p-1 object-contain"
                />

                <div v-if="open" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ company?.name ?? 'INFINITE' }}</p>
                    <p class="text-xs text-slate-500">ERP System</p>
                </div>

                <button
                    type="button"
                    @click="toggleSidebar"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                    :title="open ? 'Collapse sidebar' : 'Expand sidebar'"
                >
                    <i :class="open ? 'mdi mdi-chevron-left' : 'mdi mdi-chevron-right'" class="text-lg"></i>
                </button>
            </div>
        </div>

        <nav class="sidebar-scrollbar mt-2 flex-1 min-h-0 space-y-1 overflow-y-auto px-2 pb-3">
            <p v-if="open" class="px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Main</p>

            <div v-for="item in menu" :key="item.name">
                <template v-if="item.type === 'dropdown'">
                    <button
                        type="button"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition"
                        :class="[
                            !open ? 'justify-center' : '',
                            item.active()
                                ? 'bg-indigo-50 text-indigo-700'
                                : 'text-slate-700 hover:bg-white hover:text-slate-900'
                        ]"
                        :title="!open ? item.name : ''"
                        @click="toggleDropdown(item)"
                    >
                        <span class="text-lg" v-html="item.icon"></span>
                        <span v-if="open" class="text-sm font-medium">{{ item.name }}</span>
                        <i
                            v-if="open"
                            class="mdi mdi-chevron-right ml-auto text-base transition-transform"
                            :class="item.open ? 'rotate-90 text-indigo-600' : 'text-slate-400'"
                        ></i>
                    </button>

                    <transition name="fade">
                        <div v-if="item.open && open" class="ml-4 mt-1 space-y-1 border-l border-slate-200 pl-4">
                            <Link
                                v-for="child in item.children"
                                :key="child.name"
                                :href="route(child.route)"
                                class="block rounded-md px-2 py-1.5 text-sm transition"
                                :class="child.active()
                                    ? 'bg-white font-semibold text-slate-900 shadow-sm'
                                    : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                            >
                                {{ child.name }}
                            </Link>
                        </div>
                    </transition>
                </template>

                <template v-else>
                    <Link
                        :href="route(item.route)"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 transition"
                        :class="[
                            !open ? 'justify-center' : '',
                            item.active()
                                ? 'bg-indigo-50 text-indigo-700'
                                : 'text-slate-700 hover:bg-white hover:text-slate-900'
                        ]"
                        :title="!open ? item.name : ''"
                    >
                        <span class="text-lg" v-html="item.icon"></span>
                        <span v-if="open" class="text-sm font-medium">{{ item.name }}</span>
                    </Link>
                </template>
            </div>

            <p v-if="open" class="px-3 pt-4 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Administration</p>

            <div>
                <button
                    type="button"
                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition"
                    :class="[
                        !open ? 'justify-center' : '',
                        isSettingsActive || settingsOpen
                            ? 'bg-indigo-50 text-indigo-700'
                            : 'text-slate-700 hover:bg-white hover:text-slate-900'
                    ]"
                    :title="!open ? 'Settings' : ''"
                    @click="toggleSettings"
                >
                    <i class="mdi mdi-cog-outline text-lg"></i>
                    <span v-if="open" class="text-sm font-medium">Settings</span>
                    <i
                        v-if="open"
                        class="mdi mdi-chevron-right ml-auto text-base transition-transform"
                        :class="settingsOpen ? 'rotate-90 text-indigo-600' : 'text-slate-400'"
                    ></i>
                </button>

                <transition name="fade">
                    <div v-if="settingsOpen && open" class="ml-4 mt-1 space-y-1 border-l border-slate-200 pl-4">
                        <Link
                            v-for="item in settingsMenu"
                            :key="item.name"
                            :href="route(item.route)"
                            class="block rounded-md px-2 py-1.5 text-sm transition"
                            :class="item.active()
                                ? 'bg-white font-semibold text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                        >
                            {{ item.name }}
                        </Link>
                    </div>
                </transition>
            </div>
        </nav>

        <div class="shrink-0 border-t border-slate-200/80 p-2">
            <button
                @click.prevent="$inertia.post(route('logout'))"
                class="flex w-full items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-red-700 transition hover:bg-red-100"
                :class="{ 'justify-center': !open }"
                :title="!open ? 'Logout' : ''"
            >
                <i class="mdi mdi-logout text-lg"></i>
                <span v-if="open" class="text-sm font-semibold">Logout</span>
            </button>
        </div>
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

.sidebar-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.sidebar-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.sidebar-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 999px;
}
</style>
