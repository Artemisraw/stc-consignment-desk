<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ConsignmentForm from '../../Components/ConsignmentForm.vue';
const props = defineProps({ branches: Array, defaultOriginId: Number, lockOrigin: Boolean });
// Start at the user's own branch, heading to the far end of the corridor.
const origin = props.branches.find((b) => b.id === props.defaultOriginId) ?? props.branches[0];
const destination = origin?.id === props.branches.at(-1)?.id ? props.branches[0] : props.branches.at(-1);
const form = useForm({
    origin_branch_id: origin?.id, destination_branch_id: destination?.id,
    sender: { name: '', phone: '', email: '' }, receiver: { name: '', phone: '', email: '' },
    items: [{ quantity: 1, description: '', amount: '' }], paid_amount: 0, notes: '',
});
</script>

<template><Head title="New consignment" /><AppLayout>
    <div class="page-head"><div><p class="eyebrow">Counter intake</p><h1>New consignment</h1><p class="muted">Amounts are stored as whole Kenyan shillings.</p></div><Link href="/consignments" class="button">Back to ledger</Link></div>
    <ConsignmentForm :form="form" :branches="branches" :lock-origin="lockOrigin" submit-label="Create consignment note" @submit="form.post('/consignments')" />
</AppLayout></template>
