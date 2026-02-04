<script setup>
import { useForm } from "@inertiajs/vue3";

const emit = defineEmits(["close", "saved"]);

const form = useForm({
    name: "",
    company_name: "",
    email: "",
    phone: "",
    address: "",
    bank: "",
});

function submit() {
    form.post(route("sub-cons.store"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit("close");
            emit("saved");
        },
    });
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">
                    Create Sub Con
                </h3>

                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="$emit('close')"
                >
                    <i class="mdi mdi-close text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full border rounded-md px-3 py-2"
                        placeholder="Sub con name"
                    />
                    <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Company
                    </label>
                    <input
                        v-model="form.company_name"
                        type="text"
                        class="mt-1 block w-full border rounded-md px-3 py-2"
                        placeholder="Company name"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Email
                    </label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full border rounded-md px-3 py-2"
                        placeholder="example@email.com"
                    />
                    <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Phone
                    </label>
                    <input
                        v-model="form.phone"
                        type="text"
                        class="mt-1 block w-full border rounded-md px-3 py-2"
                        placeholder="+60..."
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Bank
                    </label>
                    <input
                        v-model="form.bank"
                        type="text"
                        class="mt-1 block w-full border rounded-md px-3 py-2"
                        placeholder="Bank name"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Address
                    </label>
                    <textarea
                        v-model="form.address"
                        rows="3"
                        class="mt-1 block w-full border rounded-md px-3 py-2"
                        placeholder="Address"
                    ></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    @click="$emit('close')"
                >
                    Cancel
                </button>

                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="form.processing"
                    @click="submit"
                >
                    Create
                </button>
            </div>
        </div>
    </div>
</template>
