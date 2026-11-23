<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ vehicle: Object, drivers: Array });
const form = useForm({
    registration_number: props.vehicle.registration_number,
    make: props.vehicle.make || '',
    model: props.vehicle.model || '',
    capacity_kg: props.vehicle.capacity_kg || '',
    default_driver_id: props.vehicle.default_driver_id || '',
});
const submit = () => form.put(`/fleet/vehicles/${props.vehicle.id}`);
</script>

<template>
    <Head :title="`Edit ${vehicle.registration_number}`" />
    <AppLayout>
        <div class="page-head"><div><p class="eyebrow">Fleet control</p><h1>Edit vehicle</h1><p class="muted">Update registration, vehicle details and its default driver.</p></div></div>
        <form class="card" style="max-width:680px" @submit.prevent="submit">
            <div class="field"><label for="edit-registration">Registration number</label><input id="edit-registration" v-model="form.registration_number" required><div v-if="form.errors.registration_number" class="error-text">{{ form.errors.registration_number }}</div></div>
            <div class="grid two"><div class="field"><label for="edit-make">Make</label><input id="edit-make" v-model="form.make"><div v-if="form.errors.make" class="error-text">{{ form.errors.make }}</div></div><div class="field"><label for="edit-model">Model</label><input id="edit-model" v-model="form.model"><div v-if="form.errors.model" class="error-text">{{ form.errors.model }}</div></div></div>
            <div class="grid two"><div class="field"><label for="edit-capacity">Capacity (kg)</label><input id="edit-capacity" v-model.number="form.capacity_kg" type="number" min="1"><div v-if="form.errors.capacity_kg" class="error-text">{{ form.errors.capacity_kg }}</div></div><div class="field"><label for="edit-default-driver">Default driver</label><select id="edit-default-driver" v-model="form.default_driver_id"><option value="">Unassigned</option><option v-for="driver in drivers" :key="driver.id" :value="driver.id">{{ driver.name }}{{ driver.is_active ? '' : ' (inactive)' }}</option></select><div v-if="form.errors.default_driver_id" class="error-text">{{ form.errors.default_driver_id }}</div></div></div>
            <div class="actions"><button class="button primary" :disabled="form.processing">Save changes</button><Link class="button" href="/fleet">Cancel</Link></div>
        </form>
    </AppLayout>
</template>
