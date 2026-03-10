<script setup>
import { inject, nextTick, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const form = useForm({
    code: ''
})

const errors = form.errors
const toast = inject('toast', null)
const useBackup = ref(false)
const digits = ref(['', '', '', '', '', ''])
const digitInputs = ref([])

watch(
    digits,
    (next) => {
        if (!useBackup.value) {
            form.code = next.join('')
        }
    },
    { deep: true }
)

const resetDigits = () => {
    digits.value = ['', '', '', '', '', '']
    form.code = ''
}

const toggleBackup = () => {
    useBackup.value = !useBackup.value
    form.code = ''
    resetDigits()
    if (!useBackup.value) {
        nextTick(() => digitInputs.value?.[0]?.focus())
    }
}

const setDigit = (index, value) => {
    const clean = value.replace(/\D/g, '')
    if (!clean) {
        digits.value[index] = ''
        return
    }

    const chars = clean.split('')
    for (let i = 0; i < chars.length && index + i < 6; i += 1) {
        digits.value[index + i] = chars[i]
    }

    const nextIndex = Math.min(index + chars.length, 5)
    if (digits.value[nextIndex] && nextIndex < 5) {
        digitInputs.value?.[nextIndex + 1]?.focus()
    } else {
        digitInputs.value?.[nextIndex]?.focus()
    }
}

const handleKeydown = (index, event) => {
    if (event.key !== 'Backspace') return
    if (digits.value[index]) {
        digits.value[index] = ''
        return
    }
    if (index > 0) {
        digitInputs.value?.[index - 1]?.focus()
    }
}

const submit = () => {
    form.post('/mfa/verify', {
        onSuccess: () => {
            toast?.value?.show('Verification successful.', 'success')
        },
        onError: (formErrors) => {
            const message = formErrors.code || 'Invalid verification code.'
            toast?.value?.show(message, 'error')
        },
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex items-center justify-center px-4">
        <div class="bg-white shadow-xl rounded-xl p-8 w-full max-w-md">

            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                Verify Authentication
            </h2>

            <form @submit.prevent="submit">

                <label class="block text-gray-700 mb-2">
                    Enter 6-digit verification code
                </label>

                <div v-if="!useBackup" class="flex items-center justify-between gap-2">
                    <input
                        v-for="(_, index) in digits"
                        :key="index"
                        :ref="(el) => (digitInputs[index] = el)"
                        :value="digits[index]"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        maxlength="1"
                        class="w-12 h-12 border border-gray-300 rounded-lg text-xl text-center font-mono focus:ring-2 focus:ring-blue-500"
                        @input="setDigit(index, $event.target.value)"
                        @keydown="handleKeydown(index, $event)"
                    />
                </div>

                <input
                    v-else
                    v-model="form.code"
                    maxlength="20"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    class="w-full border border-gray-300 rounded-lg p-3 text-lg tracking-widest text-center font-mono"
                    placeholder="Enter your backup code"
                />

                <div v-if="errors.code" class="text-red-600 text-sm mt-2">
                    {{ errors.code }}
                </div>

                <button
                    type="button"
                    class="mt-3 text-sm text-blue-600 hover:text-blue-700"
                    @click="toggleBackup"
                >
                    {{ useBackup ? 'Use authenticator code instead' : 'Use backup code instead' }}
                </button>

                <button
                    type="submit"
                    class="w-full mt-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                    Verify
                </button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-4">
                You may also enter your one-time backup code.
            </p>

        </div>
    </div>
</template>

