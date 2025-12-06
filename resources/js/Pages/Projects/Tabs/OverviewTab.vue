<script setup>
import { ref, onMounted } from "vue";
import {
    Chart,
    ArcElement,
    Tooltip,
    Legend,
    CategoryScale,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    DoughnutController
} from "chart.js";

Chart.register(
    ArcElement,
    DoughnutController,
    Tooltip,
    Legend,
    CategoryScale,
    LinearScale,
    LineController,
    LineElement,
    PointElement
);

const props = defineProps({
    project: Object
});

// Dummy values
const usedBudget = 80000;
const totalBudget = props.project.budget ?? 250000;
const remainingBudget = totalBudget - usedBudget;

// animation refs
const animateUsed = ref(0);
const animateRemaining = ref(0);
const animatePercentage = ref(0);

// memo popup
const memo = ref("");
const showMemoPopup = ref(false);

// chart refs
const doughnutRef = ref(null);
const lineRef = ref(null);

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
    console.log("Saved memo:", memo.value);
    showMemoPopup.value = false;
}

onMounted(() => {

    animateNumber(usedBudget, animateUsed);
    animateNumber(remainingBudget, animateRemaining);
    animateNumber(((usedBudget / totalBudget) * 100).toFixed(1), animatePercentage);

    // DOUGHNUT CHART
    new Chart(doughnutRef.value, {
        type: "doughnut",
        data: {
            labels: ["Used", "Remaining"],
            datasets: [
                {
                    data: [80000, remainingBudget],
                    backgroundColor: ["#6366f1", "#e0e7ff"],
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 900,
                easing: "easeOutQuint"
            },
            plugins: { legend: { display: false }},
            cutout: "65%"
        }
    });

    // LINE CHART
    new Chart(lineRef.value, {
        type: "line",
        data: {
            labels: ["Jan", "Feb", "Mar", "Apr", "May"],
            datasets: [
                {
                    label: "Expenses",
                    data: [5000, 8000, 9000, 12000, 15000],
                    borderColor: "#6366f1",
                    backgroundColor: "rgba(99,102,241,0.15)",
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 800, easing: "easeOutCubic" },
            plugins: { legend: { display: false }},
            scales: {
                y: { grid: { color: "#f1f5f9" }},
                x: { grid: { display: false }}
            }
        }
    });
});
</script>

<template>
    <div class="space-y-10 animate-fadeIn">

        <!-- 1️⃣ PROJECT TIMELINE (with star memo button attached) -->
        <div class="bg-white shadow-md rounded-xl p-6 border relative">

            <!-- MEMO STAR ICON (TOP-RIGHT) -->
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
                <div class="h-3 bg-green-500 rounded-full transition-all duration-[1200ms] ease-out" style="width: 40%"></div>
            </div>

            <p class="text-gray-500 text-sm mt-2">Progress: 40%</p>
        </div>

        <!-- 2️⃣ KPI CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">

            <div class="bg-white shadow-md rounded-xl p-6 border hover:shadow-lg transition">
                <p class="text-gray-500 text-sm">Total Budget</p>
                <p class="text-3xl font-bold mt-1">
                    RM {{ totalBudget.toLocaleString() }}
                </p>
            </div>

            <div class="bg-white shadow-md rounded-xl p-6 border hover:shadow-lg transition">
                <p class="text-gray-500 text-sm">Used</p>
                <p class="text-3xl font-bold mt-1 text-indigo-600">
                    RM {{ animateUsed.toLocaleString() }}
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
                <p class="text-2xl font-bold mt-1">{{ animatePercentage }}%</p>

                <div class="w-full bg-gray-200 rounded-full h-2 mt-3 overflow-hidden">
                    <div
                        class="h-2 bg-indigo-500 rounded-full transition-all duration-[1200ms] ease-out"
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
                <h3 class="text-lg font-semibold mb-4">Monthly Expenses Trend</h3>
                <div class="h-[400px]">
                    <canvas ref="lineRef"></canvas>
                </div>
            </div>

        </div>

        <!-- 4️⃣ DOCUMENT SUMMARY -->
        <div class="bg-white shadow-md rounded-xl p-6 border">
            <h3 class="text-xl font-semibold mb-4">📄 Document Summary</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-white rounded-lg shadow border">
                    <p class="text-gray-500">Total Documents</p>
                    <p class="text-2xl font-bold mt-1">12</p>
                </div>

                <div class="p-4 bg-white rounded-lg shadow border">
                    <p class="text-gray-500">Last Upload</p>
                    <p class="text-lg font-semibold mt-1 truncate">Contract_v3.pdf</p>
                </div>

                <div class="p-4 bg-white rounded-lg shadow border">
                    <p class="text-gray-500">Upload Time</p>
                    <p class="text-lg font-semibold mt-1">2025-02-03 14:20</p>
                </div>
            </div>

            <button class="mt-5 px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                View All Documents
            </button>
        </div>

        <!-- 5️⃣ RECENT ACTIVITIES -->
        <div class="bg-white shadow-md rounded-xl p-6 border">
            <h3 class="text-lg font-semibold mb-4">Recent Activities</h3>

            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <div class="w-2 h-2 bg-indigo-500 rounded-full mt-2"></div>
                    <div>
                        <p class="font-semibold text-gray-800">Milestone “Design Phase” created</p>
                        <p class="text-gray-500 text-sm">2 hours ago</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-2 h-2 bg-indigo-500 rounded-full mt-2"></div>
                    <div>
                        <p class="font-semibold text-gray-800">Uploaded document “Contract_v3.pdf”</p>
                        <p class="text-gray-500 text-sm">Yesterday</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-2 h-2 bg-indigo-500 rounded-full mt-2"></div>
                    <div>
                        <p class="font-semibold text-gray-800">Claim expense RM 2,400 submitted</p>
                        <p class="text-gray-500 text-sm">2 days ago</p>
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
