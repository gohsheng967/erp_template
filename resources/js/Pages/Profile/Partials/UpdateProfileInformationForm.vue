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

    if (document.fonts?.ready) {
        await document.fonts.ready
    }
    await new Promise(requestAnimationFrame)

    const canvas = await html2canvas(card, {
        scale: 3,
        useCORS: true,
        letterRendering: true
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
                <div
                    id="namecard"
                    class="relative overflow-hidden p-7 rounded-2xl shadow-2xl
                        border border-white/10
                        text-white w-full max-w-sm select-none"
                    style="
                        line-height: 1.3;
                        -webkit-font-smoothing: antialiased;
                        background-image:
                            radial-gradient(220px 220px at 110% -10%, rgba(99,102,241,0.25), transparent 60%),
                            radial-gradient(220px 220px at -10% 110%, rgba(34,211,238,0.18), transparent 60%),
                            linear-gradient(135deg, #0f172a 0%, #1f2937 50%, #0f172a 100%);
                    "
                >
                    <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-indigo-500/20"></div>
                    <div class="absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-cyan-400/10"></div>

                    <!-- Header -->
                    <div class="relative z-10 flex items-center justify-between mb-6">
                        <div class="text-[11px] tracking-widest text-gray-300 uppercase">
                            {{ company?.company_name ?? "Your Company Name" }}
                        </div>
                        <div class="text-[10px] uppercase tracking-widest text-gray-400 border border-white/10 rounded-full px-2 py-0.5">
                            Namecard
                        </div>
                    </div>

                    <!-- Profile -->
                    <div class="relative z-10 flex items-center gap-5 mb-6">
                        <div class="w-20 h-20 rounded-xl bg-gray-200/10 flex items-center justify-center overflow-hidden ring-1 ring-white/10 shadow-inner">
                            <img
                                v-if="previewPhoto"
                                :src="previewPhoto"
                                class="w-full h-full object-cover"
                            />
                            <svg
                                v-else
                                class="w-10 h-10 text-gray-400"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-semibold tracking-wide leading-tight">
                                {{ form.name }}
                            </div>
                            <div class="text-gray-300 text-sm mt-1 capitalize leading-tight">
                                {{ user.departments?.[0]?.role ?? "Staff" }}
                            </div>
                            <div class="text-gray-400 text-xs mt-1 leading-tight">
                                {{ user.departments?.[0]?.name ?? "Department" }}
                            </div>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="relative z-10 border-t border-white/10 my-5"></div>

                    <!-- Contact Section -->
                    <div class="relative z-10">
                        <div class="text-[11px] uppercase tracking-widest text-gray-400 mb-2 leading-tight">
                            Personal Contact
                        </div>
                        <div class="space-y-2 text-gray-200 text-[13px] mb-4">
                            <div
                                v-if="form.contact_channels.mobile?.enabled && form.contact_channels.mobile.value"
                                class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-2"
                            >
                                <img src="/asset/img/phone.png" class="w-4 h-4 opacity-80" />
                                <span>{{ form.contact_channels.mobile.value }}</span>
                            </div>

                            <div class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-2">
                                <img src="/asset/img/email.png" class="w-4 h-4 opacity-80" />
                                <span>{{ form.email }}</span>
                            </div>

                            <div
                                v-if="form.contact_channels.telegram?.enabled && form.contact_channels.telegram.value"
                                class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-2"
                            >
                                <img src="/asset/img/telegram.png" class="w-4 h-4 opacity-80" />
                                <span>{{ form.contact_channels.telegram.value }}</span>
                            </div>

                            <div
                                v-if="form.contact_channels.whatsapp?.enabled && form.contact_channels.whatsapp.value"
                                class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-2"
                            >
                                <img src="/asset/img/whatsapp.png" class="w-4 h-4 opacity-80" />
                                <span>{{ form.contact_channels.whatsapp.value }}</span>
                            </div>
                        </div>

                        <div class="text-[11px] uppercase tracking-widest text-gray-400 mb-2 leading-tight">
                            Company Contact
                        </div>
                        <div class="space-y-2 text-gray-200 text-[13px]">
                            <div
                                v-if="company?.office_number"
                                class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-2"
                            >
                                <img src="/asset/img/phone.png" class="w-4 h-4 opacity-80" />
                                <span>{{ company.office_number }}</span>
                            </div>

                            <div
                                v-if="company?.address"
                                class="flex items-start gap-3 rounded-lg bg-white/5 px-3 py-2"
                            >
                                <img src="/asset/img/location.png" class="w-4 h-4 opacity-80 mt-0.5" />
                                <span class="block leading-tight text-gray-300">
                                    {{ company.address }}
                                </span>
                            </div>
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
