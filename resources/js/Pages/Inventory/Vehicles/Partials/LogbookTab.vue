<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { useFormat } from '@/Composables/useFormat'

const props = defineProps({
    vehicle: {
        type: Object,
        required: true,
    },
})

const page = usePage()
const toast = inject('toast', null)
const { formatDateTime } = useFormat()

const projects = ref([])
const loadingProjects = ref(false)

const logs = computed(() => props.vehicle?.vehicle_logs ?? [])
const currentUser = computed(() => page.props.auth?.user?.data ?? null)

const form = useForm({
    trip_date: new Date().toISOString().slice(0, 16),
    mileage: '',
    origin: '',
    destination: '',
    purpose: '',
    bound_to_type: 'office',
    bound_to_project_id: '',
    bound_to_label: 'Office',
    driver_name: currentUser.value?.name ?? '',
})

function prefillFromAllocation() {
    const active = props.vehicle?.active_allocation
    if (!active) {
        form.bound_to_type = 'office'
        form.bound_to_project_id = ''
        form.bound_to_label = 'Office'
        return
    }

    const allocType = String(active.allocatable_type ?? '')
    if (allocType.includes('Project') && active.allocatable_id) {
        form.bound_to_type = 'project'
        form.bound_to_project_id = active.allocatable_id
        form.bound_to_label = active.allocatable_name ?? active.allocatable?.name ?? ''
        return
    }

    const allocName = String(active.allocatable_name ?? '').toLowerCase()
    if (allocName === 'office') {
        form.bound_to_type = 'office'
        form.bound_to_project_id = ''
        form.bound_to_label = 'Office'
        return
    }

    form.bound_to_type = 'others'
    form.bound_to_project_id = ''
    form.bound_to_label = active.allocatable_name ?? active.allocatable?.name ?? ''
}

async function loadProjects() {
    loadingProjects.value = true
    try {
        const res = await axios.get(route('inventory.vehicles.allocatable.projects'))
        projects.value = res.data?.projects ?? []
    } catch {
        projects.value = []
    } finally {
        loadingProjects.value = false
    }
}

function submit() {
    form.post(route('inventory.vehicles.logbooks.store', props.vehicle.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Logbook entry added.', 'success')
            form.reset('mileage', 'origin', 'destination', 'purpose')
            form.trip_date = new Date().toISOString().slice(0, 16)
            form.driver_name = currentUser.value?.name ?? ''
            prefillFromAllocation()
        },
        onError: (errors) => {
            const message = Object.values(errors)[0] ?? 'Failed to save logbook entry.'
            toast?.value?.show(message, 'error')
        },
    })
}

function removeEntry(log) {
    if (!confirm('Delete this logbook entry?')) return

    router.delete(route('inventory.vehicles.logbooks.destroy', {
        uuid: props.vehicle.uuid,
        logbook: log.id,
    }), {
        preserveScroll: true,
        onSuccess: () => toast?.value?.show('Logbook entry deleted.', 'success'),
    })
}

onMounted(async () => {
    prefillFromAllocation()
    await loadProjects()
})
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-xl border bg-slate-50 p-4">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <h3 class="text-sm font-semibold text-slate-800">Quick Logbook Entry</h3>
                <button
                    type="button"
                    class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100"
                    @click="prefillFromAllocation"
                >
                    Use current allocation
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Trip Date & Time</label>
                    <input
                        v-model="form.trip_date"
                        type="datetime-local"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>

                <div>
                    <label class="text-xs text-slate-500">Mileage</label>
                    <input
                        v-model="form.mileage"
                        type="number"
                        min="0"
                        step="0.1"
                        placeholder="Example: 45210.5"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Origin</label>
                    <input
                        v-model="form.origin"
                        type="text"
                        placeholder="Where the vehicle starts from"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Destination</label>
                    <input
                        v-model="form.destination"
                        type="text"
                        placeholder="Where the vehicle is going"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Purpose</label>
                    <textarea
                        v-model="form.purpose"
                        rows="2"
                        placeholder="Reason for the trip"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    />
                </div>

                <div>
                    <label class="text-xs text-slate-500">Bound To</label>
                    <select
                        v-model="form.bound_to_type"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="office">Office</option>
                        <option value="project">Project</option>
                        <option value="others">Others</option>
                    </select>
                </div>

                <div v-if="form.bound_to_type === 'project'">
                    <label class="text-xs text-slate-500">Project</label>
                    <select
                        v-model="form.bound_to_project_id"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="">
                            {{ loadingProjects ? 'Loading projects...' : 'Select project' }}
                        </option>
                        <option
                            v-for="project in projects"
                            :key="project.id"
                            :value="project.id"
                        >
                            {{ project.code }} - {{ project.name }}
                        </option>
                    </select>
                </div>

                <div v-if="form.bound_to_type === 'others'">
                    <label class="text-xs text-slate-500">Specify</label>
                    <input
                        v-model="form.bound_to_label"
                        type="text"
                        placeholder="Example: Supplier warehouse"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>

                <div v-if="form.bound_to_type === 'office'">
                    <label class="text-xs text-slate-500">Office</label>
                    <input
                        v-model="form.bound_to_label"
                        type="text"
                        disabled
                        class="mt-1 w-full rounded border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-500"
                    >
                </div>

                <div>
                    <label class="text-xs text-slate-500">Driver</label>
                    <input
                        v-model="form.driver_name"
                        type="text"
                        placeholder="Driver name"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>
            </div>

            <div v-if="Object.keys(form.errors).length" class="mt-3 text-xs text-red-600">
                <div v-for="(error, key) in form.errors" :key="key">{{ error }}</div>
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="form.processing"
                    @click="submit"
                >
                    Save Entry
                </button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100 text-slate-600">
                    <tr>
                        <th class="border px-3 py-2 text-left">Date</th>
                        <th class="border px-3 py-2 text-right">Mileage</th>
                        <th class="border px-3 py-2 text-left">Origin</th>
                        <th class="border px-3 py-2 text-left">Destination</th>
                        <th class="border px-3 py-2 text-left">Purpose</th>
                        <th class="border px-3 py-2 text-left">Bound To</th>
                        <th class="border px-3 py-2 text-left">Driver</th>
                        <th class="border px-3 py-2 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs" :key="log.id">
                        <td class="border px-3 py-2">{{ formatDateTime(log.trip_date) }}</td>
                        <td class="border px-3 py-2 text-right">{{ log.mileage ?? '-' }}</td>
                        <td class="border px-3 py-2">{{ log.origin ?? '-' }}</td>
                        <td class="border px-3 py-2">{{ log.destination }}</td>
                        <td class="border px-3 py-2">{{ log.purpose ?? '-' }}</td>
                        <td class="border px-3 py-2">{{ log.bound_to_label ?? '-' }}</td>
                        <td class="border px-3 py-2">{{ log.driver_name }}</td>
                        <td class="border px-3 py-2 text-center">
                            <button
                                type="button"
                                class="text-red-600 hover:text-red-800"
                                @click="removeEntry(log)"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!logs.length">
                        <td colspan="8" class="border px-3 py-6 text-center text-slate-400">
                            No logbook entries yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
