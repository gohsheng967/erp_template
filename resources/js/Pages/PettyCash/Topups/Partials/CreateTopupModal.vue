<script setup>
import { useForm, usePage, Link } from '@inertiajs/vue3'
import { computed, inject } from 'vue'

const props = defineProps({
    show: Boolean,
    projects: {
        type: Array,
        required: true,
    },
    defaultContextType: {
        type: String,
        default: null,
    },
    defaultProjectId: {
        type: [Number, String],
        default: null,
    },
    lockContextType: {
        type: Boolean,
        default: false,
    },
    lockProject: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'created'])

/* =========================
   TOAST
========================= */
const toast = inject('toast', null)
const page = usePage()
const bankAccounts = computed(() => {
    const accounts = page.props.auth?.user?.data?.bank_accounts ?? []
    return accounts.filter((account) => account.status === 'active')
})

/* =========================
   FORM
========================= */
const form = useForm({
    context_type: props.defaultContextType ?? 'office', // office | project
    project_id: props.defaultProjectId ?? '',
    bank_account_id: '',
    amount: '',
    reason: '',
})

/* =========================
   HELPERS
========================= */
function onChangePurpose() {
    if (form.context_type === 'office') {
        form.project_id = ''
    }
}

/* =========================
   SUBMIT
========================= */
function submit() {
    form.post(route('petty-cash.topups.store'), {
        preserveScroll: true,

        onSuccess: () => {
            toast?.value?.show(
                'Top-up request submitted successfully.',
                'success'
            )

            form.reset()
            emit('created')
            emit('close')
        },

        onError: () => {
            const first = Object.values(form.errors)?.[0]
            const msg = Array.isArray(first) ? first[0] : first

            toast?.value?.show(
                msg || 'Failed to submit top-up request.',
                'error'
            )
        },
    })
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center"
    >
        <!-- BACKDROP -->
        <div
            class="absolute inset-0 bg-black bg-opacity-40"
            @click="emit('close')"
        ></div>

        <!-- MODAL -->
        <div class="relative bg-white rounded-lg shadow-lg w-full max-w-md p-6 z-10">
            <h3 class="text-lg font-semibold mb-4">
                Request Petty Cash Top-Up
            </h3>

            <!-- ================= PURPOSE ================= -->
            <div v-if="!lockContextType" class="mb-4">
                <label class="text-sm font-medium block mb-2">
                    Top-Up Purpose
                </label>

                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            value="office"
                            v-model="form.context_type"
                            @change="onChangePurpose"
                        />
                        <span>Office</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            value="project"
                            v-model="form.context_type"
                            @change="onChangePurpose"
                        />
                        <span>Project</span>
                    </label>
                </div>
            </div>

            <!-- ================= PROJECT ================= -->
            <div
                v-if="form.context_type === 'project'"
                class="mb-4"
            >
                <label class="text-sm font-medium">
                    Project
                </label>

                <select
                    v-model="form.project_id"
                    :disabled="lockProject"
                    class="input w-full"
                >
                    <option value="">Select Project</option>
                    <option
                        v-for="p in projects"
                        :key="p.id"
                        :value="p.id"
                    >
                        {{ p.name }}
                    </option>
                </select>
            </div>

            <!-- ================= AMOUNT ================= -->
            <div class="mb-4">
                <label class="text-sm font-medium">
                    Amount
                </label>
                <input
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    class="input w-full"
                    placeholder="0.00"
                />
            </div>

            <!-- ================= BANK ACCOUNT ================= -->
            <div class="mb-4">
                <label class="text-sm font-medium">
                    Bank Account
                </label>
                <select
                    v-model="form.bank_account_id"
                    class="input w-full"
                >
                    <option value="">Select Bank Account</option>
                    <option
                        v-for="account in bankAccounts"
                        :key="account.id"
                        :value="account.id"
                    >
                        {{ account.bank_name }} - {{ account.account_no }}
                    </option>
                </select>
                <div
                    v-if="!bankAccounts.length"
                    class="mt-2 text-xs text-gray-500"
                >
                    No active bank accounts found. Add one in
                    <Link class="text-indigo-600 hover:text-indigo-800" href="/profile">Profile</Link>.
                </div>
                <div
                    v-if="form.errors.bank_account_id"
                    class="mt-1 text-xs text-red-600"
                >
                    {{ form.errors.bank_account_id }}
                </div>
            </div>

            <!-- ================= REASON ================= -->
            <div class="mb-4">
                <label class="text-sm font-medium">
                    Reason
                </label>
                <textarea
                    v-model="form.reason"
                    class="input w-full"
                    rows="2"
                    placeholder="Optional"
                />
            </div>

            <!-- ================= ACTIONS ================= -->
            <div class="flex justify-end gap-3">
                <button
                    class="btn-outline"
                    @click="emit('close')"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    class="btn-primary"
                    @click="submit"
                >
                    Submit Request
                </button>
            </div>
        </div>
    </div>
</template>
