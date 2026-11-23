<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';
const props = defineProps({ consignment: Object, trackingUrl: String, can: Object });
const money = (value) => `KES ${Number(value).toLocaleString('en-KE')}`;
const shillings = (value) => Number(value).toLocaleString('en-KE');
const issued = computed(() => new Date(props.consignment.created_at).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' }));
const pieces = computed(() => props.consignment.items.reduce((sum, item) => sum + Number(item.quantity), 0));
const payment = useForm({ amount: props.consignment.balance, method: 'cash', reference: '' });
// Keep the suggested amount in step with the balance after payments, reversals or edits.
watch(() => props.consignment.balance, (balance) => { payment.amount = balance; });
const recordPayment = () => payment.post(`/consignments/${props.consignment.id}/payments`, { onSuccess: () => payment.reset() });
const deliver = useForm({});
const cancelForm = useForm({ reason: '' });
const cancelConsignment = () => cancelForm.post(`/consignments/${props.consignment.id}/cancel`, { preserveScroll: true });
const reversing = ref(null);
const reverseForm = useForm({ reason: '' });
const reversePayment = (payment) => reverseForm.post(`/consignments/${props.consignment.id}/payments/${payment.id}/reverse`, {
    preserveScroll: true, onSuccess: () => { reversing.value = null; reverseForm.reset(); },
});
const canReverse = (payment) => props.can.reverse_payments && payment.status === 'completed' && !['delivered', 'cancelled'].includes(props.consignment.status);
const when = (value) => new Date(value).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' });
// Templates cannot reach `window`, so printing goes through a script function.
const printNote = () => window.print();
</script>

<template><Head :title="consignment.tracking_number" /><AppLayout>
    <div class="page-head noprint"><div><p class="eyebrow">Consignment record</p><h1>{{ consignment.tracking_number }} <StatusChip :status="consignment.status" /></h1></div><div class="actions"><Link href="/consignments" class="button">Back</Link><button type="button" class="button primary" @click="printNote">Print note</button></div></div>
    <div class="grid sidebar">
        <section class="receipt print-sheet">
            <div class="print-brand"><span class="logo">STC</span><div><strong>Sky Top Carriers</strong><small>Nairobi – Mandera courier service</small></div></div>
            <div class="receipt-head"><h2>Consignment note</h2><span class="receipt-number">{{ consignment.tracking_number }}</span></div>
            <p v-if="consignment.status === 'cancelled'" class="void-stamp">Cancelled · {{ consignment.cancellation_reason }}</p>
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
            <section class="card" v-if="consignment.balance > 0 && can.pay"><h2>Record payment</h2><form @submit.prevent="recordPayment"><div class="grid two"><div class="field"><label>Amount (KES)</label><input v-model.number="payment.amount" type="number" min="1" :max="consignment.balance"></div><div class="field"><label>Method</label><select v-model="payment.method"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="balance_on_delivery">Balance on delivery</option></select></div></div><div class="field"><label>Reference <span class="muted">optional</span></label><input v-model="payment.reference"></div><div class="error-text" v-for="e in payment.errors">{{ e }}</div><button class="button primary">Record payment</button></form></section>
            <section class="card" v-if="consignment.status === 'arrived'" style="margin-top:1rem"><h2>Ready for collection</h2><template v-if="can.deliver"><p>The remaining balance will be recorded automatically when handed over.</p><button class="button primary" @click="deliver.post(`/consignments/${consignment.id}/deliver`)" :disabled="deliver.processing">Hand over to receiver</button></template><p v-else class="muted">Waiting at {{ consignment.destination_branch.name }}; staff there hand it over.</p></section>
            <section class="card" style="margin-top:1rem" v-if="can.edit || can.cancel"><h2>Corrections</h2>
                <p class="small muted">Allowed until the consignment is loaded on a trip.</p>
                <Link v-if="can.edit" :href="`/consignments/${consignment.id}/edit`" class="button">Edit details and items</Link>
                <form v-if="can.cancel && consignment.paid_amount === 0" @submit.prevent="cancelConsignment" style="margin-top:1rem">
                    <div class="field"><label>Reason for cancelling</label><input v-model="cancelForm.reason" required maxlength="255"></div>
                    <div class="error-text" v-for="e in cancelForm.errors">{{ e }}</div>
                    <button class="button danger" :disabled="cancelForm.processing">Cancel consignment</button>
                </form>
                <p v-else-if="can.cancel" class="small" style="margin-top:1rem">To cancel, a manager must first reverse the KES {{ Number(consignment.paid_amount).toLocaleString('en-KE') }} already paid.</p>
            </section>
            <section class="card" style="margin-top:1rem" v-if="consignment.payments.length"><h2>Payments</h2>
                <div class="error-text" v-if="$page.props.errors.payment">{{ $page.props.errors.payment }}</div>
                <div v-for="p in consignment.payments" :key="p.id" class="payment-row" :class="{ reversed: p.status === 'reversed' }">
                    <div><strong>{{ money(p.amount) }}</strong> <StatusChip :status="p.status" /><br>
                        <span class="small muted">{{ p.method.replaceAll('_', ' ') }} · {{ p.reference }} · {{ when(p.paid_at) }}<template v-if="p.recorder"> · {{ p.recorder.name }}</template></span>
                        <p v-if="p.status === 'reversed'" class="small">Reversed by {{ p.reverser?.name }} on {{ when(p.reversed_at) }}: {{ p.reversal_reason }}</p>
                    </div>
                    <button v-if="canReverse(p) && reversing !== p.id" type="button" class="button danger" @click="reversing = p.id">Reverse</button>
                    <form v-if="reversing === p.id" @submit.prevent="reversePayment(p)" class="reverse-form">
                        <input v-model="reverseForm.reason" placeholder="Reason for reversal" required maxlength="255" aria-label="Reason for reversal">
                        <div class="actions"><button class="button danger" :disabled="reverseForm.processing">Confirm reversal</button><button type="button" class="button" @click="reversing = null">Keep</button></div>
                        <div class="error-text" v-for="e in reverseForm.errors">{{ e }}</div>
                    </form>
                </div>
            </section>
            <section class="card" style="margin-top:1rem"><h2>Tracking history</h2><ol class="timeline"><li v-for="event in [...consignment.tracking_events].reverse()" :key="event.id"><strong>{{ event.branch?.name || 'System' }}</strong><br>{{ event.description }}<br><small class="muted">{{ new Date(event.occurred_at).toLocaleString('en-KE') }}</small></li></ol></section>
        </div>
    </div>
</AppLayout></template>
