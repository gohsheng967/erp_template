<script setup>
import { ref, onMounted, computed, inject } from "vue";
import { useFormat } from '@/Composables/useFormat'
import axios from 'axios'
import {
    Chart,
    ArcElement,
    Tooltip,
    Legend,
    DoughnutController
} from "chart.js";

Chart.register(
    ArcElement,
    DoughnutController,
    Tooltip,
    Legend
);

const props = defineProps({
    project: Object
});

const { capitalize, formatCurrency } = useFormat()
const toast = inject('toast', null)

const timelineProgress = computed(() => {
    if (!props.project.start_date || !props.project.end_date) {
        return 0
    }

    const start = new Date(props.project.start_date)
    const end = new Date(props.project.end_date)
    const now = new Date()

    if (now <= start) return 0
    if (now >= end) return 100

    const total = end.getTime() - start.getTime()
    const elapsed = now.getTime() - start.getTime()

    return Math.round((elapsed / total) * 100)
})

const kpi = ref({
    budget: {
        total: 0,
        used: 0,
        remaining: 0,
        percentage: 0,
    },
    top_costs: [],
    recent_activities: [],
})

const kpiLoading = ref(true)
const animateUsed = ref(0);
const animateRemaining = ref(0);
const animatePercentage = ref(0);

async function loadKpi() {
    kpiLoading.value = true

    const { data } = await axios.get(
        route('projects.overview.kpi', props.project.id)
    )

    kpi.value = data

    animateNumber(data.budget.used, animateUsed)
    animateNumber(data.budget.remaining, animateRemaining)
    animateNumber(data.budget.percentage, animatePercentage)

    renderBudgetDoughnut()

    kpiLoading.value = false
}



// memo popup
const memo = ref("");
const showMemoPopup = ref(false);

// chart refs
const doughnutRef = ref(null);
let doughnutChart = null


// animate numbers
function animateNumber(target, refVar, duration = 1200) {
    let start = 0;
    const step = (timestamp) => {
        if (!start) start = timestamp;
        const progress = Math.min((timestamp - start) / duration, 1);
        refVar.value = Math.floor(progress * target);
        if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

function saveMemo() {
    showMemoPopup.value = false;
    toast?.value?.show('Memo Saved', 'success')
}


function renderBudgetDoughnut() {
    if (!doughnutRef.value) return

    if (doughnutChart) {
        doughnutChart.destroy()
    }

    const po = kpi.value.breakdown.purchase_orders || 0
    const claim = kpi.value.breakdown.claims || 0
    const remaining = kpi.value.budget.remaining || 0

    doughnutChart = new Chart(doughnutRef.value, {
        type: "doughnut",
        data: {
            labels: [
                "Purchase Orders (Committed)",
                "Claims (Spent)",
                "Remaining Budget",
            ],
            datasets: [
                {
                    data: [po, claim, remaining],
                    backgroundColor: [
                        "#6366f1", // indigo → PO
                        "#f59e0b", // amber → Claim
                        "#e5e7eb", // gray → Remaining
                    ],
                    borderWidth: 2,
                    hoverOffset: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: "65%",
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const value = ctx.raw ?? 0
                            return `${ctx.label}: RM ${value.toLocaleString()}`
                        }
                    }
                }
            },
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 800,
                easing: "easeOutCubic"
            }
        }
    })
}



onMounted(() => {
    loadKpi()
});
</script>

<template>
    <div class="space-y-10 animate-fadeIn">

        <div class="bg-white shadow-md rounded-xl p-6 border relative">
            <button
                @click="showMemoPopup = true"
                class="absolute top-4 right-4 transition transform hover:scale-125 animate-shinyStar"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    class="w-10 h-10 text-yellow-400"
                >
                    <path d="M12 2.5l2.9 6.1 6.6.9-4.8 4.7 1.1 6.5L12 17.8l-5.8 3 1.1-6.5-4.8-4.7 6.6-.9L12 2.5z"/>
                </svg>
            </button>

            <h3 class="text-lg font-semibold mb-2">Project Timeline</h3>
            <p class="text-gray-600">
                {{ props.project.start_date }} → {{ props.project.end_date }}
            </p>

            <div class="w-full bg-gray-200 rounded-full h-3 mt-5 overflow-hidden">
                <div
                    class="h-3 bg-green-500 rounded-full transition-all duration-[1200ms] ease-out"
                    :style="{ width: timelineProgress + '%' }"
                ></div>
            </div>

            <p class="text-gray-500 text-sm mt-2">
                Progress: {{ timelineProgress }}%
            </p>
        </div>

        <!-- 2️⃣ KPI CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">

            <div class="bg-white shadow-md rounded-xl p-6 border hover:shadow-lg transition">
                <p class="text-gray-500 text-sm">Total Budget</p>
                <p class="text-3xl font-bold mt-1">
                    {{ formatCurrency(kpi.budget.total) }}
                </p>

            </div>

            <div class="bg-white shadow-md rounded-xl p-6 border hover:shadow-lg transition">
                <p class="text-gray-500 text-sm">Used</p>
                <p class="text-3xl font-bold mt-1 text-indigo-600">
                    {{ formatCurrency(animateUsed) }}
                </p>
            </div>

            <div class="bg-white shadow-md rounded-xl p-6 border hover:shadow-lg transition">
                <p class="text-gray-500 text-sm">Remaining</p>
                <p class="text-3xl font-bold mt-1 text-green-600">
                    RM {{ animateRemaining.toLocaleString() }}
                </p>
            </div>

            <div class="bg-white shadow-md rounded-xl p-6 border hover:shadow-lg transition">
                <p class="text-gray-500 text-sm">Usage %</p>
                <p class="text-2xl font-bold mt-1">
                    {{ animatePercentage }}%
                </p>

                <div class="w-full bg-gray-200 rounded-full h-2 mt-3 overflow-hidden">
                    <div
                        class="h-2 bg-indigo-500 rounded-full transition-all duration-[1200ms]"
                        :style="{ width: animatePercentage + '%' }"
                    ></div>
                </div>
            </div>

        </div>

        <!-- 3️⃣ CHARTS -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <div class="bg-white shadow-md rounded-xl p-6 border">
                <h3 class="text-lg font-semibold mb-4">Budget Breakdown</h3>
                <div class="h-[400px]">
                    <canvas ref="doughnutRef"></canvas>
                </div>
            </div>
            <div class="bg-white shadow-md rounded-xl p-6 border">
                <h3 class="text-lg font-semibold mb-4">
                    Top Cost Drivers
                </h3>

                <div v-if="kpi.top_costs.length === 0" class="text-gray-500">
                    No cost records yet
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="(item, index) in kpi.top_costs"
                        :key="item.id"
                        class="flex justify-between items-center"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-gray-400 font-mono">
                                {{ index + 1 }}.
                            </span>

                            <div>
                                <p class="font-semibold text-gray-800 truncate max-w-[260px]">
                                    {{ item.label }}
                                </p>
                                <p class="text-xs text-gray-500 capitalize">
                                    {{ item.type }}
                                </p>
                            </div>
                        </div>

                        <p class="font-bold text-indigo-600">
                            {{ formatCurrency(item.amount) }}
                        </p>
                    </div>
                </div>
            </div>

        </div>

        <!-- 5️⃣ RECENT ACTIVITIES -->
        <div class="bg-white shadow-md rounded-xl p-6 border">
            <h3 class="text-lg font-semibold mb-4">Recent Activities</h3>

            <div v-if="kpi.recent_activities.length === 0" class="text-gray-500">
                No recent activity
            </div>

            <div v-else class="space-y-5">
                <div
                    v-for="activity in kpi.recent_activities"
                    :key="activity.id"
                    class="flex items-start gap-3"
                >
                    <div class="w-2 h-2 bg-indigo-500 rounded-full mt-2"></div>

                    <div>
                        <p class="font-semibold text-gray-800">
                            {{ activity.message }}
                        </p>
                        <p class="text-gray-500 text-sm">
                            {{ activity.time_ago }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- MEMO POPUP -->
        <div
            v-if="showMemoPopup"
            class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50"
        >
            <div class="bg-white shadow-xl rounded-xl p-6 w-full max-w-lg">
                <h3 class="text-xl font-semibold mb-4">Project Memo</h3>

                <textarea
                    v-model="memo"
                    class="w-full border rounded-lg p-3 h-40 resize-none focus:ring focus:ring-indigo-300"
                    placeholder="Write your notes here..."
                ></textarea>

                <div class="mt-4 flex justify-end gap-3">
                    <button
                        @click="showMemoPopup = false"
                        class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
                    >
                        Close
                    </button>

                    <button
                        @click="saveMemo"
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<style>
.animate-fadeIn { animation: fadeIn 0.6s ease-out; }

@keyframes fadeIn {
    from { opacity: 0 }
    to { opacity: 1 }
}

@keyframes shinyStar {
    0% {
        filter: drop-shadow(0 0 4px rgba(255, 215, 0, 0.5))
                drop-shadow(0 0 8px rgba(255, 215, 0, 0.3));
        transform: scale(1);
    }
    50% {
        filter: drop-shadow(0 0 12px rgba(255, 215, 0, 0.9))
                drop-shadow(0 0 20px rgba(255, 215, 0, 0.6));
        transform: scale(1.15);
    }
    100% {
        filter: drop-shadow(0 0 4px rgba(255, 215, 0, 0.5))
                drop-shadow(0 0 8px rgba(255, 215, 0, 0.3));
        transform: scale(1);
    }
}

.animate-shinyStar {
    animation: shinyStar 1.8s infinite ease-in-out;
}
</style>
