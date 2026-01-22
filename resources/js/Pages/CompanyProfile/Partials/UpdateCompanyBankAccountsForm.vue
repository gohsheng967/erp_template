<script setup>
import DangerButton from '@/Components/DangerButton.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed, inject } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { useFormat } from '@/Composables/useFormat'

const page = usePage()
const toast = inject("toast")
const { capitalize } = useFormat()

const bankOptions = computed(() => page.props.bankOptions ?? [])
const bankAccounts = computed(() => page.props.companyBankAccounts ?? [])

const form = useForm({
    bank_name: "",
    account_name: "",
    account_no: "",
    status: "active",
})

function submit() {
    form.post("/company-profile/bank-accounts", {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            toast?.value?.show("Bank account added!", "success")
            router.reload({ preserveScroll: true })
        }
    })
}

function toggleStatus(account) {
    const nextStatus = account.status === "active" ? "inactive" : "active"

    router.patch(`/company-profile/bank-accounts/${account.id}`, {
        status: nextStatus,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Bank account updated!", "success")
            router.reload({ preserveScroll: true })
        }
    })
}

function removeAccount(account) {
    router.delete(`/company-profile/bank-accounts/${account.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Bank account removed!", "success")
            router.reload({ preserveScroll: true })
        }
    })
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Company Bank Accounts
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Manage company bank accounts used for payments.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <InputLabel for="bank_name" value="Bank" />
                    <select
                        id="bank_name"
                        v-model="form.bank_name"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="" disabled>Select a bank</option>
                        <option v-for="bank in bankOptions" :key="bank" :value="bank">
                            {{ bank }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.bank_name" />
                </div>

                <div>
                    <InputLabel for="status" value="Status" />
                    <select
                        id="status"
                        v-model="form.status"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.status" />
                </div>

                <div>
                    <InputLabel for="account_name" value="Account Name" />
                    <TextInput
                        id="account_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.account_name"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.account_name" />
                </div>

                <div>
                    <InputLabel for="account_no" value="Account No" />
                    <TextInput
                        id="account_no"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.account_no"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.account_no" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">
                    Add Bank Account
                </PrimaryButton>
            </div>
        </form>

        <div class="mt-8">
            <div
                v-if="!bankAccounts.length"
                class="rounded-md border border-dashed border-gray-300 p-6 text-sm text-gray-500"
            >
                No company bank accounts added yet.
            </div>

            <div v-else class="overflow-x-auto rounded-md border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Bank</th>
                            <th class="px-4 py-3">Account Name</th>
                            <th class="px-4 py-3">Account No</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="account in bankAccounts" :key="account.id">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ account.bank_name }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ account.account_name }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ account.account_no }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="account.status === 'active'
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-700'"
                                >
                                    {{ capitalize(account.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <SecondaryButton
                                    @click="toggleStatus(account)"
                                >
                                    {{ account.status === 'active' ? 'Set Inactive' : 'Set Active' }}
                                </SecondaryButton>
                                <DangerButton
                                    @click="removeAccount(account)"
                                >
                                    Delete
                                </DangerButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
