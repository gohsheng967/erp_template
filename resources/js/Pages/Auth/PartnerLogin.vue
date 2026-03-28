<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import InputError from "@/Components/InputError.vue";
import AbstractMeshBackground from "@/Components/AbstractMeshBackground.vue";

const props = defineProps({
    defaultRole: {
        type: String,
        default: "sub_con",
    },
});

const selectedRole = ref(props.defaultRole === "supplier" ? "supplier" : "sub_con");

const form = useForm({
    identity_no: "",
    password: "",
});

const submit = () => {
    const loginRoute = selectedRole.value === "supplier"
        ? route("supplier.login.store")
        : route("sub-con.login.store");

    form.post(loginRoute, {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <Head title="Partner Login" />

    <div class="relative min-h-screen flex items-center justify-center px-4 overflow-hidden">
        <AbstractMeshBackground />

        <div class="bg-white shadow-2xl rounded-2xl p-10 w-full max-w-md relative z-10">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Partner Portal</h1>
                <p class="text-gray-500 mt-2">Login for Sub Con or Supplier access</p>
            </div>

            <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-1 grid grid-cols-2 gap-1">
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-center text-sm font-medium transition flex items-center justify-center gap-2"
                    :class="selectedRole === 'sub_con' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-700 hover:text-slate-900 hover:bg-white'"
                    @click="selectedRole = 'sub_con'"
                >
                    <i class="mdi mdi-account-tie"></i>
                    Sub Con
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-center text-sm font-medium transition flex items-center justify-center gap-2"
                    :class="selectedRole === 'supplier' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-700 hover:text-slate-900 hover:bg-white'"
                    @click="selectedRole = 'supplier'"
                >
                    <i class="mdi mdi-truck-delivery-outline"></i>
                    Supplier
                </button>
            </div>

            <div class="mb-6 text-center">
                <a
                    :href="route('login')"
                    class="text-xs text-slate-500 hover:text-slate-700"
                >
                    Staff login
                </a>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <label class="block text-gray-700 mb-2 font-medium" for="identity_no">
                        Login ID (Identity No)
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

                <button
                    :disabled="form.processing"
                    class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Log in
                </button>
            </form>
        </div>
    </div>
</template>

