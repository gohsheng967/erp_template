<script setup>
import DangerButton from "@/Components/DangerButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { computed, inject, reactive, watch, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { useFormat } from "@/Composables/useFormat";

const page = usePage();
const toast = inject("toast");
const { capitalize } = useFormat();

const bankOptions = computed(() => page.props.bankOptions ?? []);
const bankAccounts = computed(() => page.props.companyBankAccounts ?? []);
const authUser = computed(() => page.props.auth?.user?.data ?? page.props.auth?.user ?? null);
const allowedBranches = computed(() => authUser.value?.branches ?? []);
const collapsedByBranch = ref({});

const formsByBranch = reactive({});

function ensureBranchForm(branchId) {
    if (formsByBranch[branchId]) return;

    formsByBranch[branchId] = useForm({
        branch_id: branchId,
        bank_name: "",
        account_name: "",
        account_no: "",
        status: "active",
    });
}

function toggleBranch(branchId) {
    collapsedByBranch.value[branchId] = !collapsedByBranch.value[branchId];
}

watch(
    allowedBranches,
    (branches) => {
        branches.forEach((branch) => {
            ensureBranchForm(branch.id);
            if (typeof collapsedByBranch.value[branch.id] === "undefined") {
                collapsedByBranch.value[branch.id] = false;
            }
        });
    },
    { immediate: true }
);

const bankAccountsByBranch = computed(() => {
    const grouped = {};

    allowedBranches.value.forEach((branch) => {
        grouped[branch.id] = [];
    });

    bankAccounts.value.forEach((account) => {
        const id = Number(account.branch_id);
        if (!grouped[id]) grouped[id] = [];
        grouped[id].push(account);
    });

    return grouped;
});

function submit(branchId) {
    ensureBranchForm(branchId);
    const form = formsByBranch[branchId];
    form.branch_id = branchId;

    form.post("/company-profile/bank-accounts", {
        preserveScroll: true,
        onSuccess: () => {
            form.reset("bank_name", "account_name", "account_no");
            form.status = "active";
            toast?.value?.show("Bank account added!", "success");
            router.reload({ preserveScroll: true });
        },
    });
}

function toggleStatus(account) {
    const nextStatus = account.status === "active" ? "inactive" : "active";

    router.patch(
        `/company-profile/bank-accounts/${account.id}`,
        { status: nextStatus },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Bank account updated!", "success");
                router.reload({ preserveScroll: true });
            },
        }
    );
}

function removeAccount(account) {
    router.delete(`/company-profile/bank-accounts/${account.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Bank account removed!", "success");
            router.reload({ preserveScroll: true });
        },
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-gray-900">
                Company Bank Accounts
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Add and manage company bank accounts by branch.
            </p>
        </header>

        <div v-if="!allowedBranches.length" class="mt-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500">
            No branches assigned.
        </div>

        <div v-else class="mt-6 space-y-6">
            <div
                v-for="branch in allowedBranches"
                :key="branch.id"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
            >
                <div class="flex items-center justify-between border-b bg-slate-50 px-4 py-3">
                    <div>
                        <div class="font-semibold text-gray-900">
                            {{ branch.name }}
                        </div>
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ branch.slug }}</span>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                        @click="toggleBranch(branch.id)"
                    >
                        {{ collapsedByBranch[branch.id] ? "Expand" : "Collapse" }}
                    </button>
                </div>

                <div v-if="!collapsedByBranch[branch.id]" class="p-4">
                    <form class="rounded-lg border border-gray-200 bg-gray-50/60 p-4 space-y-4" @submit.prevent="submit(branch.id)">
                        <div class="text-sm font-medium text-gray-700">Add Bank Account</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel :for="`bank_name_${branch.id}`" value="Bank" />
                                <select
                                    :id="`bank_name_${branch.id}`"
                                    v-model="formsByBranch[branch.id].bank_name"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="" disabled>Select a bank</option>
                                    <option v-for="bank in bankOptions" :key="bank" :value="bank">
                                        {{ bank }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="formsByBranch[branch.id].errors.bank_name" />
                            </div>

                            <div>
                                <InputLabel :for="`status_${branch.id}`" value="Status" />
                                <select
                                    :id="`status_${branch.id}`"
                                    v-model="formsByBranch[branch.id].status"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <InputError class="mt-2" :message="formsByBranch[branch.id].errors.status" />
                            </div>

                            <div>
                                <InputLabel :for="`account_name_${branch.id}`" value="Account Name" />
                                <TextInput
                                    :id="`account_name_${branch.id}`"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="formsByBranch[branch.id].account_name"
                                    required
                                />
                                <InputError class="mt-2" :message="formsByBranch[branch.id].errors.account_name" />
                            </div>

                            <div>
                                <InputLabel :for="`account_no_${branch.id}`" value="Account No" />
                                <TextInput
                                    :id="`account_no_${branch.id}`"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="formsByBranch[branch.id].account_no"
                                    required
                                />
                                <InputError class="mt-2" :message="formsByBranch[branch.id].errors.account_no" />
                            </div>
                        </div>

                        <div class="pt-1">
                            <PrimaryButton :disabled="formsByBranch[branch.id].processing">
                                Add Bank Account
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <div v-if="!collapsedByBranch[branch.id]" class="border-t px-4 py-4">
                    <div class="mb-3 text-sm font-medium text-gray-700">Existing Accounts</div>
                    <div
                        v-if="!(bankAccountsByBranch[branch.id] ?? []).length"
                        class="rounded-md border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-500"
                    >
                        No bank accounts for this branch.
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
                                <tr v-for="account in bankAccountsByBranch[branch.id]" :key="account.id">
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
                                        <SecondaryButton class="text-xs" @click="toggleStatus(account)">
                                            {{ account.status === "active" ? "Set Inactive" : "Set Active" }}
                                        </SecondaryButton>
                                        <DangerButton class="text-xs" @click="removeAccount(account)">
                                            Delete
                                        </DangerButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
