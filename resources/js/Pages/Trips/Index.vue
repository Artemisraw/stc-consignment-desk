<script setup>
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';
const props = defineProps({ trips: Array, vehicles: Array, drivers: Array, waiting: Array, branches: Array, canDispatch: Boolean });
const form = useForm({ vehicle_id: props.vehicles[0]?.id || '', driver_id: props.vehicles[0]?.default_driver_id || props.drivers[0]?.id || '', consignment_ids: [] });
const money = (v) => `KES ${Number(v).toLocaleString('en-KE')}`;
const cancelTrip = (trip) => {
    if (confirm(`Cancel ${trip.trip_number}? Its consignments go back to waiting at ${trip.current_branch.name}.`)) {
        router.post(`/trips/${trip.id}/cancel`, {}, { preserveScroll: true });
    }
};
// Name the stop being confirmed and block repeat clicks until the server answers.
const advancing = ref(null);
const confirmArrival = (trip) => {
    if (advancing.value) return;
    advancing.value = trip.id;
    router.post(`/trips/${trip.id}/advance`, { stop_id: trip.next_stop.id }, { preserveScroll: true, onFinish: () => { advancing.value = null; } });
};
// Keep truck positions current while the page is open (paused when the tab is hidden).
usePoll(30000, { only: ['trips'] });
const stopDone = (trip, branch) => trip.direction === 'inbound'
    ? branch.route_order >= trip.current_branch.route_order
    : branch.route_order <= trip.current_branch.route_order;
</script>

<template><Head title="Trips" /><AppLayout>
    <div class="page-head"><div><p class="eyebrow">Linehaul</p><h1>Trips and manifests</h1><p class="muted">A consignment can belong to one active trip only.</p></div></div>
    <div v-if="$page.props.errors.trip" class="flash error" style="margin:0 0 1rem;width:auto">{{ $page.props.errors.trip }}</div>
    <div :class="canDispatch ? 'grid sidebar' : ''">
        <form v-if="canDispatch" class="card" @submit.prevent="form.post('/trips')"><h2>Load a truck</h2><div class="field"><label>Vehicle</label><select v-model="form.vehicle_id"><option v-for="v in vehicles" :value="v.id">{{ v.registration_number }}</option></select></div><div class="field"><label>Driver</label><select v-model="form.driver_id"><option v-for="d in drivers" :value="d.id">{{ d.name }}</option></select></div><p class="field-label">Waiting to load</p><p class="small muted">Load one branch and one direction per trip.</p><label class="check-row" v-for="c in waiting"><input v-model="form.consignment_ids" type="checkbox" :value="c.id"><span><strong>{{ c.tracking_number }}</strong><br><span class="small muted">{{ c.receiver.name }} · {{ c.origin_branch.name }} → {{ c.destination_branch.name }} · {{ money(c.total_amount) }}</span></span></label><div v-if="!waiting.length" class="empty">No consignments are waiting.</div><div class="error-text">{{ form.errors.consignment_ids || form.errors.driver_id || form.errors.vehicle_id }}</div><button class="button primary" style="margin-top:1rem" :disabled="!vehicles.length || !form.consignment_ids.length">Dispatch trip</button></form>
        <section><article class="card" v-for="trip in trips" :key="trip.id" style="margin-bottom:1rem"><div class="card-title"><div><h2>{{ trip.trip_number }}</h2><p>{{ trip.vehicle.registration_number }} · {{ trip.driver.name }}</p></div><StatusChip :status="trip.status" /></div><div class="route-wrap"><div class="route"><div v-for="b in branches" class="stop" :class="{done:stopDone(trip,b)}"><i></i><span>{{ b.name }}</span></div></div></div><div class="table-wrap"><table><thead><tr><th>Tracking</th><th>Receiver</th><th>To</th><th>Status</th></tr></thead><tbody><tr v-if="!trip.consignments.length"><td colspan="4" class="muted">No cargo for your branch on this trip.</td></tr><tr v-for="c in trip.consignments"><td>{{ c.tracking_number }}</td><td>{{ c.receiver.name }}</td><td>{{ c.destination_branch.name }}</td><td><StatusChip :status="c.status" /></td></tr></tbody></table></div><div class="actions noprint" style="margin-top:1rem"><button v-if="trip.can.advance && trip.next_stop" class="button primary" :disabled="advancing !== null" @click="confirmArrival(trip)">{{ advancing === trip.id ? 'Recording…' : `Confirm arrival at ${trip.next_stop.name}` }}</button><button v-if="trip.can.cancel && trip.status === 'in_transit' && trip.current_branch_id === trip.origin_branch_id" class="button danger" @click="cancelTrip(trip)">Cancel trip</button><Link :href="`/trips/${trip.id}/manifest`" class="button">Print manifest</Link></div></article><div v-if="!trips.length" class="empty">No trips have been dispatched.</div></section>
    </div>
</AppLayout></template>
