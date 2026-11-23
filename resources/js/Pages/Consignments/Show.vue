<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';
const props = defineProps({ consignment: Object, trackingUrl: String });
const money = (value) => `KES ${Number(value).toLocaleString('en-KE')}`;
const shillings = (value) => Number(value).toLocaleString('en-KE');
const issued = computed(() => new Date(props.consignment.created_at).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' }));
const pieces = computed(() => props.consignment.items.reduce((sum, item) => sum + Number(item.quantity), 0));
const payment = useForm({ amount: props.consignment.balance, method: 'cash', reference: '' });
const recordPayment = () => payment.post(`/consignments/${props.consignment.id}/payments`, { onSuccess: () => payment.reset() });
const deliver = useForm({});
// Templates cannot reach `window`, so printing goes through a script function.
const printNote = () => window.print();
</script>

<template><Head :title="consignment.tracking_number" /><AppLayout>
    <div class="page-head noprint"><div><p class="eyebrow">Consignment record</p><h1>{{ consignment.tracking_number }} <StatusChip :status="consignment.status" /></h1></div><div class="actions"><Link href="/consignments" class="button">Back</Link><button type="button" class="button primary" @click="printNote">Print note</button></div></div>
    <div class="grid sidebar">
        <section class="receipt print-sheet">
            <div class="print-brand"><span class="logo">STC</span><div><strong>Sky Top Carriers</strong><small>Nairobi – Mandera courier service</small></div></div>
            <div class="receipt-head"><h2>Consignment note</h2><span class="receipt-number">{{ consignment.tracking_number }}</span></div>
            <div class="receipt-row"><span>Issued</span><strong>{{ issued }} · {{ consignment.origin_branch.name }}</strong></div>
            <div class="receipt-row"><span>Sender</span><strong>{{ consignment.sender?.name || 'Counter sale' }}<template v-if="consignment.sender?.phone"> · {{ consignment.sender.phone }}</template></strong></div>
            <div class="receipt-row"><span>Receiver</span><strong>{{ consignment.receiver.name }} · {{ consignment.receiver.phone }}</strong></div>
            <div class="receipt-row"><span>Route</span><strong>{{ consignment.origin_branch.name }} to {{ consignment.destination_branch.name }}</strong></div>
            <table class="receipt-table"><thead><tr><th>Qty</th><th>Particulars</th><th class="number">KES</th></tr></thead>
                <tbody><tr v-for="i in consignment.items" :key="i.id"><td>{{ i.quantity }}</td><td>{{ i.description }}</td><td class="number">{{ shillings(i.amount) }}</td></tr></tbody>
                <tfoot><tr><td>{{ pieces }}</td><td>Total</td><td class="number">{{ shillings(consignment.total_amount) }}</td></tr></tfoot>
            </table>
            <div class="receipt-row"><span>Paid</span><strong>{{ money(consignment.paid_amount) }}</strong></div>
            <div class="receipt-row"><span>Balance on delivery</span><strong>{{ money(consignment.balance) }}</strong></div>
            <p v-if="consignment.notes" class="small"><strong>Notes:</strong> {{ consignment.notes }}</p>
            <p class="small">Track this consignment at <strong>{{ trackingUrl }}</strong></p>
            <div class="signatures">
                <div><span>Sender’s signature</span></div>
                <div><span>Served by: {{ consignment.creator?.name }}</span></div>
            </div>
            <div class="signatures">
                <div><span>Collected by (name and ID no.)</span></div>
                <div><span>Receiver’s signature and date</span></div>
            </div>
            <p class="small muted">Goods carried at owner’s risk. Claims must be made in writing within 15 days.</p>
        </section>
        <div class="noprint">
            <section class="card" v-if="consignment.balance > 0"><h2>Record payment</h2><form @submit.prevent="recordPayment"><div class="grid two"><div class="field"><label>Amount (KES)</label><input v-model.number="payment.amount" type="number" min="1" :max="consignment.balance"></div><div class="field"><label>Method</label><select v-model="payment.method"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="balance_on_delivery">Balance on delivery</option></select></div></div><div class="field"><label>Reference <span class="muted">optional</span></label><input v-model="payment.reference"></div><div class="error-text" v-for="e in payment.errors">{{ e }}</div><button class="button primary">Record payment</button></form></section>
            <section class="card" v-if="consignment.status === 'arrived'" style="margin-top:1rem"><h2>Ready for collection</h2><p>The remaining balance will be recorded automatically when handed over.</p><button class="button primary" @click="deliver.post(`/consignments/${consignment.id}/deliver`)" :disabled="deliver.processing">Hand over to receiver</button></section>
            <section class="card" style="margin-top:1rem"><h2>Tracking history</h2><ol class="timeline"><li v-for="event in [...consignment.tracking_events].reverse()" :key="event.id"><strong>{{ event.branch?.name || 'System' }}</strong><br>{{ event.description }}<br><small class="muted">{{ new Date(event.occurred_at).toLocaleString('en-KE') }}</small></li></ol></section>
        </div>
    </div>
</AppLayout></template>
