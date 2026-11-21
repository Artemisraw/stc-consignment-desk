<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';
const props = defineProps({ consignment: Object });
const money = (value) => `KES ${Number(value).toLocaleString('en-KE')}`;
const payment = useForm({ amount: props.consignment.balance, method: 'cash', reference: '' });
const recordPayment = () => payment.post(`/consignments/${props.consignment.id}/payments`, { onSuccess: () => payment.reset() });
const deliver = useForm({});
</script>

<template><Head :title="consignment.tracking_number" /><AppLayout>
    <div class="page-head noprint"><div><p class="eyebrow">Consignment record</p><h1>{{ consignment.tracking_number }} <StatusChip :status="consignment.status" /></h1></div><div class="actions"><Link href="/consignments" class="button">Back</Link><button class="button primary" @click="window.print()">Print note</button></div></div>
    <div class="grid sidebar">
        <section class="receipt">
            <div class="receipt-head"><h2>Consignment note</h2><span class="receipt-number">{{ consignment.tracking_number }}</span></div>
            <div class="receipt-row"><span>Sender</span><strong>{{ consignment.sender?.name || 'Counter sale' }}</strong></div>
            <div class="receipt-row"><span>Receiver</span><strong>{{ consignment.receiver.name }} · {{ consignment.receiver.phone }}</strong></div>
            <div class="receipt-row"><span>Route</span><strong>{{ consignment.origin_branch.name }} to {{ consignment.destination_branch.name }}</strong></div>
            <table><thead><tr><th>Qty</th><th>Particulars</th><th class="number">KES</th></tr></thead><tbody><tr v-for="i in consignment.items"><td>{{ i.quantity }}</td><td>{{ i.description }}</td><td class="number">{{ Number(i.amount).toLocaleString('en-KE') }}</td></tr></tbody></table>
            <div class="receipt-row"><span>Total</span><strong>{{ money(consignment.total_amount) }}</strong></div><div class="receipt-row"><span>Paid</span><strong>{{ money(consignment.paid_amount) }}</strong></div><div class="receipt-row"><span>Balance on delivery</span><strong>{{ money(consignment.balance) }}</strong></div>
            <p class="small muted">Goods carried at owner’s risk. Claims must be made in writing within 15 days.</p>
        </section>
        <div>
            <section class="card noprint" v-if="consignment.balance > 0"><h2>Record payment</h2><form @submit.prevent="recordPayment"><div class="grid two"><div class="field"><label>Amount (KES)</label><input v-model.number="payment.amount" type="number" min="1" :max="consignment.balance"></div><div class="field"><label>Method</label><select v-model="payment.method"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="balance_on_delivery">Balance on delivery</option></select></div></div><div class="field"><label>Reference <span class="muted">optional</span></label><input v-model="payment.reference"></div><div class="error-text" v-for="e in payment.errors">{{ e }}</div><button class="button primary">Record payment</button></form></section>
            <section class="card noprint" v-if="consignment.status === 'arrived'" style="margin-top:1rem"><h2>Ready for collection</h2><p>The remaining balance will be recorded automatically when handed over.</p><button class="button primary" @click="deliver.post(`/consignments/${consignment.id}/deliver`)" :disabled="deliver.processing">Hand over to receiver</button></section>
            <section class="card" style="margin-top:1rem"><h2>Tracking history</h2><ol class="timeline"><li v-for="event in [...consignment.tracking_events].reverse()" :key="event.id"><strong>{{ event.branch?.name || 'System' }}</strong><br>{{ event.description }}<br><small class="muted">{{ new Date(event.occurred_at).toLocaleString('en-KE') }}</small></li></ol></section>
        </div>
    </div>
</AppLayout></template>
