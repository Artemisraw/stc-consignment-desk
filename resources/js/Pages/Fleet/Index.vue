<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';
defineProps({ vehicles: Array, drivers: Array, canManage: Boolean });
const vehicle = useForm({ registration_number: '', make: '', model: '', capacity_kg: '', default_driver_id: '' });
const driver = useForm({ name: '', phone: '', licence_number: '' });
</script>

<template><Head title="Fleet" /><AppLayout>
    <div class="page-head"><div><p class="eyebrow">Fleet control</p><h1>Vehicles and drivers</h1><p class="muted">On-road status is derived from active trips.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Plate</th><th>Vehicle</th><th>Default driver</th><th>Capacity</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="v in vehicles" :key="v.id"><td><strong>{{ v.registration_number }}</strong></td><td>{{ [v.make,v.model].filter(Boolean).join(' ') }}</td><td>{{ v.driver || 'Unassigned' }}</td><td>{{ v.capacity_kg ? `${Number(v.capacity_kg).toLocaleString()} kg` : '—' }}</td><td><StatusChip :status="v.status" /></td><td><button v-if="canManage && v.status !== 'in_transit'" class="button" @click="$inertia.patch(`/fleet/vehicles/${v.id}/toggle`)">{{ v.status === 'available' ? 'Send to maintenance' : 'Mark available' }}</button></td></tr></tbody></table></div>
    <div v-if="canManage" class="grid two" style="margin-top:1.5rem">
        <form class="card" @submit.prevent="vehicle.post('/fleet/vehicles', {onSuccess:()=>vehicle.reset()})"><h2>Add vehicle</h2><div class="field"><label>Registration number</label><input v-model="vehicle.registration_number" required></div><div class="grid two"><div class="field"><label>Make</label><input v-model="vehicle.make"></div><div class="field"><label>Model</label><input v-model="vehicle.model"></div></div><div class="grid two"><div class="field"><label>Capacity (kg)</label><input v-model.number="vehicle.capacity_kg" type="number"></div><div class="field"><label>Default driver</label><select v-model="vehicle.default_driver_id"><option value="">Unassigned</option><option v-for="d in drivers" :value="d.id">{{ d.name }}</option></select></div></div><button class="button primary">Add vehicle</button></form>
        <form class="card" @submit.prevent="driver.post('/fleet/drivers', {onSuccess:()=>driver.reset()})"><h2>Add driver</h2><div class="field"><label>Name</label><input v-model="driver.name" required></div><div class="field"><label>Phone</label><input v-model="driver.phone"></div><div class="field"><label>Licence number</label><input v-model="driver.licence_number" required></div><button class="button primary">Add driver</button></form>
    </div>
</AppLayout></template>
