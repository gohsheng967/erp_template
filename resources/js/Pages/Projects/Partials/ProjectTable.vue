<script setup>
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    projects: Array
});

function formatStatus(value) {
    return String(value ?? "")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());
}
</script>

<template>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Code</th>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Project Name</th>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Client</th>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Start Date</th>
                <th class="px-4 py-2 text-left font-medium text-gray-600">End Date</th>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
                <th class="px-4 py-2 text-right font-medium text-gray-600">Actions</th>
            </tr>
        </thead>

        <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="project in projects" :key="project.id" class="hover:bg-gray-50">

                <td class="px-4 py-2">{{ project.code }}</td>

                <td class="px-4 py-2 font-medium text-gray-800">
                    {{ project.name }}
                </td>

                <td class="px-4 py-2">
                    {{ project.client_name ?? '-' }}
                </td>

                <td class="px-4 py-2">
                    {{ project.start_date ?? '-' }}
                </td>

                <td class="px-4 py-2">
                    {{ project.end_date ?? '-' }}
                </td>

                <td class="px-4 py-2">
                    <span
                        class="px-2 py-1 rounded text-xs"
                        :class="{
                            'bg-slate-100 text-slate-700': project.status === 'incoming',
                            'bg-blue-100 text-blue-700': project.status === 'on_going',
                            'bg-amber-100 text-amber-800': project.status === 'late',
                            'bg-violet-100 text-violet-700': project.status === 'extended',
                            'bg-green-100 text-green-700': project.status === 'finished',
                        }"
                    >
                        {{ formatStatus(project.status) }}
                    </span>
                </td>

                <td class="px-4 py-2 text-right space-x-2">
                    <Link
                        :href="route('projects.show', project.id)"
                        class="text-blue-600 hover:underline"
                    >
                        View
                    </Link>

                    <Link
                        :href="route('projects.edit', project.id)"
                        class="text-indigo-600 hover:underline"
                    >
                        Edit
                    </Link>
                </td>

            </tr>
        </tbody>
    </table>
</template>
