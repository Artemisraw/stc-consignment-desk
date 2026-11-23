<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ staffMember: Object, branches: Array, roles: Array });
const form = useForm({
    name: props.staffMember.name,
    email: props.staffMember.email,
    role: props.staffMember.role,
    branch_id: props.staffMember.branch_id,
});
const submit = () => form.put(`/staff/${props.staffMember.id}`);
</script>

<template>
    <Head :title="`Edit ${staffMember.name}`" />
    <AppLayout>
        <div class="page-head"><div><p class="eyebrow">Staff accounts</p><h1>Edit {{ staffMember.name }}</h1><p class="muted">Update this staff member's details and role.</p></div></div>
        <form class="card" style="max-width:680px" @submit.prevent="submit">
            <div class="field"><label for="edit-name">Name</label><input id="edit-name" v-model="form.name" required autocomplete="name"><div v-if="form.errors.name" class="error-text">{{ form.errors.name }}</div></div>
            <div class="field"><label for="edit-email">Email</label><input id="edit-email" v-model="form.email" type="email" required autocomplete="email"><div v-if="form.errors.email" class="error-text">{{ form.errors.email }}</div></div>
            <div class="field"><label for="edit-role">Role</label><select id="edit-role" v-model="form.role" :disabled="staffMember.is_self"><option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option></select><p v-if="staffMember.is_self" class="muted small">You cannot demote your own account.</p><div v-if="form.errors.role" class="error-text">{{ form.errors.role }}</div></div>
            <div class="field"><label for="edit-branch">Branch</label><select id="edit-branch" v-model="form.branch_id" required><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select><div v-if="form.errors.branch_id" class="error-text">{{ form.errors.branch_id }}</div></div>
            <div class="actions"><button class="button primary" :disabled="form.processing">Save changes</button><Link class="button" href="/staff">Cancel</Link></div>
        </form>
    </AppLayout>
</template>
