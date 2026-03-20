<script setup>
import { inject, onBeforeUnmount, ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DeleteConfirmation from "@/Components/DeleteConfirmation.vue";

const props = defineProps({
    sites: {
        type: Array,
        default: () => [],
    },
});

const toast = inject("toast", null);

const createForm = useForm({
    site_name: "",
    address: "",
    longitude: "",
    latitude: "",
    image: null,
});

const editForm = useForm({
    id: null,
    site_name: "",
    address: "",
    longitude: "",
    latitude: "",
    image: null,
});

const createImageInput = ref(null);
const editImageInput = ref(null);
const editingId = ref(null);
const deletingSite = ref(null);
const showDelete = ref(false);

const createPreviewUrl = ref(null);
const editPreviewUrl = ref(null);

watch(
    () => createForm.image,
    (file) => {
        if (createPreviewUrl.value) {
            URL.revokeObjectURL(createPreviewUrl.value);
            createPreviewUrl.value = null;
        }

        if (file) {
            createPreviewUrl.value = URL.createObjectURL(file);
        }
    }
);

watch(
    () => editForm.image,
    (file) => {
        if (editPreviewUrl.value) {
            URL.revokeObjectURL(editPreviewUrl.value);
            editPreviewUrl.value = null;
        }

        if (file) {
            editPreviewUrl.value = URL.createObjectURL(file);
        }
    }
);

onBeforeUnmount(() => {
    if (createPreviewUrl.value) {
        URL.revokeObjectURL(createPreviewUrl.value);
    }

    if (editPreviewUrl.value) {
        URL.revokeObjectURL(editPreviewUrl.value);
    }
});

function setCreateImage(event) {
    createForm.image = event.target.files?.[0] ?? null;
}

function setEditImage(event) {
    editForm.image = event.target.files?.[0] ?? null;
}

function openCreateImagePicker() {
    createImageInput.value?.click();
}

function openEditImagePicker() {
    editImageInput.value?.click();
}

function clearCreateImage() {
    createForm.image = null;
    if (createImageInput.value) {
        createImageInput.value.value = "";
    }
}

function clearEditImage() {
    editForm.image = null;
    if (editImageInput.value) {
        editImageInput.value.value = "";
    }
}

function formatFileSize(bytes) {
    if (!bytes || bytes <= 0) return "0 B";
    const units = ["B", "KB", "MB", "GB"];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** index;
    return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

function submitCreate() {
    createForm.post(route("sites.store"), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast?.value?.show("Site created successfully.", "success");
            createForm.reset();
            if (createImageInput.value) {
                createImageInput.value.value = "";
            }
        },
    });
}

function startEdit(site) {
    editingId.value = site.id;
    editForm.id = site.id;
    editForm.site_name = site.site_name;
    editForm.address = site.address;
    editForm.longitude = site.longitude;
    editForm.latitude = site.latitude;
    editForm.image = null;
    editForm.clearErrors();
    if (editImageInput.value) {
        editImageInput.value.value = "";
    }
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function submitEdit() {
    editForm.patch(route("sites.update", editForm.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast?.value?.show("Site updated successfully.", "success");
            cancelEdit();
        },
    });
}

function confirmDelete(site) {
    deletingSite.value = site;
    showDelete.value = true;
}

function deleteSite() {
    if (!deletingSite.value) return;

    router.delete(route("sites.destroy", deletingSite.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show("Site deleted successfully.", "success");
            showDelete.value = false;
            deletingSite.value = null;
        },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Sites</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900">Add Site</h3>

                    <form @submit.prevent="submitCreate" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Site Name</label>
                            <input v-model="createForm.site_name" type="text" class="w-full rounded border px-3 py-1.5 text-sm" />
                            <div v-if="createForm.errors.site_name" class="mt-1 text-xs text-red-600">
                                {{ createForm.errors.site_name }}
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Address</label>
                            <input v-model="createForm.address" type="text" class="w-full rounded border px-3 py-1.5 text-sm" />
                            <div v-if="createForm.errors.address" class="mt-1 text-xs text-red-600">
                                {{ createForm.errors.address }}
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Longitude</label>
                            <input v-model="createForm.longitude" type="number" step="0.0000001" class="w-full rounded border px-3 py-1.5 text-sm" />
                            <div v-if="createForm.errors.longitude" class="mt-1 text-xs text-red-600">
                                {{ createForm.errors.longitude }}
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Latitude</label>
                            <input v-model="createForm.latitude" type="number" step="0.0000001" class="w-full rounded border px-3 py-1.5 text-sm" />
                            <div v-if="createForm.errors.latitude" class="mt-1 text-xs text-red-600">
                                {{ createForm.errors.latitude }}
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-700">Image Attachment</label>
                            <input ref="createImageInput" type="file" accept="image/*" class="hidden" @change="setCreateImage" />

                            <button
                                type="button"
                                class="w-full rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-left transition hover:border-blue-300 hover:bg-blue-50"
                                @click="openCreateImagePicker"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800">
                                            {{ createForm.image ? "Image Selected" : "Upload Site Image" }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ createForm.image ? createForm.image.name : "Click to browse image file (JPG, PNG, WEBP)." }}
                                        </p>
                                        <p v-if="createForm.image" class="mt-1 text-xs text-gray-500">
                                            {{ formatFileSize(createForm.image.size) }}
                                        </p>
                                    </div>
                                    <span class="rounded bg-white px-2 py-1 text-xs font-medium text-gray-600 shadow-sm">
                                        {{ createForm.image ? "Replace" : "Choose File" }}
                                    </span>
                                </div>
                            </button>

                            <div v-if="createForm.errors.image" class="mt-1 text-xs text-red-600">
                                {{ createForm.errors.image }}
                            </div>
                        </div>

                        <div v-if="createPreviewUrl" class="md:col-span-2 rounded-lg border bg-white p-3">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-medium text-gray-700">Preview</p>
                                <button
                                    type="button"
                                    class="rounded bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100"
                                    @click="clearCreateImage"
                                >
                                    Remove
                                </button>
                            </div>
                            <img :src="createPreviewUrl" alt="Preview" class="mt-2 h-24 w-24 rounded border object-cover" />
                        </div>

                        <div class="md:col-span-2 flex justify-end">
                            <button type="submit" class="rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700" :disabled="createForm.processing">
                                Add Site
                            </button>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                                <th class="p-3 text-left">Site</th>
                                <th class="p-3 text-left">Address</th>
                                <th class="p-3 text-left">Longitude</th>
                                <th class="p-3 text-left">Latitude</th>
                                <th class="p-3 text-left">Image</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="site in props.sites" :key="site.id" class="border-b align-top">
                                <template v-if="editingId === site.id">
                                    <td class="p-3">
                                        <input v-model="editForm.site_name" type="text" class="w-full rounded border px-3 py-1.5 text-sm" />
                                        <div v-if="editForm.errors.site_name" class="mt-1 text-xs text-red-600">
                                            {{ editForm.errors.site_name }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <input v-model="editForm.address" type="text" class="w-full rounded border px-3 py-1.5 text-sm" />
                                        <div v-if="editForm.errors.address" class="mt-1 text-xs text-red-600">
                                            {{ editForm.errors.address }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <input v-model="editForm.longitude" type="number" step="0.0000001" class="w-full rounded border px-3 py-1.5 text-sm" />
                                        <div v-if="editForm.errors.longitude" class="mt-1 text-xs text-red-600">
                                            {{ editForm.errors.longitude }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <input v-model="editForm.latitude" type="number" step="0.0000001" class="w-full rounded border px-3 py-1.5 text-sm" />
                                        <div v-if="editForm.errors.latitude" class="mt-1 text-xs text-red-600">
                                            {{ editForm.errors.latitude }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <input ref="editImageInput" type="file" accept="image/*" class="hidden" @change="setEditImage" />

                                        <button
                                            type="button"
                                            class="w-full rounded border border-dashed border-gray-300 bg-gray-50 px-2 py-2 text-left text-xs transition hover:border-blue-300 hover:bg-blue-50"
                                            @click="openEditImagePicker"
                                        >
                                            <span class="block font-medium text-gray-700">
                                                {{ editForm.image ? "New image selected" : "Replace image" }}
                                            </span>
                                            <span class="block truncate text-gray-500">
                                                {{ editForm.image ? editForm.image.name : "Click to choose file" }}
                                            </span>
                                        </button>

                                        <div v-if="editForm.errors.image" class="mt-1 text-xs text-red-600">
                                            {{ editForm.errors.image }}
                                        </div>
                                        <div v-if="editPreviewUrl || site.image_url" class="mt-2">
                                            <img :src="editPreviewUrl || site.image_url" alt="Site image" class="h-16 w-16 rounded border object-cover" />
                                            <button
                                                v-if="editPreviewUrl"
                                                type="button"
                                                class="mt-1 rounded bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100"
                                                @click="clearEditImage"
                                            >
                                                Undo new image
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <button class="rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white" @click="submitEdit">
                                                Save
                                            </button>
                                            <button class="rounded bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700" @click="cancelEdit">
                                                Cancel
                                            </button>
                                        </div>
                                    </td>
                                </template>

                                <template v-else>
                                    <td class="p-3 text-sm font-medium text-gray-900">{{ site.site_name }}</td>
                                    <td class="p-3 text-sm text-gray-700">{{ site.address }}</td>
                                    <td class="p-3 text-sm text-gray-700">{{ site.longitude }}</td>
                                    <td class="p-3 text-sm text-gray-700">{{ site.latitude }}</td>
                                    <td class="p-3">
                                        <img v-if="site.image_url" :src="site.image_url" alt="Site image" class="h-16 w-16 rounded border object-cover" />
                                        <span v-else class="text-xs text-gray-400">No image</span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <button class="rounded bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100" @click="startEdit(site)">
                                                Edit
                                            </button>
                                            <button class="rounded bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100" @click="confirmDelete(site)">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="!props.sites.length">
                                <td colspan="6" class="p-6 text-center text-sm text-gray-500">
                                    No sites found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <DeleteConfirmation
        v-if="showDelete"
        title="Delete Site"
        message="Are you sure you want to delete this site?"
        @confirm="deleteSite"
        @close="showDelete = false"
    />
</template>
