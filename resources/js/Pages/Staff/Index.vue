<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ staff: Array, branches: Array, roles: Array });

const form = useForm({
    name: '',
    email: '',
    role: 'clerk',
    branch_id: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/staff', {
    onSuccess: () => form.reset(),
});

const toggle = (staffMember) => router.patch(`/staff/${staffMember.id}/toggle`, {}, {
    preserveScroll: true,
});

const roleLabel = (role) => role.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
</script>

<template>
    <Head title="Staff accounts" />
    <AppLayout>
        <div class="page-head">
            <div><p class="eyebrow">Owner controls</p><h1>Staff accounts</h1><p class="muted">Create accounts and control access to the operations desk.</p></div>
        </div>

        <div class="grid sidebar">
            <form class="card" @submit.prevent="submit">
                <div class="card-title"><h2>Add staff member</h2></div>
                <div class="field"><label for="staff-name">Name</label><input id="staff-name" v-model="form.name" required autocomplete="name"><div v-if="form.errors.name" class="error-text">{{ form.errors.name }}</div></div>
                <div class="field"><label for="staff-email">Email</label><input id="staff-email" v-model="form.email" type="email" required autocomplete="email"><div v-if="form.errors.email" class="error-text">{{ form.errors.email }}</div></div>
                <div class="field"><label for="staff-role">Role</label><select id="staff-role" v-model="form.role"><option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option></select><div v-if="form.errors.role" class="error-text">{{ form.errors.role }}</div></div>
                <div class="field"><label for="staff-branch">Branch</label><select id="staff-branch" v-model="form.branch_id" required><option value="" disabled>Select a branch</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select><div v-if="form.errors.branch_id" class="error-text">{{ form.errors.branch_id }}</div></div>
                <div class="field"><label for="staff-password">Initial password</label><input id="staff-password" v-model="form.password" type="password" required minlength="8" autocomplete="new-password"><div v-if="form.errors.password" class="error-text">{{ form.errors.password }}</div></div>
                <div class="field"><label for="staff-password-confirmation">Confirm password</label><input id="staff-password-confirmation" v-model="form.password_confirmation" type="password" required minlength="8" autocomplete="new-password"></div>
                <button class="button primary" :disabled="form.processing">Create account</button>
            </form>

            <section>
                <div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Branch</th><th>Status</th><th></th></tr></thead><tbody>
                    <tr v-for="staffMember in staff" :key="staffMember.id">
                        <td><strong>{{ staffMember.name }}</strong></td><td>{{ staffMember.email }}</td><td>{{ roleLabel(staffMember.role) }}</td><td>{{ staffMember.branch || '—' }}</td>
                        <td><span class="chip" :class="staffMember.is_active ? 'chip-available' : 'chip-maintenance'">{{ staffMember.is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td><div class="actions"><Link class="button" :href="`/staff/${staffMember.id}/edit`">Edit</Link><button v-if="staffMember.can_toggle" class="button" :class="{ danger: staffMember.is_active }" type="button" @click="toggle(staffMember)">{{ staffMember.is_active ? 'Deactivate' : 'Activate' }}</button></div></td>
                    </tr>
                </tbody></table></div>
            </section>
        </div>
    </AppLayout>
</template>
