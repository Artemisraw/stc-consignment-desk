<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import StatusChip from '../../Components/StatusChip.vue';
const props = defineProps({ tracking: String, searched: Boolean, consignment: Object, branches: Array });
const query = ref(props.tracking || '');
const submit = () => router.get('/', { tracking: query.value.trim().toUpperCase() });
const money = (v) => `KES ${Number(v).toLocaleString('en-KE')}`;
const currentOrder = () => {
    const currentBranch = props.consignment?.events?.at(-1)?.branch;
    return props.branches.find((branch) => branch.name === currentBranch)?.route_order || 0;
};
const receiverPunctuation = () => props.consignment?.receiver?.trim().endsWith('.') ? '' : '.';
</script>

<template><Head title="Track a consignment" /><div class="tracking-shell">
    <header class="tracking-hero"><nav class="public-nav" aria-label="Public navigation"><div class="public-brand"><span class="logo">STC</span><span><strong>Sky Top Carriers</strong><small>Nairobi–Mandera courier</small></span></div><Link href="/login">Staff sign in →</Link></nav><div class="tracking-intro"><p class="eyebrow">Public tracking</p><h1>Know where your goods are.</h1><p>Follow a consignment along the corridor, from counter receipt to collection.</p><form class="tracking-form" role="search" @submit.prevent="submit"><input v-model="query" placeholder="Enter tracking number, e.g. STC-5154" aria-label="Tracking number"><button class="button" type="submit">Track consignment</button></form></div></header>
    <main class="tracking-content">
        <section v-if="consignment" class="tracking-result"><div class="tracking-summary"><div><p class="eyebrow">{{ consignment.origin }} → {{ consignment.destination }}</p><h2>Your goods are moving.</h2><p>Consignment for <strong>{{ consignment.receiver }}</strong>{{ receiverPunctuation() }} The latest recorded stop appears below.</p></div><div class="tracking-number"><span>Tracking number</span><strong>{{ consignment.tracking_number }}</strong><StatusChip :status="consignment.status" /></div></div>
            <div class="route-wrap"><div class="route"><div v-for="b in branches" class="stop" :class="{done:b.route_order <= currentOrder(), destination:b.name === consignment.destination}"><i></i><span>{{ b.name }}</span></div></div></div>
            <div class="tracking-details"><div><h3>Journey history</h3><ol class="timeline"><li v-for="event in [...consignment.events].reverse()" :key="`${event.occurred_at}-${event.status}-${event.branch}`"><strong>{{ event.branch || 'System' }}</strong>: {{ event.description }}<br><small class="muted">{{ new Date(event.occurred_at).toLocaleString('en-KE') }}</small></li></ol></div><aside class="collection-card"><span>Collection</span><strong>{{ consignment.receiver }}</strong><span>Balance due</span><strong>{{ money(consignment.balance) }}</strong><p>Pay any remaining balance when the consignment is handed over at {{ consignment.destination }}.</p></aside></div>
        </section>
        <div v-else-if="searched" class="empty" style="background:var(--card)">No consignment matched <strong>{{ tracking }}</strong>. Check the number printed on the receipt. You can try STC-5154.</div>
        <div v-else class="card"><h2>Simple, accountable delivery</h2><p>Enter the full tracking number from your consignment note. Status updates appear as the truck moves through each branch.</p></div>
    </main>
</div></template>
