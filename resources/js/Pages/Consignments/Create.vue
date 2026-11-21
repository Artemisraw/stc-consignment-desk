<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
const props = defineProps({ branches: Array });
const form = useForm({
    origin_branch_id: props.branches[0]?.id, destination_branch_id: props.branches.at(-1)?.id,
    sender: { name: '', phone: '', email: '' }, receiver: { name: '', phone: '', email: '' },
    items: [{ quantity: 1, description: '', amount: '' }], paid_amount: 0, notes: '',
});
const addItem = () => form.items.push({ quantity: 1, description: '', amount: '' });
const submit = () => form.post('/consignments');
</script>

<template><Head title="New consignment" /><AppLayout>
    <div class="page-head"><div><p class="eyebrow">Counter intake</p><h1>New consignment</h1><p class="muted">Amounts are stored as whole Kenyan shillings.</p></div><Link href="/consignments" class="button">Back to ledger</Link></div>
    <form @submit.prevent="submit" class="grid two">
        <section class="card"><h2>People and route</h2>
            <div class="grid two"><div class="field"><label>Origin</label><select v-model="form.origin_branch_id"><option v-for="b in branches" :value="b.id">{{ b.name }}</option></select></div><div class="field"><label>Destination</label><select v-model="form.destination_branch_id"><option v-for="b in branches" :value="b.id">{{ b.name }}</option></select></div></div>
            <h3>Sender <span class="muted small">optional</span></h3><div class="field"><label>Name</label><input v-model="form.sender.name"></div><div class="grid two"><div class="field"><label>Phone</label><input v-model="form.sender.phone"></div><div class="field"><label>Email</label><input v-model="form.sender.email" type="email"></div></div>
            <h3>Receiver</h3><div class="field"><label>Name</label><input v-model="form.receiver.name" required><div class="error-text">{{ form.errors['receiver.name'] }}</div></div><div class="grid two"><div class="field"><label>Phone</label><input v-model="form.receiver.phone" required><div class="error-text">{{ form.errors['receiver.phone'] }}</div></div><div class="field"><label>Email</label><input v-model="form.receiver.email" type="email"></div></div>
        </section>
        <section class="card"><h2>Goods and payment</h2><p class="field-label">Items</p>
            <div v-for="(item,index) in form.items" :key="index" class="item-row"><input v-model.number="item.quantity" type="number" min="1" aria-label="Quantity"><input v-model="item.description" placeholder="Particulars" aria-label="Particulars"><input class="amount" v-model.number="item.amount" type="number" min="1" placeholder="Amount KES" aria-label="Amount"><button class="button danger" type="button" @click="form.items.splice(index,1)" :disabled="form.items.length===1">×</button></div>
            <div class="error-text">{{ form.errors.items }}</div><button class="button" type="button" @click="addItem">Add another item</button>
            <div class="field" style="margin-top:1rem"><label>Paid now (KES)</label><input v-model.number="form.paid_amount" type="number" min="0"></div><div class="field"><label>Notes</label><textarea v-model="form.notes"></textarea></div>
            <button class="button primary" :disabled="form.processing">Create consignment note</button>
        </section>
    </form>
</AppLayout></template>
