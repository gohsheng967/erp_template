<script setup>
import { computed, inject, nextTick, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import TopupApprovalA4 from './TopupApprovalA4.vue'
import { useFormat } from '@/Composables/useFormat'

const { capitalize } = useFormat()
const toast = inject('toast', null)

const props = defineProps({
    show: Boolean,
    topup: Object,
})

const emit = defineEmits(['close', 'approved'])

const page = usePage()
const company = computed(() => page.props.company ?? null)
const printing = ref(false)
const submitting = ref(false)
const rejectReason = ref('')

function printPage() {
    printing.value = true

    nextTick(() => {
        requestAnimationFrame(() => {
            window.print()
            printing.value = false
        })
    })
}

function closeModal() {
    emit('close')
}

function approveTopup() {
    if (!props.topup) return

    submitting.value = true

    router.post(
        route('petty-cash.topups.approve', props.topup.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Top-up approved')
                emit('approved')
                closeModal()
            },
            onFinish: () => {
                submitting.value = false
            },
        }
    )
}

function rejectTopup() {
    if (!props.topup) return

    submitting.value = true

    router.post(
        route('petty-cash.topups.reject', props.topup.id),
        {
            reason: rejectReason.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Top-up rejected')
                emit('approved')
                closeModal()
            },
            onFinish: () => {
                submitting.value = false
            },
        }
    )
}
</script>

<template>
    <div
        v-if="show && topup"
        class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center no-print"
    >
        <div class="bg-gray-100 w-full h-full md:h-[90vh] md:w-[90vw] rounded shadow-xl overflow-hidden">
            <div class="sticky top-0 bg-white border-b px-6 py-3 flex items-center">
                <h2 class="font-semibold text-lg">
                    Top-Up Request - {{ topup.topup_no ?? '-' }}
                </h2>

                <div class="ml-auto flex items-center gap-3">
                    <button @click="printPage">Print / Save PDF</button>
                    <button @click="closeModal" class="text-red-600">
                        <i class="mdi mdi-close text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="flex h-[calc(100%-56px)] gap-6 p-6">
                <div class="flex-1 overflow-auto">
                    <TopupApprovalA4
                        v-if="topup && !printing"
                        :topup="topup"
                        :company="company"
                    />

                    <Teleport to="body">
                        <TopupApprovalA4
                            v-if="topup && printing"
                            :topup="topup"
                            :company="company"
                        />
                    </Teleport>
                </div>

                <div
                    v-if="topup"
                    class="w-[360px] bg-white border rounded-lg p-4 space-y-6 overflow-auto"
                >
                    <div>
                        <div class="text-xs text-gray-500">Status</div>
                        <div class="font-semibold uppercase">
                            {{ capitalize(topup.status ?? '') }}
                        </div>
                    </div>

                    <div class="space-y-2 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Requester</span>
                            <span class="font-medium">{{ topup.requester?.name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Wallet</span>
                            <span class="font-medium">
                                {{ topup.wallet?.context_type === 'office'
                                    ? 'Office'
                                    : (topup.wallet?.project?.name ?? 'Project') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Bank</span>
                            <span class="font-medium">{{ topup.bank_account?.bank_name ?? '-' }}</span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div v-if="topup.status === 'requested'" class="space-y-3">
                        <button
                            class="w-full py-2 bg-green-600 text-white rounded disabled:opacity-40"
                            :disabled="submitting"
                            @click="approveTopup"
                        >
                            Approve
                        </button>
                        <div class="space-y-2">
                            <label class="text-xs text-gray-500">Rejection Reason</label>
                            <textarea
                                v-model="rejectReason"
                                rows="3"
                                class="w-full border rounded px-3 py-2 text-sm"
                                placeholder="Enter reason for rejection"
                            />
                        </div>
                        <button
                            class="w-full py-2 bg-red-600 text-white rounded disabled:opacity-40"
                            :disabled="submitting || !rejectReason"
                            @click="rejectTopup"
                        >
                            Reject
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
