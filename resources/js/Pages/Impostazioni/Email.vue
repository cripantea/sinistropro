<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">Casella email</h2>
    </template>

    <div class="py-6 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
      <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 rounded">
        {{ flash.success }}
      </div>
      <div v-if="flash?.error" class="bg-red-50 border-l-4 border-red-500 px-4 py-3 text-sm text-red-800 rounded">
        {{ flash.error }}
      </div>

      <!-- ── Casella già collegata ─────────────────────────────── -->
      <div v-if="collegata && !modifica" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start gap-3">
          <span class="mt-1 w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-500">Casella collegata</p>
            <p class="text-lg font-semibold text-gray-800 truncate">{{ collegata.email }}</p>
            <p v-if="collegata.microsoft" class="text-xs text-gray-500">Collegata con l'accesso Microsoft</p>
            <p class="text-sm text-gray-500 mt-1">Le email inviate e ricevute compaiono nella sezione Email. Gli avvisi e le automazioni partono da questo indirizzo, con il nome "{{ collegata.nome }}".</p>
          </div>
        </div>
        <div class="flex flex-wrap gap-2 mt-5">
          <Link :href="route('email.index')" class="bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700">Apri la posta</Link>
          <button type="button" @click="apriModifica" class="text-sm font-semibold px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cambia password o casella</button>
          <button type="button" @click="scollega" class="text-sm font-semibold px-4 py-2 rounded-lg text-red-600 hover:bg-red-50">Scollega</button>
        </div>
      </div>

      <!-- ── Modulo collegamento ───────────────────────────────── -->
      <form v-else @submit.prevent="collega" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
        <div>
          <h3 class="text-lg font-semibold text-gray-800">Collega la casella dei sinistri</h3>
          <p class="text-sm text-gray-500 mt-1">Scrivi l'indirizzo: i server li troviamo noi. Le email arriveranno in SinistroPro e gli avvisi partiranno da qui.</p>
        </div>

        <!-- Outlook / Microsoft 365: sempre in vista, senza password -->
        <div v-if="microsoftDisponibile && provider?.chiave !== 'microsoft'" class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 p-3">
          <a :href="microsoftUrl" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-800 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-gray-50">
            <svg class="w-4 h-4" viewBox="0 0 23 23" aria-hidden="true"><path fill="#f25022" d="M1 1h10v10H1z"/><path fill="#7fba00" d="M12 1h10v10H12z"/><path fill="#00a4ef" d="M1 12h10v10H1z"/><path fill="#ffb900" d="M12 12h10v10H12z"/></svg>
            Accedi con Microsoft
          </a>
          <span class="text-xs text-gray-500">Per caselle Outlook, Hotmail o Microsoft 365. Per le altre scrivi l'indirizzo qui sotto.</span>
        </div>

        <div>
          <label class="field-label">Indirizzo email</label>
          <input v-model="form.email" @blur="rileva" @keydown.enter.prevent="rileva" type="email" class="field-input" placeholder="sinistri@tuaagenzia.it" autocomplete="off" />
          <p v-if="form.errors.email" class="text-sm text-red-600 mt-1">{{ form.errors.email }}</p>
        </div>

        <p v-if="rilevamento.loading" class="text-sm text-gray-500">Cerco il provider…</p>

        <!-- Provider riconosciuto -->
        <div v-else-if="provider" class="rounded-lg p-4 text-sm" :class="provider.supportato ? 'bg-indigo-50 text-indigo-900' : 'bg-amber-50 text-amber-900'">
          <p class="font-semibold">{{ provider.nome }}</p>
          <template v-if="!provider.supportato">
            <p class="mt-1">{{ provider.nota }}</p>
            <template v-if="provider.chiave === 'microsoft' && microsoftDisponibile">
              <div class="mt-3">
                <label class="field-label">Nome che vedono i clienti</label>
                <input v-model="form.nome" type="text" class="field-input" :placeholder="nomeAgenzia" />
              </div>
              <a :href="microsoftUrl" class="mt-3 inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-800 text-sm font-semibold px-4 py-2.5 rounded-lg hover:bg-gray-50">
                <svg class="w-4 h-4" viewBox="0 0 23 23" aria-hidden="true"><path fill="#f25022" d="M1 1h10v10H1z"/><path fill="#7fba00" d="M12 1h10v10H12z"/><path fill="#00a4ef" d="M1 12h10v10H1z"/><path fill="#ffb900" d="M12 12h10v10H12z"/></svg>
                Accedi con Microsoft
              </a>
            </template>
          </template>
          <template v-else>
            <ol class="mt-2 space-y-1 list-decimal list-inside">
              <li v-for="(passo, i) in provider.istruzioni" :key="i">{{ passo }}</li>
            </ol>
            <a v-if="provider.link" :href="provider.link" target="_blank" rel="noopener" class="inline-block mt-3 font-semibold underline underline-offset-2">
              Apri la pagina per creare la password →
            </a>
          </template>
        </div>

        <p v-else-if="rilevamento.nonTrovato" class="rounded-lg p-4 text-sm bg-gray-50 text-gray-700">
          Non riconosco il provider di questo indirizzo. Apri "Impostazioni avanzate" qui sotto e scrivi i server: li trovi nelle istruzioni del tuo provider o te li dà chi gestisce la posta.
        </p>

        <template v-if="!provider || provider.supportato">
          <div>
            <label class="field-label">{{ provider?.password_app ? 'Password per le app' : 'Password della casella' }}</label>
            <input v-model="form.password" type="password" class="field-input" autocomplete="new-password"
              :placeholder="collegata && form.email.toLowerCase() === collegata.email.toLowerCase() ? 'lascia vuota per tenere quella salvata' : ''" />
            <p v-if="form.errors.password" class="text-sm text-red-600 mt-1">{{ form.errors.password }}</p>
          </div>

          <div>
            <label class="field-label">Nome che vedono i clienti</label>
            <input v-model="form.nome" type="text" class="field-input" :placeholder="nomeAgenzia" />
          </div>

          <div>
            <button type="button" @click="form.avanzate = !form.avanzate" class="text-sm text-gray-500 hover:text-gray-700 underline underline-offset-2">
              {{ form.avanzate ? 'Nascondi impostazioni avanzate' : 'Impostazioni avanzate' }}
            </button>
            <div v-if="form.avanzate" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="sm:col-span-3">
                <label class="field-label">Nome utente (di solito l'email)</label>
                <input v-model="form.username" type="text" class="field-input" :placeholder="form.email" />
              </div>
              <div class="sm:col-span-2">
                <label class="field-label">Server in arrivo (IMAP)</label>
                <input v-model="form.imap_host" type="text" class="field-input" placeholder="imap.esempio.it" />
                <p v-if="form.errors.imap_host" class="text-sm text-red-600 mt-1">{{ form.errors.imap_host }}</p>
              </div>
              <div>
                <label class="field-label">Porta</label>
                <input v-model.number="form.imap_port" type="number" class="field-input" placeholder="993" />
              </div>
              <div class="sm:col-span-2">
                <label class="field-label">Server in uscita (SMTP)</label>
                <input v-model="form.host" type="text" class="field-input" placeholder="smtp.esempio.it" />
                <p v-if="form.errors.host" class="text-sm text-red-600 mt-1">{{ form.errors.host }}</p>
              </div>
              <div>
                <label class="field-label">Porta</label>
                <input v-model.number="form.port" type="number" class="field-input" placeholder="465" />
              </div>
              <div class="sm:col-span-3">
                <label class="field-label">Cifratura invio</label>
                <select v-model="form.encryption" class="field-input">
                  <option value="ssl">SSL (porta 465)</option>
                  <option value="tls">STARTTLS (porta 587)</option>
                </select>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="submit" :disabled="form.processing || !form.email" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
              {{ form.processing ? 'Provo il collegamento…' : 'Collega casella' }}
            </button>
            <button v-if="collegata" type="button" @click="modifica = false" class="text-sm text-gray-500 hover:text-gray-700">Annulla</button>
          </div>
        </template>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { PageProps } from '@/types'

interface Collegata {
  email: string; nome: string; username: string
  host: string; port: number; encryption: 'ssl' | 'tls' | null
  imap_host: string; imap_port: number; imap_encryption: 'ssl' | 'tls' | null
  microsoft: boolean
}
interface Provider {
  chiave: string; nome: string; supportato: boolean; password_app: boolean
  link: string | null; istruzioni: string[]; nota: string | null; username: string
  imap: { host: string; port: number; encryption: 'ssl' | 'tls' }
  smtp: { host: string; port: number; encryption: 'ssl' | 'tls' }
}

const props = defineProps<{ collegata: Collegata | null; nomeAgenzia: string; microsoftDisponibile: boolean }>()

const page = usePage<PageProps>()
const flash = computed(() => page.props.flash)

const modifica = ref(false)
const provider = ref<Provider | null>(null)
const rilevamento = reactive({ loading: false, nonTrovato: false, ultima: '' })

const form = useForm({
  email: props.collegata?.email ?? '',
  password: '',
  nome: props.collegata?.nome ?? '',
  avanzate: false,
  username: props.collegata?.username ?? '',
  host: props.collegata?.host ?? '',
  port: props.collegata?.port ?? 465,
  encryption: props.collegata?.encryption ?? 'ssl',
  imap_host: props.collegata?.imap_host ?? '',
  imap_port: props.collegata?.imap_port ?? 993,
})

const microsoftUrl = computed(() =>
  route('impostazioni.email.microsoft', { email: form.email.trim(), nome: form.nome || undefined })
)

async function rileva() {
  const email = form.email.trim()
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email) || email === rilevamento.ultima) return
  rilevamento.ultima = email
  rilevamento.loading = true
  rilevamento.nonTrovato = false
  form.clearErrors('email')
  try {
    const { data } = await axios.post<{ provider: Provider | null }>(route('impostazioni.email.rileva'), { email })
    provider.value = data.provider
    rilevamento.nonTrovato = !data.provider
    if (data.provider) {
      // Precompila le avanzate, così chi le apre parte da valori giusti.
      form.username = data.provider.username
      form.host = data.provider.smtp.host
      form.port = data.provider.smtp.port
      form.encryption = data.provider.smtp.encryption
      form.imap_host = data.provider.imap.host
      form.imap_port = data.provider.imap.port
    } else {
      form.avanzate = true
    }
  } catch {
    provider.value = null
    rilevamento.nonTrovato = true
  } finally {
    rilevamento.loading = false
  }
}

function collega() {
  form
    .transform((d) => ({ ...d, imap_encryption: Number(d.imap_port) === 143 ? 'tls' : 'ssl' }))
    .post(route('impostazioni.email.store'), {
      preserveScroll: true,
      onSuccess: () => {
        form.password = ''
        modifica.value = false
      },
    })
}

function apriModifica() {
  modifica.value = true
  rileva()
}

function scollega() {
  if (!window.confirm('Scollegare la casella? Avvisi e automazioni via email si fermano finché non ne colleghi una.')) return
  router.delete(route('impostazioni.email.destroy'), { preserveScroll: true })
}
</script>

<style scoped>
.field-label { @apply block text-sm font-medium text-slate-700 mb-1; }
.field-input {
  @apply block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
         focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none
         bg-white placeholder-slate-400;
}
</style>
