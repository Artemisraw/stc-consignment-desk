<script setup>
// Shared intake/edit fields. `form` is an Inertia useForm() object owned by the page.
const props = defineProps({ form: Object, branches: Array, showPaid: { type: Boolean, default: true }, submitLabel: String });
defineEmits(['submit']);
const addItem = () => props.form.items.push({ quantity: 1, description: '', amount: '' });
</script>

<template>
    <form @submit.prevent="$emit('submit')" class="grid two">
        <section class="card"><h2>People and route</h2>
            <div class="grid two"><div class="field"><label>Origin</label><select v-model="form.origin_branch_id"><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select></div><div class="field"><label>Destination</label><select v-model="form.destination_branch_id"><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select><div class="error-text">{{ form.errors.destination_branch_id }}</div></div></div>
            <h3>Sender <span class="muted small">optional</span></h3><div class="field"><label>Name</label><input v-model="form.sender.name"></div><div class="grid two"><div class="field"><label>Phone</label><input v-model="form.sender.phone"></div><div class="field"><label>Email</label><input v-model="form.sender.email" type="email"></div></div>
            <h3>Receiver</h3><div class="field"><label>Name</label><input v-model="form.receiver.name" required><div class="error-text">{{ form.errors['receiver.name'] }}</div></div><div class="grid two"><div class="field"><label>Phone</label><input v-model="form.receiver.phone" required><div class="error-text">{{ form.errors['receiver.phone'] }}</div></div><div class="field"><label>Email</label><input v-model="form.receiver.email" type="email"></div></div>
        </section>
        <section class="card"><h2>{{ showPaid ? 'Goods and payment' : 'Goods' }}</h2><p class="field-label">Items</p>
            <div v-for="(item, index) in form.items" :key="index" class="item-row"><input v-model.number="item.quantity" type="number" min="1" aria-label="Quantity"><input v-model="item.description" placeholder="Particulars" aria-label="Particulars"><input class="amount" v-model.number="item.amount" type="number" min="1" placeholder="Amount KES" aria-label="Amount"><button class="button danger" type="button" @click="form.items.splice(index, 1)" :disabled="form.items.length === 1">×</button></div>
            <div class="error-text">{{ form.errors.items }}</div><div class="error-text">{{ form.errors.consignment }}</div><button class="button" type="button" @click="addItem">Add another item</button>
            <div v-if="showPaid" class="field" style="margin-top:1rem"><label>Paid now (KES)</label><input v-model.number="form.paid_amount" type="number" min="0"></div>
            <div class="field" :style="showPaid ? '' : 'margin-top:1rem'"><label>Notes</label><textarea v-model="form.notes"></textarea></div>
            <button class="button primary" :disabled="form.processing">{{ submitLabel }}</button>
        </section>
    </form>
</template>
