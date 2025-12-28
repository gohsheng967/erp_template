<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdateCompanyProfileForm from './Partials/UpdateCompanyProfileForm.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Company Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Company Profile
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

                <!-- COMPANY PROFILE FORM CARD -->
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdateCompanyProfileForm />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
