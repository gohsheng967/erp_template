<script setup>
import { computed, inject, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
    subCon: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["close", "saved"]);
const page = usePage();
const bankOptions = computed(() => page.props.bankOptions ?? []);
const toast = inject("toast", null);

function blankBankAccount() {
    return {
        bank_name: "",
        account_name: "",
        account_no: "",
    };
}

function normalizeBankAccounts(subCon) {
    const accounts = Array.isArray(subCon?.bank_accounts) ? subCon.bank_accounts : [];
    if (accounts.length) {
        return accounts.map((account) => ({
            bank_name: account.bank_name ?? "",
            account_name: account.account_name ?? "",
            account_no: account.account_no ?? "",
        }));
    }

    if (subCon?.bank) {
        return [
            {
                bank_name: subCon.bank,
                account_name: "",
                account_no: "",
            },
        ];
    }

    return [blankBankAccount()];
}

const form = useForm({
    name: "",
    company_name: "",
    email: "",
    phone: "",
    address: "",
    manage_login_account: false,
    login_identity_no: "",
    login_email: "",
    login_status: 1,
    login_password: "",
    bank_accounts: [blankBankAccount()],
});

watch(
    () => props.subCon,
    (subCon) => {
        if (!subCon) return;

        form.name = subCon.name ?? "";
        form.company_name = subCon.company_name ?? "";
        form.email = subCon.email ?? "";
        form.phone = subCon.phone ?? "";
        form.address = subCon.address ?? "";
        form.bank_accounts = normalizeBankAccounts(subCon);
        form.manage_login_account = !!subCon.portal_user;
        form.login_identity_no = subCon.portal_user?.identity_no ?? "";
        form.login_email = subCon.portal_user?.email ?? "";
        form.login_status = subCon.portal_user?.status ?? 1;
        form.login_password = "";
    },
    { immediate: true }
);

function addBankAccount() {
    if (form.bank_accounts.length >= 10) return;
    form.bank_accounts.push(blankBankAccount());
}

function removeBankAccount(index) {
    if (form.bank_accounts.length === 1) {
        form.bank_accounts[0] = blankBankAccount();
        return;
    }

    form.bank_accounts.splice(index, 1);
}

function accountError(index, field) {
    return form.errors[`bank_accounts.${index}.${field}`];
}

function submit() {
    form.put(route("sub-cons.update", props.subCon.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Sub Con updated successfully.", "success");
            emit("close");
            emit("saved");
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0] || "Failed to update Sub Con.";
            toast?.value?.show(firstError, "error");
        },
    });
}
</script>

<template>
    <div
        class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm px-4 py-6 sm:px-8"
        @click.self="$emit('close')"
    >
        <div class="mx-auto flex h-full max-w-4xl items-center">
            <div class="w-full overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div class="border-b border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">
                                Edit Sub Con
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Update sub contractor details and bank accounts.
                            </p>
                        </div>
                        <button
                            class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                            @click="$emit('close')"
                        >
                            <i class="mdi mdi-close text-xl"></i>
                        </button>
                    </div>
                </div>

                <div class="max-h-[75vh] space-y-6 overflow-y-auto px-6 py-6 sm:px-8">
                    <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <div class="mb-3 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <i class="mdi mdi-account-key-outline text-base text-indigo-600"></i>
                                Sub Con Login Account
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                <input
                                    v-model="form.manage_login_account"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Create / update portal login
                            </label>
                        </div>

                        <div v-if="form.manage_login_account" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Login ID (Identity No) <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.login_identity_no"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <p v-if="form.errors.login_identity_no" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.login_identity_no }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Login Email <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.login_email"
                                    type="email"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <p v-if="form.errors.login_email" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.login_email }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Login Status
                                </label>
                                <select
                                    v-model="form.login_status"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                >
                                    <option :value="1">Active</option>
                                    <option :value="0">Suspended</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    New Password (Optional)
                                </label>
                                <input
                                    v-model="form.login_password"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    placeholder="Leave empty to keep current password"
                                />
                                <p v-if="form.errors.login_password" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.login_password }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <i class="mdi mdi-account-tie-outline text-base text-indigo-600"></i>
                            Basic Information
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Company
                                </label>
                                <input
                                    v-model="form.company_name"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Email
                                </label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Phone
                                </label>
                                <input
                                    v-model="form.phone"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <div class="mb-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <i class="mdi mdi-bank-outline text-base text-indigo-600"></i>
                                Bank Accounts
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                    {{ form.bank_accounts.length }}
                                </span>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100"
                                @click="addBankAccount"
                            >
                                <i class="mdi mdi-plus"></i>
                                Add Account
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(account, index) in form.bank_accounts"
                                :key="index"
                                class="rounded-xl border border-slate-200 bg-slate-50/50 p-3"
                            >
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Account {{ index + 1 }}
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-md px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50"
                                        @click="removeBankAccount(index)"
                                    >
                                        Remove
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">
                                            Bank Name
                                        </label>
                                        <select
                                            v-model="account.bank_name"
                                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        >
                                            <option value="">Select bank</option>
                                            <option
                                                v-for="bank in bankOptions"
                                                :key="bank"
                                                :value="bank"
                                            >
                                                {{ bank }}
                                            </option>
                                        </select>
                                        <p v-if="accountError(index, 'bank_name')" class="mt-1 text-xs text-red-600">
                                            {{ accountError(index, "bank_name") }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">
                                            Account No
                                        </label>
                                        <input
                                            v-model="account.account_no"
                                            type="text"
                                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                            placeholder="Account number"
                                        />
                                        <p v-if="accountError(index, 'account_no')" class="mt-1 text-xs text-red-600">
                                            {{ accountError(index, "account_no") }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="block text-xs font-medium text-slate-600">
                                        Account Holder Name
                                    </label>
                                    <input
                                        v-model="account.account_name"
                                        type="text"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        placeholder="Account holder name (optional)"
                                    />
                                    <p v-if="accountError(index, 'account_name')" class="mt-1 text-xs text-red-600">
                                        {{ accountError(index, "account_name") }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <p v-if="form.errors.bank_accounts" class="mt-2 text-xs text-red-600">
                            {{ form.errors.bank_accounts }}
                        </p>
                    </section>

                    <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <i class="mdi mdi-map-marker-outline text-base text-indigo-600"></i>
                            Address
                        </div>
                        <textarea
                            v-model="form.address"
                            rows="3"
                            class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        ></textarea>
                    </section>

                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
                    <button
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                        @click="$emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        Update Sub Con
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
