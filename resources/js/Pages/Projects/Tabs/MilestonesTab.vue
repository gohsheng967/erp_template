<script setup>
import { ref } from "vue";

const props = defineProps({
    project: Object
});

/* -------------------------------
   NEW MILESTONE FORM
------------------------------- */
const newMilestone = ref({
    title: "",
    start: "",
    end: "",
});

/* -------------------------------
   MILESTONES DATA (DUMMY)
------------------------------- */
const milestones = ref([
    {
        id: 1,
        title: "Design Phase",
        start: "2025-02-01",
        end: "2025-02-15",
        status: "Completed",
        tasks: [
            { name: "Wireframes", done: true },
            { name: "UI Mockups", done: true },
            { name: "Branding Setup", done: true },
        ],
        invoice_no: "INV-2025-001"
    },
    {
        id: 2,
        title: "Development Phase",
        start: "2025-02-16",
        end: "2025-03-20",
        status: "In Progress",
        tasks: [
            { name: "Frontend Development", done: true },
            { name: "Backend API", done: false },
            { name: "Integration", done: false },
        ],
        invoice_no: null
    },
]);

/* -------------------------------
   EXPAND MILESTONE
------------------------------- */
const expandedMilestone = ref(null);

function toggleMilestone(id) {
    expandedMilestone.value = expandedMilestone.value === id ? null : id;
}

/* -------------------------------
   ADD NEW MILESTONE
------------------------------- */
function createMilestone() {
    if (!newMilestone.value.title || !newMilestone.value.start || !newMilestone.value.end) {
        alert("Fill all milestone fields.");
        return;
    }

    milestones.value.push({
        id: Date.now(),
        title: newMilestone.value.title,
        start: newMilestone.value.start,
        end: newMilestone.value.end,
        status: "Pending",
        tasks: [],
        invoice_no: null,
    });

    newMilestone.value = { title: "", start: "", end: "" };
}

/* -------------------------------
   ADD TASK TO MILESTONE
------------------------------- */
const newTask = ref("");

function addTask(milestone) {
    if (!newTask.value.trim()) return;

    milestone.tasks.push({
        name: newTask.value,
        done: false,
    });

    newTask.value = "";
}

/* -------------------------------
   LINK INVOICE TO MILESTONE
------------------------------- */
function attachInvoice(milestone) {
    const invoice = prompt("Enter invoice number (e.g., INV-2025-004):");
    if (invoice) milestone.invoice_no = invoice;
}
</script>



<template>
    <div class="space-y-8">

        <!-- NEW MILESTONE FORM -->
        <div class="bg-white p-6 rounded-xl shadow-md border space-y-4">
            <h2 class="text-lg font-semibold">Add New Milestone</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <div>
                    <label class="text-sm text-gray-700">Milestone Title</label>
                    <input
                        v-model="newMilestone.title"
                        class="border px-3 py-2 rounded-md w-full"
                        placeholder="e.g., Design Phase"
                    >
                </div>

                <div>
                    <label class="text-sm text-gray-700">Start Date</label>
                    <input
                        v-model="newMilestone.start"
                        type="date"
                        class="border px-3 py-2 rounded-md w-full"
                    >
                </div>

                <div>
                    <label class="text-sm text-gray-700">End Date</label>
                    <input
                        v-model="newMilestone.end"
                        type="date"
                        class="border px-3 py-2 rounded-md w-full"
                    >
                </div>

            </div>

            <button
                @click="createMilestone"
                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
            >
                Add Milestone
            </button>
        </div>



        <!-- MILESTONES LIST -->
        <div class="bg-white rounded-xl shadow-md border">
            <h3 class="p-4 text-lg font-semibold border-b">Project Milestones</h3>

            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Milestone</th>
                        <th class="px-3 py-2 text-left">Start</th>
                        <th class="px-3 py-2 text-left">End</th>
                        <th class="px-3 py-2 text-left">Status</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">

                    <template v-for="ms in milestones" :key="ms.id">

                        <!-- MAIN ROW -->
                        <tr
                            @click="toggleMilestone(ms.id)"
                            class="cursor-pointer hover:bg-gray-50 transition"
                        >
                            <td class="px-3 py-2 flex items-center gap-2">
                                <svg :class="expandedMilestone === ms.id ? 'rotate-90' : ''"
                                     class="h-4 w-4 text-gray-500 transition"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5l7 7-7 7" />
                                </svg>
                                <span class="font-medium">{{ ms.title }}</span>
                            </td>
                            <td class="px-3 py-2">{{ ms.start }}</td>
                            <td class="px-3 py-2">{{ ms.end }}</td>
                            <td class="px-3 py-2">
                                <span
                                    :class="{
                                        'text-green-600': ms.status === 'Completed',
                                        'text-yellow-600': ms.status === 'In Progress',
                                        'text-gray-600': ms.status === 'Pending'
                                    }"
                                    class="font-semibold"
                                >
                                    {{ ms.status }}
                                </span>
                            </td>
                        </tr>


                        <!-- EXPANDED DETAILS -->
                        <tr v-if="expandedMilestone === ms.id" class="bg-gray-50">
                            <td colspan="4" class="px-6 py-4">

                                <!-- PROGRESS BAR -->
                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-700 mb-1">Progress</h4>

                                    <div class="w-full bg-gray-300 rounded-full h-2 overflow-hidden">
                                        <div
                                            class="h-2 bg-indigo-500 rounded-full transition-all"
                                            :style="{ width: (ms.tasks.filter(t => t.done).length / ms.tasks.length * 100) + '%' }"
                                        ></div>
                                    </div>
                                </div>


                                <!-- TASKS -->
                                <div class="space-y-3 mb-4">
                                    <h4 class="font-semibold text-gray-700">Tasks</h4>

                                    <div class="space-y-2">
                                        <div
                                            v-for="(t, idx) in ms.tasks"
                                            :key="idx"
                                            class="flex items-center gap-3"
                                        >
                                            <input type="checkbox" v-model="t.done">
                                            <span :class="t.done ? 'line-through text-gray-500' : 'text-gray-800'">
                                                {{ t.name }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Add Task -->
                                    <div class="flex gap-2 mt-2">
                                        <input
                                            v-model="newTask"
                                            class="border px-3 py-1 rounded-md w-64"
                                            placeholder="Add new task"
                                        >
                                        <button
                                            @click="addTask(ms)"
                                            class="px-3 py-1 bg-green-600 text-white rounded-md hover:bg-green-700"
                                        >
                                            Add Task
                                        </button>
                                    </div>
                                </div>


                                <!-- INVOICE LINK -->
                                <div class="mb-2">
                                    <h4 class="font-semibold text-gray-700">Invoice Issued</h4>

                                    <div class="flex items-center gap-3">
                                        <span v-if="ms.invoice_no" class="font-medium text-indigo-600">
                                            {{ ms.invoice_no }}
                                        </span>

                                        <button
                                            @click="attachInvoice(ms)"
                                            class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                        >
                                            {{ ms.invoice_no ? 'Change Invoice' : 'Attach Invoice' }}
                                        </button>
                                    </div>
                                </div>

                            </td>
                        </tr>

                    </template>

                </tbody>
            </table>
        </div>

    </div>
</template>
