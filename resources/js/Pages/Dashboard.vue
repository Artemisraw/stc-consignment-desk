<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import StatusChip from '../Components/StatusChip.vue';
defineProps({ stats: Object, vehicles: Array, consignments: Array, activeTrips: Array });
const money = (value) => `KES ${Number(value).toLocaleString('en-KE')}`;
const tripStopDone = (trip, order) => trip.direction === 'inbound'
    ? order >= trip.current_branch.route_order
    : order <= trip.current_branch.route_order;
</script>

<template>
    <Head title="Dashboard" /><AppLayout>
        <div class="page-head dashboard-head"><div><p class="eyebrow">Live operations</p><h1>Dispatch overview</h1><p class="muted">Fleet readiness, cargo movement and collections across the corridor.</p></div><Link href="/consignments/create" class="button primary">New consignment</Link></div>
        <section class="kpis operations-strip" aria-label="Operations summary">
            <div class="kpi"><strong>{{ stats.vehicles }}</strong><span>Total trucks</span></div><div class="kpi"><strong>{{ stats.in_transit }}</strong><span>On the road</span></div>
            <div class="kpi"><strong>{{ stats.available }}</strong><span>Ready to load</span></div><div class="kpi"><strong>{{ stats.maintenance }}</strong><span>Maintenance</span></div>
            <div class="kpi"><strong>{{ stats.waiting }}</strong><span>Waiting to load</span></div><div class="kpi"><strong>{{ Number(stats.outstanding).toLocaleString('en-KE') }}</strong><span>KES to collect</span></div>
        </section>
        <div class="dashboard-workspace">
            <section class="active-trips-panel">
                <div class="section-heading"><div><p class="section-kicker">On the corridor</p><h2>Active trips</h2></div><Link href="/trips" class="text-link">Open trip board →</Link></div>
                <div v-if="activeTrips.length" class="trip-strips">
                    <article v-for="trip in activeTrips" :key="trip.id" class="trip-strip">
                        <div><strong>{{ trip.trip_number }}</strong><span>{{ trip.vehicle.registration_number }}</span></div>
                        <div class="trip-line" aria-hidden="true"><i v-for="order in 7" :key="order" :class="{ current: order === trip.current_branch.route_order, done: tripStopDone(trip, order) }"></i></div>
                        <p>Currently at <strong>{{ trip.current_branch.name }}</strong></p>
                    </article>
                </div>
                <div v-else class="empty">No trucks are moving right now. Dispatch a loaded vehicle from the trips desk.</div>
            </section>
            <aside class="attention-panel">
                <p class="eyebrow">Counter actions</p><h2>What needs attention</h2>
                <p><strong>{{ stats.waiting }}</strong> consignments are waiting to load. Outstanding collections total <strong>{{ money(stats.outstanding) }}</strong>.</p>
                <Link href="/consignments/create" class="button inverse">Book new consignment</Link>
            </aside>
        </div>
        <section class="dashboard-table ledger-section"><div class="section-heading"><h2>Recent consignments</h2><Link href="/consignments" class="text-link">View complete ledger →</Link></div><div class="table-wrap"><table><thead><tr><th>Tracking</th><th>Receiver</th><th>To</th><th>Status</th><th class="number">Balance</th></tr></thead><tbody><tr v-for="c in consignments" :key="c.id"><td data-label="Tracking"><Link :href="`/consignments/${c.id}`"><strong>{{ c.tracking_number }}</strong></Link></td><td data-label="Receiver">{{ c.receiver }}</td><td data-label="To">{{ c.destination }}</td><td data-label="Status"><StatusChip :status="c.status" /></td><td data-label="Balance" class="number">{{ money(c.balance) }}</td></tr></tbody></table></div></section>
        <section class="dashboard-table fleet-section"><div class="section-heading"><h2>Fleet today</h2><Link href="/fleet" class="text-link">Manage fleet →</Link></div><div class="table-wrap"><table><thead><tr><th>Plate</th><th>Driver</th><th>Status</th></tr></thead><tbody><tr v-for="v in vehicles" :key="v.id"><td data-label="Plate"><strong>{{ v.registration_number }}</strong></td><td data-label="Driver">{{ v.driver || 'Unassigned' }}</td><td data-label="Status"><StatusChip :status="v.status" /></td></tr></tbody></table></div></section>
    </AppLayout>
</template>
