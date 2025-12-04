<script setup>
import { useForm, usePage } from '@inertiajs/vue3'

const form = useForm({
    code: ''
})

const page = usePage()
const errors = page.props.errors
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex items-center justify-center px-4">
        <div class="bg-white shadow-xl rounded-xl p-8 w-full max-w-md">

            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                Verify Authentication
            </h2>

            <form @submit.prevent="form.post('/mfa/verify')">

                <label class="block text-gray-700 mb-2">
                    Enter 6-digit verification code
                </label>

                <input
                    v-model="form.code"
                    maxlength="6"
                    class="w-full border border-gray-300 rounded-lg p-3 text-lg tracking-widest text-center font-mono"
                />

                <div v-if="errors.code" class="text-red-600 text-sm mt-2">
                    {{ errors.code }}
                </div>

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
