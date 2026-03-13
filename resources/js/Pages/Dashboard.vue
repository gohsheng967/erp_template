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

const totalAttention = computed(() => attention.value.critical.length + attention.value.warning.length)
const monthLabel = computed(() =>
    new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
)

const netClass = computed(() =>
    Number(cashflow.value.net) >= 0
        ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
        : 'text-rose-700 bg-rose-50 border-rose-200'
)

const liquidityRatio = computed(() => {
    const payables = Number(stats.value.payables || 0)
    if (payables <= 0) return null
    return Number(stats.value.receivables || 0) / payables
})

const trendTail = computed(() => {
    const labels = cashflow.value.trend.labels ?? []
    const incoming = cashflow.value.trend.incoming ?? []
    const outgoing = cashflow.value.trend.outgoing ?? []
    const size = Math.min(7, labels.length)

    return Array.from({ length: size }).map((_, index) => {
        const sourceIndex = labels.length - size + index
        return {
            label: labels[sourceIndex],
            incoming: Number(incoming[sourceIndex] ?? 0),
            outgoing: Number(outgoing[sourceIndex] ?? 0),
        }
    })
})

const peakIncoming = computed(() =>
    Math.max(0, ...(cashflow.value.trend.incoming ?? []).map((row) => Number(row || 0)))
)

const peakOutgoing = computed(() =>
    Math.max(0, ...(cashflow.value.trend.outgoing ?? []).map((row) => Number(row || 0)))
)

function miniBar(value, ceiling) {
    if (!ceiling || ceiling <= 0) return '2px'
    const height = Math.round((Number(value || 0) / ceiling) * 64)
    return `${Math.max(2, height)}px`
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-8 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.14),_transparent_40%),radial-gradient(circle_at_top_right,_rgba(16,185,129,0.12),_transparent_44%),linear-gradient(180deg,_rgba(248,250,252,0.95),_rgba(255,255,255,1))]">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">
                <section class="rounded-2xl border border-slate-200 bg-white/90 backdrop-blur shadow-sm overflow-hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-3">
                        <div class="lg:col-span-2 p-6 md:p-8 bg-[linear-gradient(120deg,rgba(15,23,42,0.96),rgba(30,41,59,0.92))] text-white">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-300">
                                Finance Operations
                            </p>
                            <h3 class="mt-2 text-2xl md:text-3xl font-semibold">
                                Performance Snapshot
                            </h3>
                            <p class="mt-2 text-sm text-slate-300">
                                {{ monthLabel }} summary across receivables, payables, cash position, and execution risk.
                            </p>

                            <div class="mt-5 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1 rounded-full border border-slate-600 bg-slate-700/50 px-3 py-1 text-xs">
                                    <i class="mdi mdi-alert-circle-outline"></i>
                                    {{ totalAttention }} alert(s)
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs" :class="netClass">
                                    <i class="mdi mdi-finance"></i>
                                    Net {{ formatCurrency(cashflow.net) }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full border border-slate-600 bg-slate-700/50 px-3 py-1 text-xs">
                                    <i class="mdi mdi-briefcase-outline"></i>
                                    {{ stats.active_projects }} active project(s)
                                </span>
                            </div>
                        </div>

                        <div class="p-6 md:p-8 bg-slate-50 border-t lg:border-t-0 lg:border-l border-slate-200">
                            <h4 class="text-sm font-semibold text-slate-800">Quick Actions</h4>
                            <div class="mt-4 space-y-2">
                                <Link
                                    :href="route('payment-slips.index')"
                                    class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-slate-300 hover:bg-slate-50"
                                >
                                    <span class="inline-flex items-center gap-2">
                                        <i class="mdi mdi-cash-multiple text-slate-500"></i>
                                        Payment Arrangements
                                    </span>
                                    <i class="mdi mdi-chevron-right text-slate-400"></i>
                                </Link>
                                <Link
                                    :href="route('claims.index')"
                                    class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-slate-300 hover:bg-slate-50"
                                >
                                    <span class="inline-flex items-center gap-2">
                                        <i class="mdi mdi-receipt-text-outline text-slate-500"></i>
                                        Claims Queue
                                    </span>
                                    <i class="mdi mdi-chevron-right text-slate-400"></i>
                                </Link>
                                <Link
                                    :href="route('projects.index')"
                                    class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-slate-300 hover:bg-slate-50"
                                >
                                    <span class="inline-flex items-center gap-2">
                                        <i class="mdi mdi-office-building-outline text-slate-500"></i>
                                        Project Control
                                    </span>
                                    <i class="mdi mdi-chevron-right text-slate-400"></i>
                                </Link>
                            </div>
                            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-600">
                                <div class="flex items-center justify-between">
                                    <span>Liquidity Ratio</span>
                                    <span class="font-semibold text-slate-800">
                                        {{ liquidityRatio === null ? '-' : `${liquidityRatio.toFixed(2)}x` }}
                                    </span>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500">
                                    Receivables divided by payables.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="absolute -top-10 -right-10 h-24 w-24 rounded-full bg-cyan-100/60 blur-2xl"></div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">Receivables</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ formatCurrency(stats.receivables) }}</div>
                        <div class="mt-3 inline-flex items-center gap-1 text-xs text-cyan-700">
                            <i class="mdi mdi-trending-up"></i> Expected cash in
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="absolute -top-10 -right-10 h-24 w-24 rounded-full bg-rose-100/60 blur-2xl"></div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">Payables</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ formatCurrency(stats.payables) }}</div>
                        <div class="mt-3 inline-flex items-center gap-1 text-xs text-rose-700">
                            <i class="mdi mdi-trending-down"></i> Scheduled cash out
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="absolute -top-10 -right-10 h-24 w-24 rounded-full bg-emerald-100/60 blur-2xl"></div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">Cash On Hand</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ formatCurrency(stats.cash_on_hand) }}</div>
                        <div class="mt-3 inline-flex items-center gap-1 text-xs text-emerald-700">
                            <i class="mdi mdi-wallet-outline"></i> Active petty cash wallets
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="absolute -top-10 -right-10 h-24 w-24 rounded-full bg-amber-100/60 blur-2xl"></div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">Active Projects</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ stats.active_projects }}</div>
                        <div class="mt-3 inline-flex items-center gap-1 text-xs text-amber-700">
                            <i class="mdi mdi-briefcase-outline"></i> Ongoing execution load
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-slate-800">Cashflow Snapshot</h3>
                                <p class="text-xs text-slate-500">{{ monthLabel }}</p>
                            </div>
                            <div class="inline-flex items-center gap-3 text-xs">
                                <span class="inline-flex items-center gap-1 text-emerald-700">
                                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                    Incoming
                                </span>
                                <span class="inline-flex items-center gap-1 text-rose-700">
                                    <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                                    Outgoing
                                </span>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs text-slate-500">Incoming</div>
                                    <div class="mt-1 text-lg font-semibold text-emerald-700">
                                        {{ formatCurrency(cashflow.incoming) }}
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs text-slate-500">Outgoing</div>
                                    <div class="mt-1 text-lg font-semibold text-rose-700">
                                        {{ formatCurrency(cashflow.outgoing) }}
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs text-slate-500">Net</div>
                                    <div class="mt-1 text-lg font-semibold" :class="cashflow.net >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                        {{ formatCurrency(cashflow.net) }}
                                    </div>
                                </div>
                            </div>

                            <div class="h-28 flex items-end gap-1.5">
                                <div
                                    v-for="(label, index) in cashflow.trend.labels"
                                    :key="`day-${label}-${index}`"
                                    class="flex flex-col items-center justify-end gap-1 w-2.5"
                                    :title="label"
                                >
                                    <div
                                        class="w-2.5 rounded-t bg-emerald-400/70"
                                        :style="{ height: barHeight(cashflow.trend.incoming[index] ?? 0) }"
                                    ></div>
                                    <div
                                        class="w-2.5 rounded-t bg-rose-400/70"
                                        :style="{ height: barHeight(cashflow.trend.outgoing[index] ?? 0) }"
                                    ></div>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                                <span>Start</span>
                                <span>End</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h3 class="text-base font-semibold text-slate-800">7-Day Pulse</h3>
                            <p class="text-xs text-slate-500">Most recent daily flow trend.</p>
                        </div>
                        <div class="p-5">
                            <div class="flex items-end justify-between gap-2 h-24">
                                <div
                                    v-for="day in trendTail"
                                    :key="`mini-${day.label}`"
                                    class="flex-1 min-w-0 flex items-end justify-center gap-1"
                                >
                                    <div class="w-2 rounded-t bg-emerald-400/70" :style="{ height: miniBar(day.incoming, Math.max(peakIncoming, peakOutgoing)) }"></div>
                                    <div class="w-2 rounded-t bg-rose-400/70" :style="{ height: miniBar(day.outgoing, Math.max(peakIncoming, peakOutgoing)) }"></div>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500">
                                <span v-for="day in trendTail" :key="`mini-label-${day.label}`" class="w-5 text-center">{{ day.label }}</span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                                <div class="rounded-lg border border-slate-100 bg-slate-50 p-2">
                                    <div class="text-slate-500">Peak Incoming</div>
                                    <div class="font-semibold text-emerald-700">{{ formatCurrency(peakIncoming) }}</div>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50 p-2">
                                    <div class="text-slate-500">Peak Outgoing</div>
                                    <div class="font-semibold text-rose-700">{{ formatCurrency(peakOutgoing) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 xl:grid-cols-5 gap-6">
                    <div class="xl:col-span-2 bg-white/90 backdrop-blur shadow-sm rounded-2xl border border-slate-200 overflow-hidden">
                        <div class="border-b border-slate-100 px-6 py-4 flex items-center gap-3 bg-gradient-to-r from-indigo-50/70 to-transparent">
                            <div class="h-9 w-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center ring-1 ring-indigo-100">
                                <i class="mdi mdi-history text-lg"></i>
                            </div>
                            <h3 class="text-base font-semibold text-slate-800">Recent Activity</h3>
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
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-slate-800">
                                        {{ item.message }}
                                    </p>
                                    <p class="text-xs text-slate-500">
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
                            class="px-6 py-4 text-sm text-slate-500"
                        >
                            No recent activity.
                        </div>
                    </div>

                    <div class="xl:col-span-3 bg-white/90 backdrop-blur shadow-sm rounded-2xl border border-slate-200 overflow-hidden">
                        <div class="border-b border-slate-100 px-6 py-4 flex items-center gap-3 bg-gradient-to-r from-amber-50/70 to-transparent">
                            <div class="h-9 w-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center ring-1 ring-amber-100">
                                <i class="mdi mdi-alert-circle-outline text-lg"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-semibold text-slate-800">
                                    Attention Required
                                </h3>
                                <p class="text-xs text-slate-500">
                                    {{ attention.critical.length }} critical, {{ attention.warning.length }} warning
                                </p>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <section v-if="attention.critical.length">
                                <h4 class="mb-3 text-sm font-semibold text-red-600 uppercase tracking-wide">
                                    Critical
                                </h4>

                                <ul class="space-y-2">
                                    <li
                                        v-for="(item, index) in attention.critical"
                                        :key="`critical-${index}`"
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

                            <section v-if="attention.warning.length">
                                <h4 class="mb-3 text-sm font-semibold text-yellow-600 uppercase tracking-wide">
                                    Warning
                                </h4>

                                <ul class="space-y-2">
                                    <li
                                        v-for="(item, index) in attention.warning"
                                        :key="`warning-${index}`"
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

                            <div
                                v-if="!attention.critical.length && !attention.warning.length"
                                class="rounded-xl border border-green-100/80 bg-green-50/70 px-4 py-3 text-sm text-green-700 shadow-sm flex items-center gap-2"
                            >
                                <i class="mdi mdi-check-circle-outline text-lg"></i>
                                No pending actions.
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
