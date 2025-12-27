<script setup>
defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    default: 'Are you sure?',
  },
  message: {
    type: String,
    default: '',
  },
  confirmText: {
    type: String,
    default: 'Confirm',
  },
  cancelText: {
    type: String,
    default: 'Cancel',
  },
  danger: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center"
  >
    <!-- Backdrop -->
    <div
      class="absolute inset-0 bg-black/40"
      @click="emit('cancel')"
    ></div>

    <!-- Modal -->
    <div
      class="relative bg-white w-full max-w-md rounded shadow-lg p-6"
    >
      <h3 class="text-lg font-semibold text-gray-800">
        {{ title }}
      </h3>

      <p
        v-if="message"
        class="mt-2 text-sm text-gray-600"
      >
        {{ message }}
      </p>

      <div class="mt-6 flex justify-end gap-3">
        <button
          type="button"
          class="btn-secondary"
          @click="emit('cancel')"
        >
          {{ cancelText }}
        </button>

        <button
          type="button"
          :class="[
            danger ? 'btn-danger' : 'btn-primary'
          ]"
          @click="emit('confirm')"
        >
          {{ confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>
