<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash || {});
const nav = [
    ['/dashboard', 'Dashboard'], ['/consignments', 'Consignments'], ['/consignments/create', 'New consignment'],
    ['/trips', 'Trips'], ['/fleet', 'Fleet'], ['/reports', 'Reports'], ['/staff', 'Staff'], ['/', 'Public tracking'],
];
const active = (href) => href === '/' ? page.url === '/' : page.url.startsWith(href);
</script>

<template>
    <div class="app-shell">
        <header class="masthead">
            <Link href="/dashboard" class="brand">
                <span class="logo">STC</span>
                <span><strong>Sky Top Carriers</strong><small>Consignment operations desk</small></span>
            </Link>
            <div class="user-block" v-if="user">
                <span><strong>{{ user.name }}</strong><small>{{ user.role.replaceAll('_', ' ') }}</small></span>
                <Link href="/account/password" class="quiet-button">Password</Link>
                <Link href="/logout" method="post" as="button" class="quiet-button">Sign out</Link>
            </div>
        </header>
        <nav class="topnav" aria-label="Main navigation">
            <template v-for="item in nav" :key="item[0]">
                <Link
                    v-if="(item[0] !== '/reports' || ['manager','owner'].includes(user?.role)) && (item[0] !== '/staff' || user?.role === 'owner')"
                    :href="item[0]"
                    :class="{ active: active(item[0]) }"
                >{{ item[1] }}</Link>
            </template>
        </nav>
        <div v-if="flash.success" class="flash success" role="status">{{ flash.success }}</div>
        <div v-if="flash.error" class="flash error" role="alert">{{ flash.error }}</div>
        <main class="page"><slot /></main>
    </div>
</template>

<style scoped>
@media (max-width: 600px) {
    .masthead { flex-wrap: wrap; }
    .user-block { margin-left: auto; gap: .4rem; }
}
</style>
