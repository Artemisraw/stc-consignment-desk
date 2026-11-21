<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
const form = useForm({ email: 'owner@skytop.test', password: 'password', remember: false });
const submit = () => form.post('/login');
</script>

<template>
    <Head title="Sign in" />
    <div class="auth-page">
        <section class="auth-brand">
            <p class="eyebrow" style="color:#ffb29e">Nairobi–Mandera corridor</p>
            <h1>Every load.<br>Every stop.<br>Accounted for.</h1>
            <p>Sky Top Carriers operational desk for fleet, consignments, trips and collections.</p>
        </section>
        <section class="auth-panel">
            <form @submit.prevent="submit">
                <p class="eyebrow">Staff access</p><h2>Sign in to the desk</h2>
                <p class="muted">The seeded owner account is filled in for local evaluation.</p>
                <div class="field"><label for="email">Email</label><input id="email" v-model="form.email" type="email" autocomplete="email"><div class="error-text" v-if="form.errors.email">{{ form.errors.email }}</div></div>
                <div class="field"><label for="password">Password</label><input id="password" v-model="form.password" type="password" autocomplete="current-password"><div class="error-text" v-if="form.errors.password">{{ form.errors.password }}</div></div>
                <label class="check-row" style="border:0"><input v-model="form.remember" type="checkbox"><span>Keep me signed in</span></label>
                <div class="actions"><button class="button primary" :disabled="form.processing">Sign in</button><Link href="/" class="button">Track a parcel</Link></div>
            </form>
        </section>
    </div>
</template>
