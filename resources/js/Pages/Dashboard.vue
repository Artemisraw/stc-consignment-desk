<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import StatusChip from '../Components/StatusChip.vue';
defineProps({ stats: Object, vehicles: Array, consignments: Array, activeTrips: Array });
const money = (value) => `KES ${Number(value).toLocaleString('en-KE')}`;
</script>

<template>
    <Head title="Dashboard" /><AppLayout>
        <div class="page-head"><div><p class="eyebrow">Live operations</p><h1>Dispatch overview</h1><p class="muted">Fleet readiness, cargo movement and collections at a glance.</p></div><Link href="/consignments/create" class="button primary">New consignment</Link></div>
        <section class="kpis">
            <div class="kpi"><strong>{{ stats.vehicles }}</strong><span>Total trucks</span></div><div class="kpi"><strong>{{ stats.in_transit }}</strong><span>On the road</span></div>
            <div class="kpi"><strong>{{ stats.available }}</strong><span>Ready to load</span></div><div class="kpi"><strong>{{ stats.maintenance }}</strong><span>Maintenance</span></div>
            <div class="kpi"><strong>{{ stats.waiting }}</strong><span>Waiting to load</span></div><div class="kpi"><strong>{{ Number(stats.outstanding).toLocaleString('en-KE') }}</strong><span>KES to collect</span></div>
        </section>
        <div class="grid two">
            <section><div class="card-title"><h2>Fleet today</h2><Link href="/fleet">Manage fleet</Link></div><div class="table-wrap"><table><thead><tr><th>Plate</th><th>Driver</th><th>Status</th></tr></thead><tbody><tr v-for="v in vehicles" :key="v.id"><td><strong>{{ v.registration_number }}</strong></td><td>{{ v.driver || 'Unassigned' }}</td><td><StatusChip :status="v.status" /></td></tr></tbody></table></div></section>
            <section><div class="card-title"><h2>Recent consignments</h2><Link href="/consignments">View all</Link></div><div class="table-wrap"><table><thead><tr><th>Tracking</th><th>Receiver</th><th>To</th><th>Status</th><th class="number">Balance</th></tr></thead><tbody><tr v-for="c in consignments" :key="c.id"><td><Link :href="`/consignments/${c.id}`"><strong>{{ c.tracking_number }}</strong></Link></td><td>{{ c.receiver }}</td><td>{{ c.destination }}</td><td><StatusChip :status="c.status" /></td><td class="number">{{ money(c.balance) }}</td></tr></tbody></table></div></section>
        </div>
    </AppLayout>
</template>
