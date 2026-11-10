<template>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div class="flex items-center justify-between gap-3 px-5 py-3.5 bg-indigo-600">
      <span class="flex items-center gap-2 text-white font-semibold text-sm">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        Corrispondenza email
        <span v-if="messaggi.length" class="text-indigo-200 font-normal">· {{ messaggi.length }}</span>
      </span>
      <button v-if="caricato && configurata && !scrivendo" type="button" @click="apriNuova" class="text-xs font-semibold bg-white text-indigo-700 rounded-lg px-3 py-1.5 hover:bg-indigo-50">+ Scrivi email</button>
    </div>

    <div v-if="!caricato" class="p-6 flex justify-center">
      <svg class="animate-spin w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
    </div>

    <template v-else>
      <!-- Nuova email dal sinistro -->
      <form v-if="scrivendo" @submit.prevent="invia" class="p-4 space-y-3 border-b border-gray-100 bg-gray-50/60">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">A</label>
          <input v-model="bozza.to" type="email" required list="destinatari-sinistro" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="email@esempio.it" />
          <datalist id="destinatari-sinistro"><option v-for="d in destinatari" :key="d.email" :value="d.email">{{ d.label }}</option></datalist>
          <div v-if="destinatari.length" class="flex flex-wrap gap-1.5 mt-1.5">
            <button v-for="d in destinatari" :key="d.email" type="button" @click="bozza.to = d.email"
              class="text-xs rounded-full border px-2.5 py-0.5" :class="bozza.to === d.email ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:bg-white'">
              {{ d.label }}: {{ d.email }}
            </button>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Oggetto</label>
          <input v-model="bozza.subject" type="text" required maxlength="255" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Messaggio</label>
          <textarea v-model="bozza.body" rows="6" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" />
        </div>
        <SceltaFirma v-model="bozza.firmaId" :stato="firme" />
        <input ref="fileInput" type="file" multiple class="text-xs text-gray-500" />
        <p v-if="errore" class="text-xs text-red-600">{{ errore }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" @click="scrivendo = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-white rounded-lg">Annulla</button>
          <button type="submit" :disabled="invio" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">{{ invio ? 'Invio…' : 'Invia' }}</button>
        </div>
      </form>

      <p v-if="!configurata" class="px-5 pt-4 text-xs text-gray-500">Nessuna casella email collegata: le email si vedono qui, ma per scriverne serve collegare la casella in Impostazioni → Casella email.</p>

      <p v-if="!messaggi.length" class="p-6 text-sm text-gray-400 italic text-center">
        Nessuna email per questo sinistro. Qui compaiono quelle scritte dal sinistro (con le risposte), quelle con il cliente e quelle che citano il numero del sinistro nell'oggetto.
      </p>

      <ul v-else class="divide-y divide-gray-100 max-h-[560px] overflow-y-auto">
        <li v-for="m in messaggi" :key="m.id">
          <button type="button" @click="aperto = aperto === m.id ? null : m.id" class="w-full text-left px-5 py-3 hover:bg-gray-50 flex items-start gap-3">
            <span class="mt-0.5 text-xs font-semibold rounded px-1.5 py-0.5 shrink-0" :class="m.direction === 'outbound' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700'">
              {{ m.direction === 'outbound' ? 'Inviata' : 'Ricevuta' }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium text-gray-800 truncate">{{ m.direction === 'outbound' ? 'A ' + (m.toAddresses ?? []).join(', ') : (m.fromName || m.fromAddress) }}</span>
                <span class="text-xs text-gray-400 shrink-0">{{ data(m.createdAt) }}</span>
              </span>
              <span class="block text-sm text-gray-600 truncate">{{ m.subject || '(senza oggetto)' }}</span>
              <span v-if="m.status === 'failed'" class="text-xs text-red-600">Invio non riuscito</span>
              <span v-if="m.attachments.some((a) => !a.inline)" class="text-xs text-gray-400">📎 {{ m.attachments.filter((a) => !a.inline).length }} allegati</span>
            </span>
          </button>
          <div v-if="aperto === m.id" class="px-5 pb-4">
            <div v-if="m.bodyHtml" class="email-body text-sm text-gray-800 leading-relaxed break-words border-l-2 border-gray-200 pl-3" v-html="m.bodyHtml" />
            <p v-else class="text-sm text-gray-800 whitespace-pre-wrap break-words border-l-2 border-gray-200 pl-3">{{ m.bodyText }}</p>
            <div v-if="m.attachments.some((a) => !a.inline)" class="flex flex-wrap gap-2 mt-3">
              <button v-for="a in m.attachments.filter((x) => !x.inline)" :key="a.id" type="button" @click="scarica(a.id)"
                class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 text-gray-700 max-w-[16rem] truncate">📎 {{ a.filename }}</button>
            </div>
            <div class="mt-3 flex gap-3 text-xs">
              <button v-if="configurata" type="button" @click="rispondi(m)" class="text-indigo-600 font-semibold hover:underline">Rispondi</button>
              <Link :href="route('email.index')" class="text-gray-500 hover:underline">Apri in Email</Link>
            </div>
          </div>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import SceltaFirma from '@/Components/Email/SceltaFirma.vue'
import type { StatoFirme } from '@/Components/Email/GestioneFirme.vue'
import type { EmailMessageItem } from '@/Components/Email/EmailThread.vue'

const props = defineProps<{ praticaId: number; firme: StatoFirme }>()

const firme = reactive<StatoFirme>({ ...props.firme })
const caricato = ref(false)
const configurata = ref(false)
const messaggi = ref<EmailMessageItem[]>([])
const destinatari = ref<{ label: string; email: string }[]>([])
const oggettoPredefinito = ref('')
const aperto = ref<number | null>(null)

const scrivendo = ref(false)
const invio = ref(false)
const errore = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const bozza = reactive<{ to: string; subject: string; body: string; firmaId: number | null }>({ to: '', subject: '', body: '', firmaId: null })

async function carica() {
  const { data } = await axios.get(route('pratiche.email', props.praticaId))
  messaggi.value = data.messages
  destinatari.value = data.destinatari
  oggettoPredefinito.value = data.oggetto
  configurata.value = data.emailConfigured
  caricato.value = true
}
onMounted(carica)

function apriNuova() {
  Object.assign(bozza, { to: destinatari.value[0]?.email ?? '', subject: oggettoPredefinito.value, body: '', firmaId: firme.preferita })
  errore.value = null
  scrivendo.value = true
}

function rispondi(m: EmailMessageItem) {
  const oggetto = m.subject ?? ''
  Object.assign(bozza, {
    to: m.direction === 'outbound' ? (m.toAddresses?.[0] ?? '') : m.fromAddress,
    subject: /^re:/i.test(oggetto) ? oggetto : `Re: ${oggetto}`,
    body: '',
    firmaId: firme.preferita,
  })
  errore.value = null
  scrivendo.value = true
}

async function invia() {
  if (invio.value) return
  invio.value = true
  errore.value = null
  try {
    const fd = new FormData()
    fd.append('to', bozza.to)
    fd.append('subject', bozza.subject)
    fd.append('body', bozza.body)
    fd.append('pratica_id', String(props.praticaId))
    if (bozza.firmaId) fd.append('firma_id', String(bozza.firmaId))
    Array.from(fileInput.value?.files ?? []).forEach((f) => fd.append('attachments[]', f))
    const { data } = await axios.post(route('email.compose'), fd)
    if (data.message?.status === 'failed') {
      errore.value = 'L\'email è stata salvata ma l\'invio non è riuscito: controlla la casella in Impostazioni.'
    } else {
      scrivendo.value = false
    }
    await carica()
  } catch (e: any) {
    errore.value = e?.response?.data?.message ?? 'Invio non riuscito. Riprova.'
  } finally {
    invio.value = false
  }
}

async function scarica(id: number) {
  const { data } = await axios.get(route('email.attachments.download', id))
  window.open(data.url, '_blank')
}

const data = (iso: string) => new Date(iso).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
</script>

<style scoped>
.email-body :deep(img) { max-width: 100%; height: auto; }
.email-body :deep(p) { margin: 0 0 .6em; }
.email-body :deep(a) { color: #4f46e5; text-decoration: underline; }
.email-body :deep(blockquote) { border-left: 3px solid #e5e7eb; margin: .6em 0; padding-left: .8em; color: #6b7280; }
.email-body { overflow-x: auto; }
</style>
