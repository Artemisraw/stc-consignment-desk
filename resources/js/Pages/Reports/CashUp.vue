<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';
const props = defineProps({ filters: Object, branches: Array, report: Object, preparedBy: String, printedAt: String });
const shillings = (value) => Number(value).toLocaleString('en-KE');
const day = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('en-KE', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
const when = (value) => value ? new Date(value).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const singleDay = computed(() => props.filters.from === props.filters.to);
const period = computed(() => singleDay.value ? day(props.filters.from) : `${day(props.filters.from)} – ${day(props.filters.to)}`);
const branchName = computed(() => props.branches.find((b) => b.id === props.filters.branch_id)?.name ?? 'All branches');
// On a one-day cash-up the date is in the heading, so rows only need the time.
const stamp = (value) => singleDay.value ? new Date(value).toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit' }) : when(value);

const form = useForm({ from: props.filters.from, to: props.filters.to, branch_id: props.filters.branch_id ?? '' });
const show = () => form.transform((data) => ({ ...data, branch_id: data.branch_id || undefined })).get('/reports/cash-up', { preserveState: true, preserveScroll: true });
const isoDay = (offset) => { const d = new Date(); d.setDate(d.getDate() + offset); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
const pick = (offset) => { form.from = form.to = isoDay(offset); show(); };
const printCashUp = () => window.print();
</script>

<template><Head title="Daily cash-up" /><AppLayout>
    <div class="page-head noprint"><div><p class="eyebrow">Management</p><h1>Daily cash-up</h1><p class="muted">Money taken and reversed, by branch, staff member and payment method.</p></div><div class="actions"><Link href="/reports" class="button">Back to reports</Link><button type="button" class="button primary" @click="printCashUp">Print cash-up</button></div></div>

    <form class="card noprint cashup-filters" @submit.prevent="show">
        <div class="field"><label for="cashup-from">From</label><input id="cashup-from" v-model="form.from" type="date" required></div>
        <div class="field"><label for="cashup-to">To</label><input id="cashup-to" v-model="form.to" type="date" :min="form.from" required></div>
        <div class="field"><label for="cashup-branch">Branch</label><select id="cashup-branch" v-model="form.branch_id"><option value="">All branches</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select></div>
        <div class="actions"><button class="button primary" :disabled="form.processing">Show</button><button type="button" class="button" @click="pick(0)">Today</button><button type="button" class="button" @click="pick(-1)">Yesterday</button></div>
        <div class="error-text" v-for="e in form.errors" :key="e">{{ e }}</div>
    </form>

    <section class="receipt print-sheet manifest cashup">
        <div class="print-brand"><span class="logo">STC</span><div><strong>Sky Top Carriers</strong><small>Nairobi – Mandera courier service</small></div></div>
        <div class="receipt-head"><h2>Cash-up</h2><span class="receipt-number">{{ period }}</span></div>
        <div class="manifest-meta">
            <div><span>Branch</span><strong>{{ branchName }}</strong></div>
            <div><span>Prepared by</span><strong>{{ preparedBy }}</strong></div>
            <div><span>Printed</span><strong>{{ when(printedAt) }}</strong></div>
        </div>

        <div class="cashup-totals">
            <div><span>Received</span><strong>KES {{ shillings(report.totals.received) }}</strong><small>{{ report.totals.payments }} payments</small></div>
            <div><span>Reversed</span><strong>KES {{ shillings(report.totals.reversed) }}</strong><small>{{ report.totals.reversals }} reversals</small></div>
            <div class="net"><span>Net to account for</span><strong>KES {{ shillings(report.totals.net) }}</strong><small>Received less reversed</small></div>
        </div>

        <h3>By payment method</h3>
        <div class="table-wrap print-table"><table>
            <thead><tr><th>Method</th><th class="number">Payments</th><th class="number">Received</th><th class="number">Reversed</th><th class="number">Net</th></tr></thead>
            <tbody>
                <tr v-for="row in report.byMethod" :key="row.key"><td><strong>{{ row.label }}</strong></td><td class="number">{{ row.payments }}</td><td class="number">{{ shillings(row.received) }}</td><td class="number">{{ shillings(row.reversed) }}</td><td class="number">{{ shillings(row.net) }}</td></tr>
                <tr v-if="!report.byMethod.length"><td colspan="5" class="muted">No payments in this period.</td></tr>
            </tbody>
            <tfoot><tr><td>Total</td><td class="number">{{ report.totals.payments }}</td><td class="number">{{ shillings(report.totals.received) }}</td><td class="number">{{ shillings(report.totals.reversed) }}</td><td class="number">{{ shillings(report.totals.net) }}</td></tr></tfoot>
        </table></div>

        <div class="cashup-split">
            <div><h3>By branch</h3><div class="table-wrap print-table"><table>
                <thead><tr><th>Branch</th><th class="number">Received</th><th class="number">Reversed</th><th class="number">Net</th></tr></thead>
                <tbody>
                    <tr v-for="row in report.byBranch" :key="row.key"><td>{{ row.label }}</td><td class="number">{{ shillings(row.received) }}</td><td class="number">{{ shillings(row.reversed) }}</td><td class="number">{{ shillings(row.net) }}</td></tr>
                    <tr v-if="!report.byBranch.length"><td colspan="4" class="muted">None.</td></tr>
                </tbody>
            </table></div></div>
            <div><h3>By staff member</h3><div class="table-wrap print-table"><table>
                <thead><tr><th>Taken by</th><th class="number">Received</th><th class="number">Reversed</th><th class="number">Net</th></tr></thead>
                <tbody>
                    <tr v-for="row in report.byStaff" :key="row.key"><td>{{ row.label }}</td><td class="number">{{ shillings(row.received) }}</td><td class="number">{{ shillings(row.reversed) }}</td><td class="number">{{ shillings(row.net) }}</td></tr>
                    <tr v-if="!report.byStaff.length"><td colspan="4" class="muted">None.</td></tr>
                </tbody>
            </table></div></div>
        </div>

        <h3>Payments received</h3>
        <div class="table-wrap print-table"><table>
            <thead><tr><th>{{ singleDay ? 'Time' : 'Paid' }}</th><th>Tracking no.</th><th>Method</th><th>Reference</th><th>Taken by</th><th>Branch</th><th class="number">KES</th></tr></thead>
            <tbody>
                <tr v-for="p in report.payments" :key="p.id">
                    <td>{{ stamp(p.paid_at) }}</td>
                    <td><Link :href="`/consignments/${p.consignment_id}`"><strong>{{ p.tracking_number }}</strong></Link></td>
                    <td>{{ p.method }}</td><td>{{ p.reference }}</td><td>{{ p.staff ?? '—' }}</td><td>{{ p.branch ?? '—' }}</td>
                    <td class="number">{{ shillings(p.amount) }} <StatusChip v-if="p.status === 'reversed'" status="reversed" /></td>
                </tr>
                <tr v-if="!report.payments.length"><td colspan="7" class="muted">No payments in this period.</td></tr>
            </tbody>
            <tfoot><tr><td colspan="6">{{ report.totals.payments }} payments received</td><td class="number">{{ shillings(report.totals.received) }}</td></tr></tfoot>
        </table></div>
        <p class="small muted" v-if="report.payments.some((p) => p.status === 'reversed')">Payments marked Reversed were taken in this period and given back later; the reversal is listed on the day it was made.</p>

        <h3>Reversals</h3>
        <div class="table-wrap print-table"><table>
            <thead><tr><th>Reversed</th><th>Tracking no.</th><th>Originally paid</th><th>Method</th><th>Taken by</th><th>Reversed by</th><th>Reason</th><th class="number">KES</th></tr></thead>
            <tbody>
                <tr v-for="r in report.reversals" :key="r.id">
                    <td>{{ stamp(r.reversed_at) }}</td>
                    <td><Link :href="`/consignments/${r.consignment_id}`"><strong>{{ r.tracking_number }}</strong></Link></td>
                    <td>{{ when(r.paid_at) }}</td><td>{{ r.method }}</td><td>{{ r.staff ?? '—' }}</td><td>{{ r.reversed_by ?? '—' }}</td><td>{{ r.reason }}</td>
                    <td class="number">{{ shillings(r.amount) }}</td>
                </tr>
                <tr v-if="!report.reversals.length"><td colspan="8" class="muted">No reversals in this period.</td></tr>
            </tbody>
            <tfoot><tr><td colspan="7">{{ report.totals.reversals }} reversals</td><td class="number">{{ shillings(report.totals.reversed) }}</td></tr></tfoot>
        </table></div>

        <p class="small muted">Amounts in KES. M-Pesa payments are listed with their confirmation reference for checking against the till statement.</p>
        <div class="signatures three">
            <div><span>Cash counted (KES)</span></div>
            <div><span>Prepared by (name and signature)</span></div>
            <div><span>Checked by manager (name, signature, date)</span></div>
        </div>
    </section>
</AppLayout></template>
