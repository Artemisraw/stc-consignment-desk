<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';
const props = defineProps({ consignments: Object, filters: Object });
const search = ref(props.filters.search || '');
const runSearch = () => router.get('/consignments', { search: search.value }, { preserveState: true, replace: true });
const money = (value) => Number(value).toLocaleString('en-KE');
</script>

<template><Head title="Consignments" /><AppLayout>
    <div class="page-head"><div><p class="eyebrow">Cargo ledger</p><h1>Consignments</h1><p class="muted">Search, inspect, collect and hand over cargo.</p></div><Link href="/consignments/create" class="button primary">New consignment</Link></div>
    <form class="actions card noprint" @submit.prevent="runSearch" style="margin-bottom:1rem"><input v-model="search" placeholder="Tracking number or receiver" style="max-width:360px"><button class="button">Search</button></form>
    <div class="table-wrap"><table><thead><tr><th>Tracking no.</th><th>Receiver</th><th>Route</th><th>Status</th><th class="number">Total</th><th class="number">Balance</th><th></th></tr></thead><tbody><tr v-for="c in consignments.data" :key="c.id"><td><strong>{{ c.tracking_number }}</strong></td><td>{{ c.receiver }}</td><td>{{ c.origin }} → {{ c.destination }}</td><td><StatusChip :status="c.status" /></td><td class="number">{{ money(c.total_amount) }}</td><td class="number">{{ money(c.balance) }}</td><td><Link :href="`/consignments/${c.id}`" class="button">Open</Link></td></tr></tbody></table></div>
    <div class="pagination"><template v-for="link in consignments.links" :key="link.label"><Link v-if="link.url" :href="link.url" v-html="link.label" :class="{active:link.active}"/><span v-else v-html="link.label"/></template></div>
</AppLayout></template>
