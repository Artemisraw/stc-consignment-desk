<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ConsignmentForm from '../../Components/ConsignmentForm.vue';
const props = defineProps({ consignment: Object, branches: Array, lockOrigin: Boolean });
const c = props.consignment;
const party = (p) => ({ name: p?.name ?? '', phone: p?.phone ?? '', email: p?.email ?? '' });
const form = useForm({
    origin_branch_id: c.origin_branch_id, destination_branch_id: c.destination_branch_id,
    sender: party(c.sender), receiver: party(c.receiver),
    items: c.items.map(({ quantity, description, amount }) => ({ quantity, description, amount })),
    notes: c.notes ?? '',
});
</script>

<template><Head :title="`Edit ${consignment.tracking_number}`" /><AppLayout>
    <div class="page-head"><div><p class="eyebrow">Correction</p><h1>Edit {{ consignment.tracking_number }}</h1><p class="muted">Already paid: KES {{ Number(consignment.paid_amount).toLocaleString('en-KE') }}. The new total cannot be lower than that.</p></div><Link :href="`/consignments/${consignment.id}`" class="button">Back</Link></div>
    <ConsignmentForm :form="form" :branches="branches" :lock-origin="lockOrigin" :show-paid="false" submit-label="Save corrections" @submit="form.put(`/consignments/${consignment.id}`)" />
</AppLayout></template>
