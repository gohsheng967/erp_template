<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import html2canvas from 'html2canvas'
import { ref, inject, reactive, onBeforeUnmount, nextTick, watch } from 'vue'
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
    signature_file: null,
    signature_drawn: "",
    contact_channels: user.contact_channels ?? {
        mobile: { enabled: false, value: "" },
        telegram: { enabled: false, value: "" },
        whatsapp: { enabled: false, value: "" },
    }
})

const previewPhoto = ref(user.profile_photo ?? null)
const photoFileName = ref("")
const photoVisible = ref(true)
const showPhotoEditor = ref(false)
const previewSignature = ref(user.signature ?? null)
const signatureFileName = ref("")
const signatureMode = ref("upload")
const signatureVisible = ref(true)
const showSignatureEditor = ref(false)
const canvasRef = ref(null)
let canvas = null
let ctx = null
let drawing = false

function onPhotoSelected(e) {
    const file = e.target.files[0]
    if (!file) return

    form.profile_photo = file
    photoFileName.value = file.name
    previewPhoto.value = URL.createObjectURL(file)
}

function clearSelectedPhoto() {
    form.profile_photo = null
    photoFileName.value = ""
    previewPhoto.value = user.profile_photo ?? null
}

function openPhotoEditor() {
    showPhotoEditor.value = true
}

function closePhotoEditor() {
    showPhotoEditor.value = false
}

function onSignatureSelected(e) {
    const file = e.target.files[0]
    if (!file) return

    const isPng = file.type === "image/png"
    if (!isPng) {
        toast?.value?.show("Only PNG signature is allowed", "error")
        e.target.value = ""
        return
    }

    form.signature_file = file
    form.signature_drawn = ""
    signatureFileName.value = file.name
    previewSignature.value = URL.createObjectURL(file)
}

function setupCanvas() {
    canvas = canvasRef.value
    if (!canvas) return

    ctx = canvas.getContext("2d")
    ctx.fillStyle = "#ffffff"
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.strokeStyle = "#111827"
    ctx.lineWidth = 2
    ctx.lineCap = "round"
    ctx.lineJoin = "round"
}

function bindCanvasEvents() {
    if (!canvas) return
    canvas.addEventListener("mousedown", startDraw)
    canvas.addEventListener("mousemove", moveDraw)
    canvas.addEventListener("mouseup", endDraw)
    canvas.addEventListener("mouseleave", endDraw)
    canvas.addEventListener("touchstart", startDraw, { passive: true })
    canvas.addEventListener("touchmove", moveDraw, { passive: false })
    canvas.addEventListener("touchend", endDraw)
}

function unbindCanvasEvents() {
    if (!canvas) return
    canvas.removeEventListener("mousedown", startDraw)
    canvas.removeEventListener("mousemove", moveDraw)
    canvas.removeEventListener("mouseup", endDraw)
    canvas.removeEventListener("mouseleave", endDraw)
    canvas.removeEventListener("touchstart", startDraw)
    canvas.removeEventListener("touchmove", moveDraw)
    canvas.removeEventListener("touchend", endDraw)
}

function pointFromEvent(e) {
    const rect = canvas.getBoundingClientRect()
    const source = e.touches ? e.touches[0] : e
    return {
        x: (source.clientX - rect.left) * (canvas.width / rect.width),
        y: (source.clientY - rect.top) * (canvas.height / rect.height),
    }
}

function startDraw(e) {
    if (!ctx || !canvas) return
    drawing = true
    const p = pointFromEvent(e)
    ctx.beginPath()
    ctx.moveTo(p.x, p.y)
}

function moveDraw(e) {
    if (!drawing || !ctx || !canvas) return
    e.preventDefault()
    const p = pointFromEvent(e)
    ctx.lineTo(p.x, p.y)
    ctx.stroke()
}

function endDraw() {
    drawing = false
}

function clearSignaturePad() {
    if (!ctx || !canvas) return
    ctx.clearRect(0, 0, canvas.width, canvas.height)
    ctx.fillStyle = "#ffffff"
    ctx.fillRect(0, 0, canvas.width, canvas.height)
}

function saveDrawnSignature() {
    if (!canvas) return

    form.signature_drawn = canvas.toDataURL("image/png")
    form.signature_file = null
    signatureFileName.value = ""
    previewSignature.value = form.signature_drawn
    toast?.value?.show("Drawn signature saved", "success")
}

async function openSignatureEditor() {
    showSignatureEditor.value = true
    await nextTick()
    setupCanvas()
    bindCanvasEvents()
}

function closeSignatureEditor() {
    unbindCanvasEvents()
    showSignatureEditor.value = false
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

    if (form.signature_file) {
        fd.append("signature_file", form.signature_file)
    }

    if (form.signature_drawn) {
        fd.append("signature_drawn", form.signature_drawn)
    }

    router.post("/profile/update", fd, {
        forceFormData: true,
        onSuccess: () => toast?.value?.show("Profile updated!", "success"),
        onError: (errors) => {
            const first = Object.values(errors ?? {})[0]
            toast?.value?.show(first || "Profile update failed.", "error")
        },
    })
}

onBeforeUnmount(() => {
    unbindCanvasEvents()
})

watch(signatureMode, async (mode) => {
    if (!showSignatureEditor.value) return

    if (mode === "draw") {
        await nextTick()
        setupCanvas()
        unbindCanvasEvents()
        bindCanvasEvents()
        return
    }

    unbindCanvasEvents()
})
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
                <div class="rounded-xl border bg-gray-50 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-semibold text-gray-800">Profile Picture</div>
                            <p class="text-xs text-gray-500">Shown in top bar and profile card.</p>
                        </div>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs rounded border bg-white hover:bg-gray-100"
                            @click="openPhotoEditor"
                        >
                            Edit
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs rounded border bg-white hover:bg-gray-100"
                            @click="photoVisible = !photoVisible"
                        >
                            {{ photoVisible ? 'Hide Photo' : 'Show Photo' }}
                        </button>
                    </div>

                    <div v-if="photoVisible" class="rounded-lg border bg-white p-3 min-h-[90px] flex items-center justify-center">
                        <img
                            v-if="previewPhoto"
                            :src="previewPhoto"
                            class="w-20 h-20 rounded-full object-cover border shadow"
                        />
                        <p v-else class="text-xs text-gray-400">No profile picture yet</p>
                    </div>
                </div>

                <!-- SIGNATURE -->
                <div class="rounded-xl border bg-gray-50 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-semibold text-gray-800">Signature</div>
                            <p class="text-xs text-gray-500">Use for documents and approvals.</p>
                        </div>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs rounded border bg-white hover:bg-gray-100"
                            @click="openSignatureEditor"
                        >
                            Edit
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs rounded border bg-white hover:bg-gray-100"
                            @click="signatureVisible = !signatureVisible"
                        >
                            {{ signatureVisible ? 'Hide Signature' : 'Show Signature' }}
                        </button>
                    </div>

                    <div v-if="signatureVisible" class="rounded-lg border bg-white p-3 min-h-[90px] flex items-center">
                        <img
                            v-if="previewSignature"
                            :src="previewSignature"
                            class="max-h-16 object-contain"
                        />
                        <p v-else class="text-xs text-gray-400">No signature yet</p>
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

    <div
        v-if="showPhotoEditor"
        class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
        @click.self="closePhotoEditor"
    >
        <div class="w-full max-w-xl rounded-2xl bg-white shadow-2xl border overflow-hidden">
            <div class="px-5 py-4 border-b flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Edit Profile Picture</h3>
                    <p class="text-xs text-gray-500">Upload a clear image for your account avatar.</p>
                </div>
                <button
                    type="button"
                    class="text-gray-500 hover:text-gray-800"
                    @click="closePhotoEditor"
                >
                    Close
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div class="space-y-3">
                    <InputLabel value="Upload Profile Picture" />
                    <input
                        type="file"
                        accept="image/*"
                        @change="onPhotoSelected"
                        class="block w-full"
                    />
                    <p v-if="photoFileName" class="text-xs text-gray-500">
                        Selected: {{ photoFileName }}
                    </p>
                </div>

                <div class="rounded-lg border bg-gray-50 p-4 min-h-[180px] flex items-center justify-center">
                    <img
                        v-if="previewPhoto"
                        :src="previewPhoto"
                        class="w-36 h-36 rounded-full object-cover border shadow"
                    />
                    <p v-else class="text-xs text-gray-400">No profile picture preview</p>
                </div>
            </div>

            <div class="px-5 py-4 border-t flex items-center justify-between">
                <button
                    type="button"
                    class="px-4 py-2 rounded border bg-white hover:bg-gray-50"
                    @click="clearSelectedPhoto"
                >
                    Reset
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded bg-gray-900 text-white hover:bg-black"
                    @click="closePhotoEditor"
                >
                    Done
                </button>
            </div>
        </div>
    </div>

    <div
        v-if="showSignatureEditor"
        class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
        @click.self="closeSignatureEditor"
    >
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl border overflow-hidden">
            <div class="px-5 py-4 border-b flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Edit Signature</h3>
                    <p class="text-xs text-gray-500">Upload PNG or draw your signature.</p>
                </div>
                <button
                    type="button"
                    class="text-gray-500 hover:text-gray-800"
                    @click="closeSignatureEditor"
                >
                    Close
                </button>
            </div>

            <div class="px-5 pt-4">
                <div class="inline-flex rounded-lg border bg-gray-50 p-1">
                    <button
                        type="button"
                        class="px-3 py-1.5 text-sm rounded"
                        :class="signatureMode === 'upload' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"
                        @click="signatureMode = 'upload'"
                    >
                        Upload PNG
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-sm rounded"
                        :class="signatureMode === 'draw' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"
                        @click="signatureMode = 'draw'"
                    >
                        Draw
                    </button>
                </div>
            </div>

            <div class="p-5 space-y-4">
                <div v-if="signatureMode === 'upload'" class="space-y-3">
                    <InputLabel value="Upload Signature (PNG only)" />
                    <input
                        type="file"
                        accept="image/png"
                        @change="onSignatureSelected"
                        class="block w-full"
                    />
                    <p v-if="signatureFileName" class="text-xs text-gray-500">
                        Selected: {{ signatureFileName }}
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <InputLabel value="Draw Signature" />
                    <canvas
                        ref="canvasRef"
                        width="720"
                        height="220"
                        class="w-full border rounded bg-white touch-none"
                    />
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded bg-gray-200 hover:bg-gray-300"
                            @click="clearSignaturePad"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded bg-gray-800 text-white hover:bg-black"
                            @click="saveDrawnSignature"
                        >
                            Save Drawn Signature
                        </button>
                    </div>
                </div>

                <div class="rounded-lg border bg-gray-50 p-3 min-h-[90px] flex items-center">
                    <img v-if="previewSignature" :src="previewSignature" class="max-h-16 object-contain" />
                    <p v-else class="text-xs text-gray-400">No signature preview</p>
                </div>
            </div>

            <div class="px-5 py-4 border-t flex justify-end">
                <button
                    type="button"
                    class="px-4 py-2 rounded bg-gray-900 text-white hover:bg-black"
                    @click="closeSignatureEditor"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</section>
</template>
