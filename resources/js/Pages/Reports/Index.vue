<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
defineProps({ summary: Object, byDestination: Array });
const money = (value) => `KES ${Number(value).toLocaleString('en-KE')}`;
</script>

<template><Head title="Reports" /><AppLayout>
    <div class="page-head"><div><p class="eyebrow">Management</p><h1>Performance report</h1><p class="muted">Current all-time operational totals from recorded transactions.</p></div><button class="button noprint" @click="window.print()">Print report</button></div>
    <section class="kpis" style="grid-template-columns:repeat(4,minmax(150px,1fr))"><div class="kpi"><strong>{{ money(summary.revenue) }}</strong><span>Collected revenue</span></div><div class="kpi"><strong>{{ money(summary.outstanding) }}</strong><span>Outstanding</span></div><div class="kpi"><strong>{{ summary.consignments }}</strong><span>Consignments</span></div><div class="kpi"><strong>{{ summary.delivered }}</strong><span>Delivered</span></div></section>
    <section><h2 style="margin-bottom:.8rem">Value by destination</h2><div class="table-wrap"><table><thead><tr><th>Destination</th><th class="number">Consignments</th><th class="number">Booked value</th></tr></thead><tbody><tr v-for="row in byDestination"><td><strong>{{ row.name }}</strong></td><td class="number">{{ row.consignments }}</td><td class="number">{{ money(row.value) }}</td></tr></tbody></table></div></section>
</AppLayout></template>
