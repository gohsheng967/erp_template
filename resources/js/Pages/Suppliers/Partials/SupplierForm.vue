<script setup>
import { computed, inject } from "vue";
import { useForm } from "@inertiajs/vue3";

const toast = inject("toast", null);

const props = defineProps({
    supplier: {
        type: Object,
        default: null,
    },
    mode: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(["close", "saved"]);
const isEdit = computed(() => props.mode === "edit");

const form = useForm({
    company_name: props.supplier?.company_name ?? "",
    registration_no: props.supplier?.registration_no ?? "",
    contact_person: props.supplier?.contact_person ?? "",
    contact_phone: props.supplier?.contact_phone ?? "",
    email: props.supplier?.email ?? "",
    address: props.supplier?.address ?? "",
    status: props.supplier?.status ?? "active",
    create_login_account: false,
    manage_login_account: !!props.supplier?.login_identity_no,
    login_identity_no: props.supplier?.login_identity_no ?? "",
    login_email: props.supplier?.login_email ?? "",
    login_password: "",
    login_status: props.supplier?.login_status ?? 1,
});

const shouldShowPortalFields = computed(() =>
    isEdit.value ? form.manage_login_account : form.create_login_account
);

function submit() {
    if (isEdit.value) {
        form.post(route("suppliers.update", props.supplier.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show("Supplier updated successfully.", "success");
                emit("saved");
                emit("close");
            },
            onError: (errors) => {
                const firstError = Object.values(errors ?? {})[0] || "Failed to update supplier.";
                toast?.value?.show(firstError, "error");
            },
        });
        return;
    }

    form.post(route("suppliers.store"), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Supplier created successfully.", "success");
            emit("saved");
            emit("close");
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0] || "Failed to create supplier.";
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
        <div class="mx-auto flex h-full max-w-3xl items-center">
            <div class="w-full overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div class="border-b border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">
                                {{ isEdit ? "Edit Supplier" : "Create Supplier" }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Maintain supplier profile and portal access settings.
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
                        <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <i class="mdi mdi-truck-delivery-outline text-base text-indigo-600"></i>
                            Supplier Information
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Company Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.company_name"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    placeholder="Supplier company name"
                                />
                                <p v-if="form.errors.company_name" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.company_name }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Registration No <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.registration_no"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    placeholder="Registration number"
                                />
                                <p v-if="form.errors.registration_no" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.registration_no }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">Contact Person</label>
                                <input
                                    v-model="form.contact_person"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    placeholder="PIC name"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">Contact Phone</label>
                                <input
                                    v-model="form.contact_phone"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    placeholder="+60..."
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">Email</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    placeholder="example@email.com"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div v-if="isEdit">
                                <label class="block text-sm font-medium text-slate-700">
                                    Status <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.status"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="blacklisted">Blacklisted</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-slate-700">Address</label>
                            <textarea
                                v-model="form.address"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                placeholder="Supplier address"
                            />
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <div class="mb-3 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <i class="mdi mdi-account-key-outline text-base text-indigo-600"></i>
                                Supplier Login Account
                            </div>
                            <label v-if="isEdit" class="inline-flex items-center gap-2 text-sm text-slate-600">
                                <input
                                    v-model="form.manage_login_account"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Manage portal login
                            </label>
                            <label v-else class="inline-flex items-center gap-2 text-sm text-slate-600">
                                <input
                                    v-model="form.create_login_account"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Create portal login
                            </label>
                        </div>

                        <div v-if="shouldShowPortalFields" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Login ID (Identity No)
                                </label>
                                <input
                                    v-model="form.login_identity_no"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    placeholder="Supplier login ID"
                                />
                                <p v-if="form.errors.login_identity_no" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.login_identity_no }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Login Email
                                </label>
                                <input
                                    v-model="form.login_email"
                                    type="email"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    placeholder="login email"
                                />
                                <p v-if="form.errors.login_email" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.login_email }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    Portal Status
                                </label>
                                <select
                                    v-model="form.login_status"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                >
                                    <option :value="1">Active</option>
                                    <option :value="0">Inactive</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700">
                                    {{ isEdit ? "New Password (Optional)" : "Initial Password (Optional)" }}
                                </label>
                                <input
                                    v-model="form.login_password"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    :placeholder="isEdit ? 'Leave empty to keep current password' : 'Leave empty to auto-set from Login ID'"
                                />
                                <p v-if="form.errors.login_password" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.login_password }}
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="border-t border-slate-200 bg-slate-50/80 px-6 py-4 sm:px-8">
                    <div class="flex items-center justify-end gap-2">
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white"
                            @click="$emit('close')"
                        >
                            Cancel
                        </button>
                        <button
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            <i class="mdi mdi-content-save-outline"></i>
                            {{ isEdit ? "Update Supplier" : "Create Supplier" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
