<script setup>
import { computed, nextTick, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import SubConPaymentSlipA4 from "./SubConPaymentSlipA4.vue";

const props = defineProps({
    show: Boolean,
    slip: Object,
});

const emit = defineEmits(["close"]);

const page = usePage();
const company = computed(() => page.props.company ?? null);
const printing = ref(false);

function printPage() {
    printing.value = true;

    nextTick(() => {
        requestAnimationFrame(() => {
            window.print();
            printing.value = false;
        });
    });
}

function closeModal() {
    emit("close");
}
</script>

<template>
    <div
        v-if="show && slip"
        class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center no-print"
    >
        <div class="bg-gray-100 w-full h-full md:h-[90vh] md:w-[90vw] rounded shadow-xl overflow-hidden">
            <div class="sticky top-0 bg-white border-b px-6 py-3 flex items-center">
                <h2 class="font-semibold text-lg">
                    Payment Slip - {{ slip.slip_no ?? "-" }}
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
                    <SubConPaymentSlipA4
                        v-if="slip && !printing"
                        :slip="slip"
                        :company="company"
                    />

                    <Teleport to="body">
                        <SubConPaymentSlipA4
                            v-if="slip && printing"
                            :slip="slip"
                            :company="company"
                        />
                    </Teleport>
                </div>
            </div>
        </div>
    </div>
</template>
