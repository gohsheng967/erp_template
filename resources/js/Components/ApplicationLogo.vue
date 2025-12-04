<script setup>
import { usePage } from '@inertiajs/vue3'

// Access global page props
const page = usePage()
const company = page.props.company
</script>

<template>
    <div class="w-16 h-16">
        <img
            :src="company?.logo
                ? (company.logo.startsWith('http') ? company.logo : '/storage/' + company.logo)
                : '/asset/img/sample-logo.png'"
            class="w-full h-full object-contain"
        />
    </div>
</template>
