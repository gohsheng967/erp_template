<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    secret: String,
    qrImage: String,
    backupCode: String
})

const form = useForm({
    code: ''
})

const submitForm = () => {
    form.post('/mfa/setup/verify')
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex items-center justify-center px-4">
        <div class="bg-white shadow-xl rounded-xl p-8 w-full max-w-lg">

            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                Set Up Google Authenticator
            </h2>

            <p class="text-gray-600 mb-6">
                Scan the QR code using Google Authenticator and enter the 6-digit code.
            </p>

            <div class="flex justify-center mb-8">
                <img :src="qrImage"
                     class="w-48 h-48 border rounded-lg shadow-md bg-white" />
            </div>

            <div class="bg-gray-50 p-4 rounded-lg mb-4 border">
                <p class="text-gray-600 text-sm">Secret Key</p>
                <p class="text-lg font-mono font-semibold text-blue-600">
                    {{ secret }}
                </p>
            </div>

            <!-- 6-digit TOTP CODE -->
            <input
                v-model="form.code"
                maxlength="6"
                class="border px-3 py-2 rounded-lg w-full"
                placeholder="Enter the 6-digit code" />

            <div v-if="form.errors.code" class="text-red-600 mt-2">
                {{ form.errors.code }}
            </div>

            <button
                @click="submitForm"
                class="w-full py-3 bg-blue-600 text-white rounded-lg text-center font-medium hover:bg-blue-700 mt-4"
            >
                Verify & Continue
            </button>

        </div>
    </div>
</template>
