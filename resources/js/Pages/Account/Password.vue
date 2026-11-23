<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });
const submit = () => form.put('/account/password', {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});
</script>

<template>
    <Head title="Change password" />
    <AppLayout>
        <div class="page-head"><div><p class="eyebrow">My account</p><h1>Change password</h1><p class="muted">Confirm your current password before choosing a new one.</p></div></div>
        <form class="card" style="max-width:560px" @submit.prevent="submit">
            <div class="field"><label for="current-password">Current password</label><input id="current-password" v-model="form.current_password" type="password" required autocomplete="current-password"><div v-if="form.errors.current_password" class="error-text">{{ form.errors.current_password }}</div></div>
            <div class="field"><label for="new-password">New password</label><input id="new-password" v-model="form.password" type="password" required minlength="8" autocomplete="new-password"><div v-if="form.errors.password" class="error-text">{{ form.errors.password }}</div></div>
            <div class="field"><label for="confirm-password">Confirm new password</label><input id="confirm-password" v-model="form.password_confirmation" type="password" required minlength="8" autocomplete="new-password"></div>
            <button class="button primary" :disabled="form.processing">Change password</button>
        </form>
    </AppLayout>
</template>
