<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';

defineProps({ vehicles: Array, drivers: Array, canManage: Boolean });

const page = usePage();
const vehicle = useForm({ registration_number: '', make: '', model: '', capacity_kg: '', default_driver_id: '' });
const driver = useForm({ name: '', phone: '', licence_number: '' });

const addVehicle = () => vehicle.post('/fleet/vehicles', { onSuccess: () => vehicle.reset() });
const addDriver = () => driver.post('/fleet/drivers', { onSuccess: () => driver.reset() });
const toggleVehicle = (vehicleId) => router.patch(`/fleet/vehicles/${vehicleId}/toggle`, {}, { preserveScroll: true });
const toggleDriver = (driverId) => router.patch(`/fleet/drivers/${driverId}/toggle`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Fleet" />
    <AppLayout>
        <div class="page-head"><div><p class="eyebrow">Fleet control</p><h1>Vehicles and drivers</h1><p class="muted">On-road status is derived from active trips.</p></div></div>

        <section>
            <div class="card-title"><h2>Vehicles</h2></div>
            <div class="table-wrap"><table><thead><tr><th>Plate</th><th>Vehicle</th><th>Default driver</th><th>Capacity</th><th>Status</th><th></th></tr></thead><tbody>
                <tr v-for="item in vehicles" :key="item.id">
                    <td><strong>{{ item.registration_number }}</strong></td>
                    <td>{{ [item.make, item.model].filter(Boolean).join(' ') || '—' }}</td>
                    <td>{{ item.driver || 'Unassigned' }}</td>
                    <td>{{ item.capacity_kg ? `${Number(item.capacity_kg).toLocaleString()} kg` : '—' }}</td>
                    <td><StatusChip :status="item.status" /></td>
                    <td><div v-if="canManage" class="actions"><Link class="button" :href="`/fleet/vehicles/${item.id}/edit`">Edit</Link><button v-if="item.status !== 'in_transit'" class="button" type="button" @click="toggleVehicle(item.id)">{{ item.status === 'available' ? 'Send to maintenance' : 'Mark available' }}</button></div></td>
                </tr>
            </tbody></table></div>
        </section>

        <section style="margin-top:1.75rem">
            <div class="card-title"><h2>Drivers</h2></div>
            <div v-if="page.props.errors?.driver" class="flash error" role="alert">{{ page.props.errors.driver }}</div>
            <div class="table-wrap"><table><thead><tr><th>Name</th><th>Phone</th><th>Licence</th><th>Status</th><th></th></tr></thead><tbody>
                <tr v-for="item in drivers" :key="item.id">
                    <td><strong>{{ item.name }}</strong></td><td>{{ item.phone || '—' }}</td><td>{{ item.licence_number }}</td>
                    <td><span class="chip" :class="item.is_active ? 'chip-available' : 'chip-maintenance'">{{ item.is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><div v-if="canManage" class="actions"><Link class="button" :href="`/fleet/drivers/${item.id}/edit`">Edit</Link><button class="button" :class="{ danger: item.is_active }" type="button" @click="toggleDriver(item.id)">{{ item.is_active ? 'Deactivate' : 'Activate' }}</button></div></td>
                </tr>
            </tbody></table></div>
        </section>

        <div v-if="canManage" class="grid two" style="margin-top:1.75rem">
            <form class="card" @submit.prevent="addVehicle">
                <h2>Add vehicle</h2>
                <div class="field"><label for="vehicle-registration">Registration number</label><input id="vehicle-registration" v-model="vehicle.registration_number" required><div v-if="vehicle.errors.registration_number" class="error-text">{{ vehicle.errors.registration_number }}</div></div>
                <div class="grid two"><div class="field"><label for="vehicle-make">Make</label><input id="vehicle-make" v-model="vehicle.make"><div v-if="vehicle.errors.make" class="error-text">{{ vehicle.errors.make }}</div></div><div class="field"><label for="vehicle-model">Model</label><input id="vehicle-model" v-model="vehicle.model"><div v-if="vehicle.errors.model" class="error-text">{{ vehicle.errors.model }}</div></div></div>
                <div class="grid two"><div class="field"><label for="vehicle-capacity">Capacity (kg)</label><input id="vehicle-capacity" v-model.number="vehicle.capacity_kg" type="number" min="1"><div v-if="vehicle.errors.capacity_kg" class="error-text">{{ vehicle.errors.capacity_kg }}</div></div><div class="field"><label for="vehicle-driver">Default driver</label><select id="vehicle-driver" v-model="vehicle.default_driver_id"><option value="">Unassigned</option><option v-for="item in drivers" :key="item.id" :value="item.id">{{ item.name }}{{ item.is_active ? '' : ' (inactive)' }}</option></select><div v-if="vehicle.errors.default_driver_id" class="error-text">{{ vehicle.errors.default_driver_id }}</div></div></div>
                <button class="button primary" :disabled="vehicle.processing">Add vehicle</button>
            </form>

            <form class="card" @submit.prevent="addDriver">
                <h2>Add driver</h2>
                <div class="field"><label for="driver-name">Name</label><input id="driver-name" v-model="driver.name" required><div v-if="driver.errors.name" class="error-text">{{ driver.errors.name }}</div></div>
                <div class="field"><label for="driver-phone">Phone</label><input id="driver-phone" v-model="driver.phone"><div v-if="driver.errors.phone" class="error-text">{{ driver.errors.phone }}</div></div>
                <div class="field"><label for="driver-licence">Licence number</label><input id="driver-licence" v-model="driver.licence_number" required><div v-if="driver.errors.licence_number" class="error-text">{{ driver.errors.licence_number }}</div></div>
                <button class="button primary" :disabled="driver.processing">Add driver</button>
            </form>
        </div>
    </AppLayout>
</template>
