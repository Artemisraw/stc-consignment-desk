<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ driver: Object });
const form = useForm({
    name: props.driver.name,
    phone: props.driver.phone || '',
    licence_number: props.driver.licence_number,
});
const submit = () => form.put(`/fleet/drivers/${props.driver.id}`);
</script>

<template>
    <Head :title="`Edit ${driver.name}`" />
    <AppLayout>
        <div class="page-head"><div><p class="eyebrow">Fleet control</p><h1>Edit driver</h1><p class="muted">Update contact and licence details.</p></div></div>
        <form class="card" style="max-width:680px" @submit.prevent="submit">
            <div class="field"><label for="edit-driver-name">Name</label><input id="edit-driver-name" v-model="form.name" required><div v-if="form.errors.name" class="error-text">{{ form.errors.name }}</div></div>
            <div class="field"><label for="edit-driver-phone">Phone</label><input id="edit-driver-phone" v-model="form.phone"><div v-if="form.errors.phone" class="error-text">{{ form.errors.phone }}</div></div>
            <div class="field"><label for="edit-driver-licence">Licence number</label><input id="edit-driver-licence" v-model="form.licence_number" required><div v-if="form.errors.licence_number" class="error-text">{{ form.errors.licence_number }}</div></div>
            <div class="actions"><button class="button primary" :disabled="form.processing">Save changes</button><Link class="button" href="/fleet">Cancel</Link></div>
        </form>
    </AppLayout>
</template>
