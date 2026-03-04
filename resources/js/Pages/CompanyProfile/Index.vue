<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdateCompanyProfileForm from './Partials/UpdateCompanyProfileForm.vue';
import UpdateCompanyBankAccountsForm from './Partials/UpdateCompanyBankAccountsForm.vue';
import ManageBranchesForm from './Partials/ManageBranchesForm.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Company Profile" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-900">
                    Company Profile
                </h2>
                <p class="text-sm text-gray-500">
                    Manage company details, branches, and branch bank accounts.
                </p>
            </div>
        </template>

        <div class="bg-slate-50/70 py-10">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
                    <h3 class="text-base font-semibold text-slate-900">Overview</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Keep profile and branch banking information accurate for document generation and payments.
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-7">
                    <UpdateCompanyProfileForm />
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-7">
                    <UpdateCompanyBankAccountsForm />
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-7">
                    <ManageBranchesForm />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
