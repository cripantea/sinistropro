<template>
  <SuperadminLayout title="Nuova agenzia">
    <form @submit.prevent="submit" class="p-6 max-w-xl space-y-6">
      <FormSection title="Account del cliente">
        <p class="text-sm text-slate-500 mb-5">
          L'agenzia nasce con stati e compagnie standard e paga con Stripe. Il resto lo configuri dopo dalla scheda, oppure lo configura il cliente.
        </p>

        <div class="space-y-4">
          <div>
            <label class="field-label">Nome agenzia *</label>
            <input v-model="form.name" type="text" class="field-input" placeholder="es. Agenzia Rossi Assicurazioni" autofocus />
            <FieldError :message="form.errors.name" />
          </div>
          <div>
            <label class="field-label">Nome e cognome del referente</label>
            <input v-model="form.admin_name" type="text" class="field-input" placeholder="es. Mario Rossi" />
            <FieldError :message="form.errors.admin_name" />
          </div>
          <div>
            <label class="field-label">Email di accesso *</label>
            <input v-model="form.admin_email" type="email" class="field-input" placeholder="mario@agenzia.it" />
            <FieldError :message="form.errors.admin_email" />
          </div>
          <div>
            <label class="field-label">Password provvisoria *</label>
            <div class="flex gap-2">
              <input v-model="form.admin_password" type="text" class="field-input font-mono" />
              <button type="button" @click="form.admin_password = generaPassword()" class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-800 px-2">Rigenera</button>
            </div>
            <p class="text-xs text-slate-400 mt-1">Al primo accesso il cliente sceglie una password sua.</p>
            <FieldError :message="form.errors.admin_password" />
          </div>
        </div>
      </FormSection>

      <FormSection title="Prezzo (IVA inclusa)">
        <p class="text-sm text-slate-500 mb-4">Vuoto = prezzo standard: 99 €/mese o 990 €/anno. Se metti solo il mensile, l'annuale vale 10 mesi.</p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="field-label">Mensile €</label>
            <input v-model="form.price_monthly" type="number" min="1" step="0.01" class="field-input" placeholder="99" />
            <FieldError :message="form.errors.price_monthly" />
          </div>
          <div>
            <label class="field-label">Annuale €</label>
            <input v-model="form.price_yearly" type="number" min="1" step="0.01" class="field-input" :placeholder="form.price_monthly ? String(Number(form.price_monthly) * 10) : '990'" />
            <FieldError :message="form.errors.price_yearly" />
          </div>
        </div>
      </FormSection>

      <div class="flex items-center gap-4">
        <button type="submit" :disabled="form.processing" class="inline-flex items-center bg-indigo-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-indigo-700 disabled:opacity-60">
          {{ form.processing ? 'Creazione…' : 'Crea agenzia' }}
        </button>
        <Link :href="route('superadmin.tenants.index')" class="text-sm text-slate-500 hover:underline">Annulla</Link>
      </div>
    </form>
  </SuperadminLayout>
</template>

<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'
import FormSection from '@/Components/Superadmin/FormSection.vue'
import FieldError from '@/Components/Superadmin/FieldError.vue'

// Facile da dettare al telefono: niente caratteri ambigui (0/O, 1/l/I).
function generaPassword(): string {
  const caratteri = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'
  const valori = crypto.getRandomValues(new Uint32Array(10))
  return Array.from(valori, (v) => caratteri[v % caratteri.length]).join('')
}

const form = useForm({
  name: '',
  admin_name: '',
  admin_email: '',
  admin_password: generaPassword(),
  price_monthly: '' as string | number,
  price_yearly: '' as string | number,
})

const submit = () => form.post(route('superadmin.tenants.store'))
</script>

<style scoped>
.field-label { @apply block text-sm font-medium text-slate-700 mb-1; }
.field-input {
  @apply block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
         focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none
         bg-white placeholder-slate-400;
}
</style>
