<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import { ref } from 'vue'
import AbstractMeshBackground from '@/Components/AbstractMeshBackground.vue'

const form = useForm({
    identity_no: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Login" />

    <div class="relative min-h-screen flex items-center justify-center px-4 overflow-hidden">

        <!-- Background must be INSIDE this wrapper -->
        <AbstractMeshBackground />

        <div class="bg-white shadow-2xl rounded-2xl p-10 w-full max-w-md relative z-10">

            <!-- Brand -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">
                    Infinite ERP
                </h1>
                <p class="text-gray-500 mt-2">
                    Secure Login Portal
                </p>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submit" class="space-y-6">

                <!-- Identity Number -->
                <div>
                    <label class="block text-gray-700 mb-2 font-medium" for="identity_no">
                        Identity Number
                    </label>

                    <input
                        id="identity_no"
                        v-model="form.identity_no"
                        type="text"
                        required
                        autofocus
                        autocomplete="off"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />

                    <InputError class="mt-2" :message="form.errors.identity_no" />
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-gray-700 mb-2 font-medium" for="password">
                        Password
                    </label>

                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />

                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <!-- Remember me -->
                <div class="flex items-center pt-1">
                    <input
                        type="checkbox"
                        id="remember"
                        v-model="form.remember"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <label for="remember" class="ml-2 text-sm text-gray-600">
                        Remember me
                    </label>
                </div>

                <!-- Submit -->
                <button
                    :disabled="form.processing"
                    class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Log in
                </button>
            </form>

            <div class="text-center text-sm text-gray-500 mt-8">
                © {{ new Date().getFullYear() }} Infinite ERP. All rights reserved.
            </div>

        </div>
    </div>
</template>
