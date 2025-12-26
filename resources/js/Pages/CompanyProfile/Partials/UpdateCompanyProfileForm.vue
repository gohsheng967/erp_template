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
            <h2 class="text-lg font-medium text-gray-900">
                Company Profile
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Update your company information and branding settings.
            </p>
        </header>

        <form @submit.prevent="save" class="mt-6 space-y-6">

            <!-- COMPANY NAME -->
            <div>
                <InputLabel value="Company Name" />
                <TextInput
                    class="mt-1 w-full"
                    v-model="form.company_name"
                />

                <InputError
                    :message="$page.props.errors?.company_name"
                    class="mt-2"
                />
            </div>

            <!-- REGISTRATION NUMBER -->
            <div>
                <InputLabel value="Registration Number" />
                <TextInput
                    class="mt-1 w-full"
                    v-model="form.company_reg_no"
                />

                <InputError
                    :message="$page.props.errors?.company_reg_no"
                    class="mt-2"
                />
            </div>

            <!-- ADDRESS -->
            <div>
                <InputLabel value="Company Address" />
                <textarea
                    class="mt-1 w-full border-gray-300 rounded-lg"
                    rows="3"
                    v-model="form.address"
                ></textarea>

                <InputError
                    :message="$page.props.errors?.address"
                    class="mt-2"
                />
            </div>

            <!-- OFFICE NUMBER -->
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

            <!-- COMPANY LOGO -->
            <div>
                <InputLabel value="Company Logo" />

                <input
                    type="file"
                    accept="image/*"
                    @change="onLogoSelect"
                    class="mt-1"
                />

                <div v-if="previewLogo" class="mt-4">
                    <img :src="previewLogo" class="h-24 rounded shadow border" />
                </div>

                <InputError
                    :message="$page.props.errors?.logo"
                    class="mt-2"
                />
            </div>

            <!-- SAVE BUTTON -->
            <div class="flex items-center gap-4">
                <PrimaryButton>Save</PrimaryButton>
            </div>
        </form>
    </section>
</template>
