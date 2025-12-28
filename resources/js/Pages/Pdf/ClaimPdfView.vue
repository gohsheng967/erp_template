<script setup>
import ClaimShowA4 from '@/Pages/Transactions/Claims/Partials/ClaimShowA4.vue'

defineProps({
    claim: Object,
    company: Object,
})
</script>

<template>
    <ClaimShowA4 :claim="claim" :company="company" />
</template>
