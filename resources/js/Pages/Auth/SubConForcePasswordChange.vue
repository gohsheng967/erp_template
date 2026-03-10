<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import AbstractMeshBackground from "@/Components/AbstractMeshBackground.vue";

const props = defineProps({
    subCon: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("sub-con.password.update"), {
        onFinish: () => {
            form.reset("current_password", "password", "password_confirmation");
        },
    });
};
</script>

<template>
    <Head title="Change Password" />

    <div class="relative min-h-screen flex items-center justify-center px-4 overflow-hidden">
        <AbstractMeshBackground />

        <div class="bg-white shadow-2xl rounded-2xl p-10 w-full max-w-md relative z-10">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Change Your Password</h1>
                <p class="text-gray-500 mt-2">
                    First login detected for <span class="font-semibold">{{ subCon.identity_no }}</span>.
                </p>
                <p class="text-gray-500">Please set a new password to continue.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <label class="block text-gray-700 mb-2 font-medium" for="current_password">
                        Current Password
                    </label>
                    <input
                        id="current_password"
                        v-model="form.current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <InputError class="mt-2" :message="form.errors.current_password" />
                </div>

                <div>
                    <label class="block text-gray-700 mb-2 font-medium" for="password">
                        New Password
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <label class="block text-gray-700 mb-2 font-medium" for="password_confirmation">
                        Confirm New Password
                    </label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <button
                    :disabled="form.processing"
                    class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Update Password
                </button>
            </form>
        </div>
    </div>
</template>

