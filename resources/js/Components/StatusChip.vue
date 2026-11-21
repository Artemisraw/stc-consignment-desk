<script setup>
defineProps({ status: { type: String, required: true } });
const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
</script>

<template><span class="chip" :class="`chip-${status}`">{{ label(status) }}</span></template>
