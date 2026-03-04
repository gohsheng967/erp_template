<script setup>
import { reactive, ref, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import InputError from '@/Components/InputError.vue'

const toast = inject("toast", null)

// Get data passed from controller (if any)
const page = usePage()
const profile = page.props.company ?? {}

// Form data
const form = reactive({
    company_name: profile.company_name ?? "",
    company_reg_no: profile.company_reg_no ?? "",
    address: profile.address ?? "",
    office_number: profile.office_number ?? "",
    logo: null,
})

// Preview logo
const previewLogo = ref(
    profile.logo ? `/storage/${profile.logo}` : null
)

// File handler
function onLogoSelect(e) {
    const file = e.target.files[0]
    if (!file) return

    form.logo = file
    previewLogo.value = URL.createObjectURL(file)
}

// Submit
function save() {
    const fd = new FormData()

    fd.append("company_name", form.company_name)
    fd.append("company_reg_no", form.company_reg_no)
    fd.append("address", form.address)
    fd.append("office_number", form.office_number)

    if (form.logo) {
        fd.append("logo", form.logo)
    }

    router.post("/company-profile", fd, {
        forceFormData: true,

        onSuccess: () => {
            toast?.value?.show("Company profile updated!", "success")
        },

        onError: () => {
            toast?.value?.show("Validation failed.", "error")
        },
    })
}

</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-gray-900">
                Company Profile
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Update your company information and branding settings.
            </p>
        </header>

        <form @submit.prevent="save" class="mt-6 space-y-6">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <InputLabel value="Company Name" />
                    <TextInput
                        class="mt-1 w-full"
                        v-model="form.company_name"
                        placeholder="Infinite Sdn Bhd"
                    />

                    <InputError
                        :message="$page.props.errors?.company_name"
                        class="mt-2"
                    />
                </div>

                <div>
                    <InputLabel value="Registration Number" />
                    <TextInput
                        class="mt-1 w-full"
                        v-model="form.company_reg_no"
                        placeholder="202601234567"
                    />

                    <InputError
                        :message="$page.props.errors?.company_reg_no"
                        class="mt-2"
                    />
                </div>
            </div>

            <div>
                <InputLabel value="Company Address" />
                <textarea
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows="4"
                    v-model="form.address"
                    placeholder="No. 1, Jalan Example, Kuala Lumpur"
                ></textarea>

                <InputError
                    :message="$page.props.errors?.address"
                    class="mt-2"
                />
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <InputLabel value="Office Number" />
                    <TextInput
                        class="mt-1 w-full"
                        v-model="form.office_number"
                        placeholder="+60123456789"
                    />

                    <InputError
                        :message="$page.props.errors?.office_number"
                        class="mt-2"
                    />
                </div>
            </div>

            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50/70 p-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-1">
                        <InputLabel value="Company Logo" />
                        <p class="text-xs text-gray-500">
                            Upload a clear image for document headers. PNG/JPG recommended.
                        </p>
                        <input
                            type="file"
                            accept="image/*"
                            @change="onLogoSelect"
                            class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-100"
                        />
                        <InputError
                            :message="$page.props.errors?.logo"
                            class="mt-2"
                        />
                    </div>

                    <div class="min-w-[9rem]">
                        <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Preview</div>
                        <div class="flex h-28 w-40 items-center justify-center rounded-lg border border-gray-200 bg-white">
                            <img v-if="previewLogo" :src="previewLogo" class="max-h-24 rounded object-contain" />
                            <span v-else class="text-xs text-gray-400">No logo</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton>Save</PrimaryButton>
            </div>
        </form>
    </section>
</template>
