<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import html2canvas from 'html2canvas'
import { ref, inject, reactive } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

// Always safe — provides fallback {}
const page = usePage()
const user = page.props.auth?.user?.data ?? {}
const company = page.props.company
const toast = inject("toast")

// Main editable form
const form = reactive({
    name: user.name ?? "",
    email: user.email ?? "",
    profile_photo: null,
    contact_channels: user.contact_channels ?? {
        mobile: { enabled: false, value: "" },
        telegram: { enabled: false, value: "" },
        whatsapp: { enabled: false, value: "" },
    }
})

const previewPhoto = ref(user.profile_photo ?? null)

function onPhotoSelected(e) {
    const file = e.target.files[0]
    if (!file) return

    form.profile_photo = file
    previewPhoto.value = URL.createObjectURL(file)
}

async function downloadCard() {
    const card = document.getElementById("namecard")
    if (!card) return

    const canvas = await html2canvas(card, {
        scale: 3,
        useCORS: true
    })

    const link = document.createElement("a")
    link.download = `${form.name}-namecard.png`
    link.href = canvas.toDataURL("image/png")
    link.click()
}

async function saveProfile() {
    const fd = new FormData()

    fd.append("name", form.name)
    fd.append("email", form.email)

    Object.entries(form.contact_channels).forEach(([key, v]) => {
        fd.append(`contact_channels[${key}][enabled]`, v.enabled);
        fd.append(`contact_channels[${key}][value]`, v.value ?? "");
    });


    if (form.profile_photo) {
        fd.append("profile_photo", form.profile_photo)
    }

    router.post("/profile/update", fd, {
        onSuccess: () => toast.value.show("Profile updated!", "success")
    })
}
</script>

<template>
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Profile Information
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Update your account's profile information and email address.
        </p>
    </header>

    <form @submit.prevent="saveProfile" class="mt-6">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">

            <!-- LEFT SIDE -->
            <div class="space-y-6">

                <!-- PHOTO -->
                <div>
                    <InputLabel value="Profile Picture" />

                    <input 
                        type="file"
                        accept="image/*"
                        @change="onPhotoSelected"
                        class="mt-1 block w-full"
                    />

                    <div v-if="previewPhoto" class="mt-4">
                        <img :src="previewPhoto" class="w-24 h-24 rounded-full object-cover border shadow" />
                    </div>
                </div>

                <!-- NAME -->
                <div>
                    <InputLabel for="name" value="Name" />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.name"
                        required
                    />
                </div>

                <!-- EMAIL -->
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                    />
                </div>

                <!-- CONTACT CHANNELS -->
                <div>
                    <InputLabel value="Contact Channels" class="mb-2" />

                    <!-- MOBILE -->
                    <div class="flex items-center gap-3 mb-3">
                        <input type="checkbox" v-model="form.contact_channels.mobile.enabled" />
                        <label class="w-28 font-medium">Mobile</label>
                        <input
                            type="text"
                            class="border rounded p-2 flex-1"
                            placeholder="+60123456789"
                            v-model="form.contact_channels.mobile.value"
                            :disabled="!form.contact_channels.mobile.enabled"
                        />
                    </div>

                    <!-- TELEGRAM -->
                    <div class="flex items-center gap-3 mb-3">
                        <input type="checkbox" v-model="form.contact_channels.telegram.enabled" />
                        <label class="w-28 font-medium">Telegram</label>
                        <input
                            type="text"
                            class="border rounded p-2 flex-1"
                            placeholder="@telegram_username"
                            v-model="form.contact_channels.telegram.value"
                            :disabled="!form.contact_channels.telegram.enabled"
                        />
                    </div>

                    <!-- WHATSAPP -->
                    <div class="flex items-center gap-3 mb-3">
                        <input type="checkbox" v-model="form.contact_channels.whatsapp.enabled" />
                        <label class="w-28 font-medium">WhatsApp</label>
                        <input
                            type="text"
                            class="border rounded p-2 flex-1"
                            placeholder="+60123456789"
                            v-model="form.contact_channels.whatsapp.value"
                            :disabled="!form.contact_channels.whatsapp.enabled"
                        />
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDE (NAMECARD) -->
            <div class="flex flex-col items-center space-y-6">
                <!-- NAMECARD -->
                <div id="namecard"
                    class="p-8 rounded-3xl shadow-xl border 
                        bg-gradient-to-b from-gray-800 to-black 
                        text-white w-full max-w-xs select-none text-center relative">

                    <!-- Company Logo -->
                    <div class="mb-5 flex justify-center">
                        <img 
                            :src="company?.logo 
                                ? (company.logo.startsWith('http') ? company.logo : '/storage/' + company.logo)
                                : '/asset/img/sample-logo.png'"
                            class="h-10 object-contain filter invert"
                        />
                    </div>

                    <!-- Profile Photo OR Placeholder -->
                    <div class="flex justify-center mb-4">
                        <div class="w-24 h-24 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden shadow">
                            <img
                                v-if="previewPhoto"
                                :src="previewPhoto"
                                class="w-full h-full object-cover"
                            />
                            <svg
                                v-else
                                class="w-14 h-14 text-gray-600"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Name -->
                    <div class="text-xl font-bold text-white tracking-wide">
                        {{ form.name }}
                    </div>

                    <!-- Job Title -->
                    <div class="text-gray-300 text-sm mt-1 capitalize">
                        {{ user.departments?.[0]?.role ?? "Staff" }}
                    </div>

                    <!-- Company Name -->
                    <div class="text-gray-300 font-semibold mt-1">
                        {{ company?.company_name ?? "Your Company Name" }}
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-gray-600 my-6"></div>

                    <!-- Contact Section -->
                    <div class="mt-6 space-y-4 text-gray-200 text-[13px]">

                        <!-- LINE 1 — Mobile -->
                        <div
                            v-if="form.contact_channels.mobile?.enabled && form.contact_channels.mobile.value"
                            class="flex items-center gap-3 justify-center"
                        >
                            <img src="/asset/img/phone.png" class="w-5 h-5 opacity-80" />
                            <span>{{ form.contact_channels.mobile.value }}</span>
                        </div>

                        <!-- LINE 2 — Office + Email -->
                        <div class="flex justify-center gap-6">

                            <div
                                v-if="company?.office_number"
                                class="flex items-center gap-2"
                            >
                                <img src="/asset/img/phone.png" class="w-5 h-5 opacity-80" />
                                <span>{{ company.office_number }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <img src="/asset/img/email.png" class="w-5 h-5 opacity-80" />
                                <span>{{ form.email }}</span>
                            </div>
                        </div>

                        <!-- LINE 3 — Telegram + WhatsApp -->
                        <div class="flex justify-center gap-6">

                            <div
                                v-if="form.contact_channels.telegram?.enabled && form.contact_channels.telegram.value"
                                class="flex items-center gap-2"
                            >
                                <img src="/asset/img/telegram.png" class="w-5 h-5 opacity-80" />
                                <span>{{ form.contact_channels.telegram.value }}</span>
                            </div>

                            <div
                                v-if="form.contact_channels.whatsapp?.enabled && form.contact_channels.whatsapp.value"
                                class="flex items-center gap-2"
                            >
                                <img src="/asset/img/whatsapp.png" class="w-5 h-5 opacity-80" />
                                <span>{{ form.contact_channels.whatsapp.value }}</span>
                            </div>

                        </div>

                        <!-- Address -->
                        <div
                            v-if="company?.address"
                            class="flex items-center gap-3 justify-center text-center"
                        >
                            <img src="/asset/img/location.png" class="w-5 h-5 opacity-80" />
                            <span class="block max-w-[200px] leading-tight text-gray-300">
                                {{ company.address }}
                            </span>
                        </div>

                    </div>
                </div>

                <PrimaryButton class="mx-auto" @click="downloadCard">
                    Download Namecard
                </PrimaryButton>
            </div>
        </div>

        <!-- SAVE BUTTON -->
        <div class="flex items-center gap-4 mt-6">
            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        </div>

    </form>
</section>
</template>
