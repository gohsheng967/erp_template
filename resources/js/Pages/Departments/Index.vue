<script setup>
import { ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();
const departments = page.props.departments;
const expanded = ref(null);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">
                Department & Role Management
            </h2>
        </template>

        <div class="p-6 space-y-6">
            <div class="bg-white rounded-lg shadow divide-y">
                <div
                    v-for="dept in departments"
                    :key="dept.id"
                    class="border-b last:border-0"
                >
                    <div
                        class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50"
                        @click="expanded = expanded === dept.id ? null : dept.id"
                    >
                        <div class="flex items-center gap-3">
                            <svg
                                :class="['h-5 transition', expanded === dept.id ? 'rotate-90' : '']"
                                fill="none" stroke="currentColor" stroke-width="1.5"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>

                            <span class="font-semibold text-gray-800 text-lg">{{ dept.name }}</span>
                            <span class="text-sm text-gray-500">({{ dept.users_count }} users)</span>
                        </div>
                    </div>

                    <div
                        v-if="expanded === dept.id"
                        class="px-10 pb-5 space-y-3 animate-fade"
                    >
                        <div
                            v-for="role in dept.roles"
                            :key="role.id"
                            class="flex justify-between items-center bg-gray-50 border px-4 py-2 rounded"
                        >
                            <span>{{ role.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.animate-fade {
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
