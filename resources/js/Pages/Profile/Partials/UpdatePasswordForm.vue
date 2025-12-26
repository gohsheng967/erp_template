<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, inject } from 'vue';

// User data
const page = usePage();
const user = page.props.auth?.user?.data ?? {};

// Toast
const toast = inject("toast");

// Input refs
const passwordInput = ref(null);
const currentPasswordInput = ref(null);

// Password form
const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,

        onSuccess: () => {
            toast?.value?.show("Password updated successfully!", "success")
            form.reset();
        },

        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>
<template>
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Security Settings</h2>
        <p class="mt-1 text-sm text-gray-600">
            Manage your password, MFA authentication, and future employee history records.
        </p>
    </header>

    <!-- GRID: LEFT = Password | RIGHT = MFA + Employee History -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-10">

        <!-- ========================= -->
        <!-- LEFT — PASSWORD FORM      -->
        <!-- ========================= -->
        <div class="bg-white shadow-sm rounded-lg p-6 border">
            <h3 class="text-base font-semibold text-gray-800">Update Password</h3>
            <p class="mt-1 text-sm text-gray-600">
                Keep your account secure by using a strong password.
            </p>

            <form @submit.prevent="updatePassword" class="mt-6 space-y-6">

                <!-- Current Password -->
                <div>
                    <InputLabel for="current_password" value="Current Password" />
                    <TextInput
                        id="current_password"
                        ref="currentPasswordInput"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.current_password"
                        autocomplete="current-password"
                    />
                    <InputError :message="form.errors.current_password" class="mt-2" />
                </div>

                <!-- New Password -->
                <div>
                    <InputLabel for="password" value="New Password" />
                    <TextInput
                        id="password"
                        ref="passwordInput"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <!-- Confirm Password -->
                <div>
                    <InputLabel for="password_confirmation" value="Confirm Password" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password_confirmation"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                </div>

                <PrimaryButton :disabled="form.processing">Save Password</PrimaryButton>
            </form>
        </div>

        <!-- ========================= -->
        <!-- RIGHT — MFA + EMPLOYEE HISTORY -->
        <!-- ========================= -->
        <div class="space-y-8">

            <!-- MFA BOX -->
            <div class="bg-white shadow-sm rounded-lg p-6 border">
                <h3 class="text-base font-semibold text-gray-800">Multi-Factor Authentication (MFA)</h3>
                <p class="mt-1 text-sm text-gray-600">
                    View your MFA status and backup code.
                </p>

                <div class="mt-6 space-y-6">

                    <!-- MFA Status -->
                    <div>
                        <InputLabel value="MFA Status" />

                        <span
                            class="mt-2 px-4 py-2 rounded text-sm font-semibold inline-block border"
                            :class="user.mfa_enabled 
                                ? 'bg-green-50 text-green-700 border-green-300'
                                : 'bg-red-50 text-red-700 border-red-300'"
                        >
                            {{ user.mfa_enabled ? 'Enabled' : 'Disabled' }}
                        </span>

                        <p class="text-gray-500 text-sm mt-1">
                            MFA adds an extra layer of security to your account.
                        </p>
                    </div>

                    <!-- Backup Code -->
                    <div>
                        <InputLabel value="Backup Code (Read-only)" />

                        <div class="mt-2 px-4 py-2 bg-gray-100 border rounded font-mono tracking-widest text-gray-900">
                            {{ user.backup_plain ?? 'N/A' }}
                        </div>

                        <p class="text-gray-500 text-sm mt-2">
                            Use this code if you lose access to Google Authenticator.
                        </p>
                    </div>

                </div>
            </div>

            <!-- ========================= -->
            <!-- EMPLOYEE HISTORY (COMING SOON) -->
            <!-- ========================= -->
            <div class="bg-white shadow-sm rounded-lg p-6 border">
                <h3 class="text-base font-semibold text-gray-800">
                    Employee History <span class="text-xs text-gray-500">(Coming Soon)</span>
                </h3>

                <p class="mt-1 text-sm text-gray-600">
                    This panel will display your KPI updates, attendance logs, and performance records.
                </p>

                <div class="mt-6 space-y-3 text-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400">•</span> KPI Record Tracking
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400">•</span> Attendance History
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400">•</span> Task & Job Performance Notes
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400">•</span> Promotion & Role Change Timeline
                    </div>
                </div>

                <div class="mt-6 p-3 bg-gray-50 border rounded text-sm text-gray-600">
                    Future module integration will appear here once enabled.
                </div>
            </div>

        </div>

    </div>
</section>
</template>
