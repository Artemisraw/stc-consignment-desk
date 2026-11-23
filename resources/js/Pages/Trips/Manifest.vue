<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';
defineProps({ trip: Object, rows: Array, totals: Object, printedAt: String });
const shillings = (value) => Number(value).toLocaleString('en-KE');
const when = (value) => value ? new Date(value).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const printManifest = () => window.print();
</script>

<template><Head :title="`Manifest ${trip.trip_number}`" /><AppLayout>
    <div class="page-head noprint"><div><p class="eyebrow">Trip manifest</p><h1>{{ trip.trip_number }} <StatusChip :status="trip.status" /></h1></div><div class="actions"><Link href="/trips" class="button">Back to trips</Link><button type="button" class="button primary" @click="printManifest">Print manifest</button></div></div>

    <section class="receipt print-sheet manifest">
        <div class="print-brand"><span class="logo">STC</span><div><strong>Sky Top Carriers</strong><small>Nairobi – Mandera courier service</small></div></div>
        <div class="receipt-head"><h2>Loading manifest</h2><span class="receipt-number">{{ trip.trip_number }}</span></div>
        <div class="manifest-meta">
            <div><span>Vehicle</span><strong>{{ trip.vehicle }}</strong></div>
            <div><span>Driver</span><strong>{{ trip.driver }}<template v-if="trip.driver_phone"> · {{ trip.driver_phone }}</template></strong></div>
            <div><span>Licence no.</span><strong>{{ trip.driver_licence }}</strong></div>
            <div><span>Loaded at</span><strong>{{ trip.origin }} ({{ trip.direction }})</strong></div>
            <div><span>Departed</span><strong>{{ when(trip.departed_at) }}</strong></div>
            <div><span>Printed</span><strong>{{ when(printedAt) }}</strong></div>
        </div>
        <div class="table-wrap print-table">
            <table>
                <thead><tr><th>#</th><th>Tracking no.</th><th>Receiver</th><th>Phone</th><th>To</th><th class="number">Pieces</th><th class="number">Paid</th><th class="number">To collect</th><th class="noprint">Status</th><th class="print-only">Checked</th></tr></thead>
                <tbody><tr v-for="(row, index) in rows" :key="row.tracking_number">
                    <td>{{ index + 1 }}</td><td><strong>{{ row.tracking_number }}</strong></td><td>{{ row.receiver }}</td><td>{{ row.phone }}</td><td>{{ row.destination }}</td>
                    <td class="number">{{ row.pieces }}</td><td class="number">{{ shillings(row.paid) }}</td><td class="number">{{ shillings(row.balance) }}</td>
                    <td class="noprint"><StatusChip :status="row.status" /></td><td class="print-only check-box"></td>
                </tr></tbody>
                <tfoot><tr><td colspan="5">{{ totals.consignments }} consignments</td><td class="number">{{ totals.pieces }}</td><td class="number">{{ shillings(totals.paid) }}</td><td class="number">{{ shillings(totals.balance) }}</td><td></td></tr></tfoot>
            </table>
        </div>
        <p class="small muted">Amounts in KES. "To collect" is the balance the receiving branch collects on delivery.</p>
        <div class="signatures three">
            <div><span>Dispatched by (name and signature)</span></div>
            <div><span>Driver’s signature</span></div>
            <div><span>Received at destination (name, signature, date)</span></div>
        </div>
    </section>
</AppLayout></template>
