<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

/* =========================
   PROPS & EMITS
========================= */
const props = defineProps({
    vehicle: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close', 'allocated'])

/* =========================
   STATE
========================= */
const users = ref([])
const projects = ref([])

const loadingUsers = ref(false)
const loadingProjects = ref(false)

const form = ref({
    assign_type: 'user', // user | project | others | office
    allocatable_id: null,
    allocatable_name: '',
    from_date: new Date().toISOString().slice(0, 10),
    to_date: null,
    location: '',
    remark: '',
})

/* =========================
   CURRENT ALLOCATION
========================= */
const currentAllocation = computed(() => {
    return props.vehicle.active_allocation ?? null
})

/* =========================
   VALIDATION
========================= */
const isValid = computed(() => {
    if (form.value.assign_type === 'others') {
        return !!form.value.allocatable_name
    }

    if (form.value.assign_type === 'office') {
        return true
    }

    return !!form.value.allocatable_id
})

/* =========================
   LOADERS
========================= */
async function loadUsers() {
    if (loadingUsers.value) return
    loadingUsers.value = true

    try {
        const res = await axios.get(
            route('inventory.vehicles.allocatable.users'),
            {
                params: {
                    exclude_id:
                        currentAllocation.value?.allocatable_type === 'user'
                            ? currentAllocation.value.allocatable_id
                            : null,
                },
            }
        )

        users.value = res.data.users
    } finally {
        loadingUsers.value = false
    }
}

async function loadProjects() {
    if (loadingProjects.value) return
    loadingProjects.value = true

    try {
        const res = await axios.get(
            route('inventory.vehicles.allocatable.projects'),
            {
                params: {
                    exclude_id:
                        currentAllocation.value?.allocatable_type === 'project'
                            ? currentAllocation.value.allocatable_id
                            : null,
                },
            }
        )

        projects.value = res.data.projects
    } finally {
        loadingProjects.value = false
    }
}


/* =========================
   WATCHERS
========================= */
watch(() => form.value.assign_type, (type) => {
    form.value.allocatable_id = null
    form.value.allocatable_name = ''
    form.value.to_date = null

    if (type === 'user' && !users.value.length) {
        loadUsers()
    }

    if (type === 'project' && !projects.value.length) {
        loadProjects()
    }
})

/* =========================
   ON MOUNT (MODAL OPEN)
========================= */
onMounted(() => {
    if (form.value.assign_type === 'user') {
        loadUsers()
    }
})

/* =========================
   SUBMIT
========================= */
function submit() {
    router.post(
        route('inventory.vehicles.allocate', props.vehicle.uuid),
        form.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('allocated')
            },
        }
    )
}
</script>

<template>
    <div class="fixed inset-0 bg-black/30 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg w-full max-w-lg shadow-lg">

            <!-- HEADER -->
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h3 class="font-semibold text-lg">Allocate Vehicle</h3>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
                    ✕
                </button>
            </div>

            <!-- BODY -->
            <div class="p-4 space-y-4 text-sm">

                <!-- VEHICLE INFO -->
                <div class="bg-gray-50 rounded p-3">
                    <div><b>Vehicle:</b> {{ vehicle.brand }} {{ vehicle.model }}</div>
                    <div><b>Plate:</b> {{ vehicle.vehicle?.plate_number }}</div>
                </div>

                <!-- ASSIGN TYPE -->
                <div>
                    <label class="font-medium">Assign To</label>
                    <div class="flex gap-4 mt-1 flex-wrap">
                        <label class="flex items-center gap-1">
                            <input type="radio" value="user" v-model="form.assign_type" />
                            User
                        </label>
                        <label class="flex items-center gap-1">
                            <input type="radio" value="project" v-model="form.assign_type" />
                            Project
                        </label>
                        <label class="flex items-center gap-1">
                            <input type="radio" value="office" v-model="form.assign_type" />
                            Office
                        </label>
                        <label class="flex items-center gap-1">
                            <input type="radio" value="others" v-model="form.assign_type" />
                            Others
                        </label>
                    </div>
                </div>

                <!-- USER -->
                <div v-if="form.assign_type === 'user'">
                    <label class="font-medium">User</label>

                    <div v-if="loadingUsers" class="text-gray-400 text-sm">
                        Loading users...
                    </div>

                    <select
                        v-else
                        v-model="form.allocatable_id"
                        class="w-full border rounded px-3 py-2"
                    >
                        <option value="">-- Select User --</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">
                            {{ u.name }}
                        </option>
                    </select>
                </div>

                <!-- PROJECT -->
                <div v-if="form.assign_type === 'project'">
                    <label class="font-medium">Project</label>

                    <div v-if="loadingProjects" class="text-gray-400 text-sm">
                        Loading projects...
                    </div>

                    <select
                        v-else
                        v-model="form.allocatable_id"
                        class="w-full border rounded px-3 py-2"
                    >
                        <option value="">-- Select Project --</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.code }} - {{ p.name }}
                        </option>
                    </select>
                </div>

                <!-- MANUAL -->
                <div v-if="form.assign_type === 'others'">
                    <label class="font-medium">Name / Company</label>
                    <input
                        v-model="form.allocatable_name"
                        class="w-full border rounded px-3 py-2"
                        placeholder="External driver / vendor"
                    />
                </div>

                <!-- LOCATION -->
                <div>
                    <label class="font-medium">Location</label>
                    <input
                        v-model="form.location"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>
                
                <!-- DATES -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-medium">From Date</label>
                        <input
                            type="date"
                            v-model="form.from_date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>
                    <div>
                        <label class="font-medium">Expected Return</label>
                        <input
                            type="date"
                            v-model="form.to_date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>
                </div>



                <!-- REMARK -->
                <div>
                    <label class="font-medium">Remark</label>
                    <textarea
                        v-model="form.remark"
                        rows="3"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-4 py-3 border-t flex justify-end gap-2">
                <button
                    class="px-4 py-2 border rounded"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded disabled:opacity-50"
                    :disabled="!isValid"
                    @click="submit"
                >
                    Confirm
                </button>
            </div>

        </div>
    </div>
</template>
