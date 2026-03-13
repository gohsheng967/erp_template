<script setup>
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

const props = defineProps({
    search: String,
    status: String,
    client: String,
    date_from: String,
    date_to: String,
    clients: Array,
});

const emit = defineEmits([
    "update:search",
    "update:status",
    "update:client",
    "update:date_from",
    "update:date_to",
    "reset"
]);
</script>

<template>
    <div class="bg-white p-4 rounded-md shadow-sm border mb-4">

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

            <!-- Search -->
            <div>
                <InputLabel value="Search" class="mb-1" />
                <TextInput
                    class="w-full"
                    placeholder="Search by name or code"
                    :model-value="search"
                    @input="$emit('update:search', $event.target.value)"
                />
            </div>

            <!-- Client -->
            <div>
                <InputLabel value="Client" class="mb-1" />
                <select
                    class="w-full border-gray-300 rounded-md"
                    :value="client"
                    @change="$emit('update:client', $event.target.value)"
                >
                    <option value="">All Clients</option>
                    <option
                        v-for="c in clients"
                        :key="c.id"
                        :value="c.id"
                    >
                        {{ c.name }}
                    </option>
                </select>
            </div>

            <!-- Status -->
            <div>
                <InputLabel value="Status" class="mb-1" />
                <select
                    class="w-full border-gray-300 rounded-md"
                    :value="status"
                    @change="$emit('update:status', $event.target.value)"
                >
                    <option value="">All Status</option>
                    <option value="incoming">Incoming</option>
                    <option value="on_going">On Going</option>
                    <option value="late">Late</option>
                    <option value="extended">Extended</option>
                    <option value="finished">Finished</option>
                </select>
            </div>

            <!-- Date From -->
            <div>
                <InputLabel value="Start Date From" class="mb-1" />
                <input
                    type="date"
                    class="w-full border-gray-300 rounded-md"
                    :value="date_from"
                    @input="$emit('update:date_from', $event.target.value)"
                />
            </div>

            <!-- Date To -->
            <div>
                <InputLabel value="Start Date To" class="mb-1" />
                <input
                    type="date"
                    class="w-full border-gray-300 rounded-md"
                    :value="date_to"
                    @input="$emit('update:date_to', $event.target.value)"
                />
            </div>
        </div>
    </div>
</template>
