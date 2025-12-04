<template>
    <transition name="toast-fade">
        <div
            v-if="visible"
            class="fixed bottom-24 right-6 px-4 py-2 rounded-lg shadow-lg z-50 text-white"
            :class="bgClass"
        >
            {{ message }}
        </div>
    </transition>
</template>

<script setup>
import { ref, computed } from "vue";

const visible = ref(false);
const message = ref("");
const type = ref("success");

const bgClass = computed(() => {
    return {
        success: "bg-gray-900",
        error: "bg-red-600",
        info: "bg-blue-600",
    }[type.value];
});

// Expose a global function that other components can call
function show(msg, toastType = "success") {
    message.value = msg;
    type.value = toastType;
    visible.value = true;

    setTimeout(() => {
        visible.value = false;
    }, 2000);
}

defineExpose({ show });
</script>


<style>
.toast-fade-enter-active,
.toast-fade-leave-active {
    transition: all 0.3s ease;
}
.toast-fade-enter-from,
.toast-fade-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
</style>
