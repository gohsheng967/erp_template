<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useFormat } from '@/Composables/useFormat'

const page = usePage()
const { formatCurrency } = useFormat()

const attention = computed(() => page.props.attention ?? {
    critical: [],
    warning: [],
})

const stats = computed(() => page.props.stats ?? {
    receivables: 0,
    payables: 0,
    cash_on_hand: 0,
    active_projects: 0,
})

const cashflow = computed(() => page.props.cashflow ?? {
    incoming: 0,
    outgoing: 0,
    net: 0,
    trend: {
        labels: [],
        incoming: [],
        outgoing: [],
    },
})

const recentActivity = computed(() => page.props.recentActivity ?? [])

const cashflowMax = computed(() => {
    const incoming = cashflow.value.trend.incoming ?? []
    const outgoing = cashflow.value.trend.outgoing ?? []
    return Math.max(1, ...incoming, ...outgoing)
})

function barHeight(value) {
    const height = Math.round((Number(value) / cashflowMax.value) * 48)
    return `${Math.max(2, height)}px`
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <!-- HEADER -->
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-8 bg-[radial-gradient(circle_at_top,_rgba(148,163,184,0.18),_rgba(255,255,255,0)_45%),linear-gradient(180deg,_rgba(248,250,252,0.9),_rgba(255,255,255,1))]">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-10">

                <!-- =============================
                     QUICK STATS
                ============================== -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="relative overflow-hidden bg-white/90 backdrop-blur shadow-sm rounded-2xl border border-slate-100 p-4 flex items-center gap-3 transition hover:shadow-md">
                        <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-indigo-50/60 blur-2xl"></div>
                        <div class="h-10 w-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center ring-1 ring-indigo-100">
                            <i class="mdi mdi-file-document-outline text-lg"></i>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Receivables</div>
                            <div class="text-lg font-semibold text-gray-900">
                                {{ formatCurrency(stats.receivables) }}
                            </div>
                        </div>
                    </div>

                    <div class="relative overflow-hidden bg-white/90 backdrop-blur shadow-sm rounded-2xl border border-slate-100 p-4 flex items-center gap-3 transition hover:shadow-md">
                        <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-rose-50/60 blur-2xl"></div>
                        <div class="h-10 w-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center ring-1 ring-rose-100">
                            <i class="mdi mdi-cash-minus text-lg"></i>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Payables</div>
                            <div class="text-lg font-semibold text-gray-900">
                                {{ formatCurrency(stats.payables) }}
                            </div>
                        </div>
                    </div>

                    <div class="relative overflow-hidden bg-white/90 backdrop-blur shadow-sm rounded-2xl border border-slate-100 p-4 flex items-center gap-3 transition hover:shadow-md">
                        <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-emerald-50/60 blur-2xl"></div>
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center ring-1 ring-emerald-100">
                            <i class="mdi mdi-wallet-outline text-lg"></i>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Cash on Hand</div>
                            <div class="text-lg font-semibold text-gray-900">
                                {{ formatCurrency(stats.cash_on_hand) }}
                            </div>
                        </div>
                    </div>

                    <div class="relative overflow-hidden bg-white/90 backdrop-blur shadow-sm rounded-2xl border border-slate-100 p-4 flex items-center gap-3 transition hover:shadow-md">
                        <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-amber-50/60 blur-2xl"></div>
                        <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center ring-1 ring-amber-100">
                            <i class="mdi mdi-briefcase-outline text-lg"></i>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Active Projects</div>
                            <div class="text-lg font-semibold text-gray-900">
                                {{ stats.active_projects }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- =============================
                     CASHFLOW SNAPSHOT
                ============================== -->
                <div class="bg-white/90 backdrop-blur shadow-sm sm:rounded-2xl border border-slate-100 overflow-hidden">
                    <div class="border-b border-slate-100 px-6 py-4 flex items-center gap-3 bg-gradient-to-r from-emerald-50/70 to-transparent">
                        <div class="h-9 w-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center ring-1 ring-emerald-100">
                            <i class="mdi mdi-chart-line-variant text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-800">
                                Cashflow Snapshot
                            </h3>
                            <p class="text-xs text-gray-500">
                                This month
                            </p>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="space-y-3 rounded-xl border border-slate-100 bg-white/80 px-4 py-3 shadow-sm">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Incoming</span>
                                <span class="font-semibold text-emerald-600">
                                    {{ formatCurrency(cashflow.incoming) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Outgoing</span>
                                <span class="font-semibold text-rose-600">
                                    {{ formatCurrency(cashflow.outgoing) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Net</span>
                                <span
                                    class="font-semibold"
                                    :class="cashflow.net >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                >
                                    {{ formatCurrency(cashflow.net) }}
                                </span>
                            </div>
                        </div>

                        <div class="lg:col-span-2 rounded-xl border border-slate-100 bg-white/80 px-4 py-3 shadow-sm">
                            <div class="h-24 flex items-end gap-1">
                                <div
                                    v-for="(label, index) in cashflow.trend.labels"
                                    :key="`day-${label}-${index}`"
                                    class="flex flex-col items-center justify-end gap-1 w-2"
                                    :title="label"
                                >
                                    <div
                                        class="w-2 rounded-t bg-emerald-400/60"
                                        :style="{ height: barHeight(cashflow.trend.incoming[index] ?? 0) }"
                                    ></div>
                                    <div
                                        class="w-2 rounded-t bg-rose-400/60"
                                        :style="{ height: barHeight(cashflow.trend.outgoing[index] ?? 0) }"
                                    ></div>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-xs text-gray-400">
                                <span>Start</span>
                                <span>End</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- =============================
                     RECENT ACTIVITY
                ============================== -->
                <div class="bg-white/90 backdrop-blur shadow-sm sm:rounded-2xl border border-slate-100 overflow-hidden">
                    <div class="border-b border-slate-100 px-6 py-4 flex items-center gap-3 bg-gradient-to-r from-indigo-50/70 to-transparent">
                        <div class="h-9 w-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center ring-1 ring-indigo-100">
                            <i class="mdi mdi-history text-lg"></i>
                        </div>
                        <h3 class="text-base font-semibold text-gray-800">
                            Recent Activity
                        </h3>
                    </div>

                    <div v-if="recentActivity.length" class="divide-y divide-slate-100">
                        <div
                            v-for="item in recentActivity"
                            :key="item.id"
                            class="px-6 py-4 flex items-start gap-3 hover:bg-slate-50/60 transition"
                        >
                            <div class="h-8 w-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                                <i class="mdi text-lg" :class="item.icon"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-800">
                                    {{ item.message }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ item.time }}
                                </p>
                            </div>
                            <Link
                                v-if="item.route"
                                :href="route(item.route, item.params ?? {})"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                            >
                                View
                            </Link>
                        </div>
                    </div>

                    <div
                        v-else
                        class="px-6 py-4 text-sm text-gray-500"
                    >
                        No recent activity.
                    </div>
                </div>

                <!-- =============================
                     ATTENTION REQUIRED
                ============================== -->
                <div class="bg-white/90 backdrop-blur shadow-sm sm:rounded-2xl border border-slate-100 overflow-hidden">
                    <div class="border-b border-slate-100 px-6 py-4 flex items-center gap-3 bg-gradient-to-r from-amber-50/70 to-transparent">
                        <div class="h-9 w-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center ring-1 ring-amber-100">
                            <i class="mdi mdi-alert-circle-outline text-lg"></i>
                        </div>
                        <h3 class="text-base font-semibold text-gray-800">
                            Attention Required
                        </h3>
                    </div>

                    <div class="p-6 space-y-6">

                        <!-- CRITICAL -->
                        <section v-if="attention.critical.length">
                            <h4 class="mb-3 text-sm font-semibold text-red-600 uppercase tracking-wide">
                                Critical
                            </h4>

                            <ul class="space-y-2">
                                <li
                                    v-for="(item, index) in attention.critical"
                                    :key="'critical-' + index"
                                    class="flex items-center justify-between rounded-xl border border-red-100/80 bg-red-50/70 px-4 py-3 shadow-sm"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 h-8 w-8 rounded-full bg-red-100 text-red-700 flex items-center justify-center ring-1 ring-red-100">
                                            <i
                                                class="mdi text-lg"
                                                :class="item.icon ?? 'mdi-alert-octagon-outline'"
                                            ></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-red-800">
                                                {{ item.message }}
                                            </p>
                                            <p class="text-xs text-red-600">
                                                Immediate attention required
                                            </p>
                                        </div>
                                    </div>

                                    <Link
                                        :href="route(item.route, item.params ?? {})"
                                        class="text-sm font-semibold text-red-700 hover:text-red-900 inline-flex items-center gap-1"
                                    >
                                        View
                                        <i class="mdi mdi-chevron-right"></i>
                                    </Link>
                                </li>
                            </ul>
                        </section>

                        <!-- WARNING -->
                        <section v-if="attention.warning.length">
                            <h4 class="mb-3 text-sm font-semibold text-yellow-600 uppercase tracking-wide">
                                Warning
                            </h4>

                            <ul class="space-y-2">
                                <li
                                    v-for="(item, index) in attention.warning"
                                    :key="'warning-' + index"
                                    class="flex items-center justify-between rounded-xl border border-yellow-100/80 bg-yellow-50/70 px-4 py-3 shadow-sm"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 h-8 w-8 rounded-full bg-yellow-100 text-yellow-700 flex items-center justify-center ring-1 ring-yellow-100">
                                            <i
                                                class="mdi text-lg"
                                                :class="item.icon ?? 'mdi-alert-outline'"
                                            ></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-yellow-800">
                                                {{ item.message }}
                                            </p>
                                            <p class="text-xs text-yellow-700">
                                                Action recommended
                                            </p>
                                        </div>
                                    </div>

                                    <Link
                                        :href="route(item.route, item.params ?? {})"
                                        class="text-sm font-semibold text-yellow-700 hover:text-yellow-900 inline-flex items-center gap-1"
                                    >
                                        View
                                        <i class="mdi mdi-chevron-right"></i>
                                    </Link>
                                </li>
                            </ul>
                        </section>

                        <!-- EMPTY -->
                        <div
                            v-if="!attention.critical.length && !attention.warning.length"
                            class="rounded-xl border border-green-100/80 bg-green-50/70 px-4 py-3 text-sm text-green-700 shadow-sm flex items-center gap-2"
                        >
                            <i class="mdi mdi-check-circle-outline text-lg"></i>
                            No pending actions.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
