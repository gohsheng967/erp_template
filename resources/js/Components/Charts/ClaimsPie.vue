<script setup>
import { ref, onMounted, watch, computed, onBeforeUnmount } from "vue"
import {
    Chart,
    ArcElement,
    Tooltip,
    Legend,
    PieController
} from "chart.js"

Chart.register(ArcElement, PieController, Tooltip, Legend)
Chart.defaults.resizeDelay = 0

const props = defineProps({
    donut: {
        type: Array,
        required: true
    }
})

const chartRef = ref(null)
let chart = null

const colors = [
    "#6366f1", "#22c55e", "#f59e0b",
    "#ef4444", "#0ea5e9", "#a855f7",
    "#64748b"
]

const total = computed(() =>
    props.donut.reduce((sum, i) => sum + Number(i.amount || 0), 0)
)

const hasData = computed(() => total.value > 0)

function destroyChart() {
    if (chart) {
        chart.destroy()
        chart = null
    }
}

/* 🔹 Draw % label inside slices */
const pieLabelPlugin = {
    id: "pieLabelPlugin",
    afterDraw(chart) {
        if (!hasData.value) return

        const { ctx } = chart
        const dataset = chart.data.datasets[0]
        const meta = chart.getDatasetMeta(0)

        ctx.save()
        ctx.font = "9px sans-serif"
        ctx.fillStyle = "#374151"
        ctx.textAlign = "center"
        ctx.textBaseline = "middle"

        meta.data.forEach((arc, i) => {
            const value = dataset.data[i]
            if (!value) return

            const pct = ((value / total.value) * 100).toFixed(0) + "%"
            const pos = arc.tooltipPosition()

            ctx.fillText(pct, pos.x, pos.y)
        })

        ctx.restore()
    }
}

function render() {
    if (!chartRef.value) return
    destroyChart()

    chart = new Chart(chartRef.value, {
        type: "pie",
        data: {
            labels: hasData.value
                ? props.donut.map(i => i.label)
                : ["No Data"],
            datasets: [{
                data: hasData.value
                    ? props.donut.map(i => i.amount)
                    : [1],
                backgroundColor: hasData.value
                    ? colors
                    : ["#e5e7eb"],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // 🔥 REQUIRED
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: hasData.value,
                    callbacks: {
                        label: ctx =>
                            `${ctx.label}: RM ${Number(ctx.raw).toLocaleString()}`
                    }
                }
            }
        },
        plugins: [pieLabelPlugin]
    })
}


onMounted(render)
watch(() => props.donut, render, { deep: true })
onBeforeUnmount(destroyChart)
</script>

<template>
    <div class="relative w-full h-full">
        <canvas
            ref="chartRef"
            class="block w-full h-full"
        ></canvas>

        <div
            v-if="!hasData"
            class="absolute inset-0 flex items-center justify-center
                   text-xs text-gray-400 italic"
        >
            No Data
        </div>
    </div>
</template>

