<script setup>
import { usePage, Link } from '@inertiajs/vue3'

const page = usePage()
const user = page.props.auth.user.data

// Generate initials for avatar fallback
function initials(name) {
    if (!name) return "U"
    const parts = name.split(" ")
    if (parts.length === 1) return parts[0][0].toUpperCase()
    return (parts[0][0] + parts[1][0]).toUpperCase()
}

// Fake counters
const urgent = 5
const moderate = 12
const low = 32
</script>

<template>
    <header
        class="
            h-14
            backdrop-blur-xl bg-white/70 
            border-b border-white/40
            px-4 flex items-center justify-between 
            sticky top-0 z-30 shadow-sm
        "
        style="
            background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 50%, #e5e7eb 100%);
        "
    >
        <!-- Sidebar Toggle (mobile) -->
        <button class="text-gray-600 hover:text-black sm:hidden">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Priority Indicators -->
        <div class="hidden sm:flex items-center gap-6">

            <!-- Urgent -->
            <div class="flex items-center gap-2">
                <span class="h-3 w-3 rounded-full bg-red-500"></span>
                <span class="text-sm text-gray-700">Urgent</span>
                <span class="text-sm font-semibold text-gray-900">{{ urgent }}</span>
            </div>

            <!-- Moderate -->
            <div class="flex items-center gap-2">
                <span class="h-3 w-3 rounded-full bg-yellow-500"></span>
                <span class="text-sm text-gray-700">Moderate</span>
                <span class="text-sm font-semibold text-gray-900">{{ moderate }}</span>
            </div>

            <!-- Low -->
            <div class="flex items-center gap-2">
                <span class="h-3 w-3 rounded-full bg-green-500"></span>
                <span class="text-sm text-gray-700">Low</span>
                <span class="text-sm font-semibold text-gray-900">{{ low }}</span>
            </div>

        </div>

        <!-- Right Section -->
        <div class="flex items-center gap-4 text-gray-600">

            <!-- Notification Bell -->
            <button class="hover:text-black">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M14 21a2 2 0 11-4 0m8-7v-2a6 6 0 10-12 0v2l-1.293 1.293A1 1 0 006 17h12a1 1 0 00.707-1.707L18 14z" />
                </svg>
            </button>

            <!-- Avatar → Profile -->
            <Link :href="route('profile.edit')" class="block">
                <div class="w-9 h-9 rounded-full overflow-hidden bg-indigo-100 flex items-center justify-center shadow cursor-pointer">

                    <!-- Use uploaded image if exists -->
                    <img
                        v-if="user.profile_photo_url"
                        :src="user.profile_photo_url"
                        class="w-full h-full object-cover"
                    />

                    <!-- Otherwise initials -->
                    <span
                        v-else
                        class="text-indigo-700 font-semibold text-sm"
                    >
                        {{ initials(user.name) }}
                    </span>

                </div>
            </Link>
        </div>

    </header>
</template>
