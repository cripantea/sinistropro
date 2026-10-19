<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <TastoIndietro :href="route('clienti.index')">Clienti</TastoIndietro>
        <h1 class="text-base font-semibold text-slate-800">Importa clienti</h1>
      </div>
    </template>

    <div class="p-6 max-w-3xl space-y-5">

      <!-- ── Caricamento ─────────────────────────────────────── -->
      <template v-if="fase === 'carica' || fase === 'lettura'">
        <div
          class="bg-white border-2 border-dashed rounded-2xl px-6 py-12 text-center transition-colors"
          :class="[dragOver ? 'border-indigo-400 bg-indigo-50' : 'border-slate-300', fase === 'carica' ? 'cursor-pointer hover:border-indigo-300' : '']"
          @dragover.prevent="dragOver = true"
          @dragleave="dragOver = false"
          @drop.prevent="onDrop"
          @click="fase === 'carica' && fileInput?.click()"
        >
          <input ref="fileInput" type="file" class="hidden" :accept="acceptAttr" @change="onFileChange" />
          <template v-if="fase === 'carica'">
            <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            </div>
            <p class="text-base font-semibold text-slate-800">Trascina qui l'elenco clienti</p>
            <p class="text-sm text-slate-500 mt-1">oppure <span class="text-indigo-600 font-medium">sceglilo dal computer</span> · Excel, CSV, JSON o XML</p>
            <p class="text-xs text-slate-400 mt-4 max-w-md mx-auto">
              Carica ogni volta il file completo del gestionale: vengono aggiunti solo i clienti e i veicoli nuovi
              e aggiornati i dati cambiati. Nessun cliente viene cancellato.
            </p>
          </template>
          <template v-else>
            <Spinner class="w-6 h-6 mx-auto mb-3 text-indigo-600" />
            <p class="text-sm font-medium text-slate-700">Lettura di {{ fileName }}…</p>
            <p class="text-xs text-slate-400 mt-1">Con file grandi può servire qualche secondo.</p>
          </template>
        </div>
        <Errore :msg="error" />

        <!-- Storico -->
        <div v-if="storico.length" class="bg-white rounded-xl border border-slate-200 shadow-sm">
          <p class="px-5 pt-4 pb-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">Importazioni precedenti</p>
          <ul class="divide-y divide-slate-100">
            <li v-for="imp in storico" :key="imp.id" class="px-5 py-2.5 flex items-center gap-3 text-sm">
              <span :class="['w-2 h-2 rounded-full shrink-0', STATO_DOT[imp.stato] ?? 'bg-slate-300']" :title="STATO_LABEL[imp.stato]" />
              <span class="text-slate-700 truncate flex-1" :title="imp.file_name">{{ imp.file_name }}</span>
              <span class="text-xs text-slate-500 shrink-0">
                <template v-if="imp.stato === 'completata' && imp.riepilogo">
                  +{{ n(imp.riepilogo.clienti_nuovi) }} clienti · +{{ n(imp.riepilogo.veicoli_nuovi) }} veicoli
                </template>
                <template v-else>{{ STATO_LABEL[imp.stato] ?? imp.stato }}</template>
              </span>
              <span class="text-xs text-slate-400 shrink-0 w-32 text-right">{{ formatDateTime(imp.created_at) }}<template v-if="imp.user"> · {{ imp.user.name }}</template></span>
            </li>
          </ul>
        </div>
      </template>

      <!-- ── Verifica ────────────────────────────────────────── -->
      <template v-else-if="fase === 'verifica'">
        <!-- File -->
        <div class="flex items-center gap-3 text-sm">
          <svg class="w-5 h-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <span class="font-medium text-slate-800 truncate">{{ fileName }}</span>
          <span class="text-slate-400 shrink-0">{{ n(preview.total) }} righe</span>
          <Spinner v-if="analizzando" class="text-slate-400 shrink-0" />
          <button type="button" @click="reset" class="ml-auto text-xs text-slate-500 hover:text-slate-800 shrink-0">Cambia file</button>
        </div>

        <!-- Manca il nome: unico caso in cui serve intervenire sulle colonne -->
        <div v-if="!hasNome" class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800">
          Non abbiamo capito quale colonna contiene il <strong>nome del cliente</strong>: indicala qui sotto.
        </div>

        <!-- Cosa succederà -->
        <div v-if="analisi" :class="['bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4 transition-opacity', analizzando ? 'opacity-60' : '']">
          <div class="grid grid-cols-3 gap-3">
            <Stat :value="analisi.riepilogo.clienti_nuovi" label="clienti nuovi" :accent="analisi.riepilogo.clienti_nuovi > 0" />
            <Stat :value="analisi.riepilogo.veicoli_nuovi" label="veicoli nuovi" :accent="analisi.riepilogo.veicoli_nuovi > 0" />
            <Stat :value="analisi.riepilogo.clienti_aggiornati + analisi.riepilogo.veicoli_aggiornati" label="schede da aggiornare" :accent="false" />
          </div>
          <p class="text-xs text-slate-500">
            {{ n(analisi.riepilogo.clienti_file) }} clienti nel file, di cui {{ n(analisi.riepilogo.clienti_esistenti) }} già presenti.
            <template v-if="analisi.riepilogo.veicoli_spostati"> {{ n(analisi.riepilogo.veicoli_spostati) }} {{ analisi.riepilogo.veicoli_spostati === 1 ? 'auto è passata' : 'auto sono passate' }} a un altro proprietario e {{ analisi.riepilogo.veicoli_spostati === 1 ? 'verrà spostata' : 'verranno spostate' }} sul nuovo cliente (i sinistri già aperti restano dove sono).</template>
            <template v-if="analisi.riepilogo.scartati"> {{ n(analisi.riepilogo.scartati) }} righe senza nome verranno saltate.</template>
            Nessun cliente verrà cancellato.
          </p>

          <details v-if="analisi.riepilogo.esempi_modifiche.length" class="text-xs text-slate-600">
            <summary class="cursor-pointer text-slate-500 hover:text-slate-800">Cosa verrà aggiornato</summary>
            <ul class="mt-2 space-y-1">
              <li v-for="(e, i) in analisi.riepilogo.esempi_modifiche" :key="i">
                <span class="font-medium text-slate-700">{{ e.nome }}</span>:
                <span v-for="(m, j) in e.modifiche" :key="j">
                  {{ m.campo.toLowerCase() }} <span v-if="m.vecchio" class="line-through text-slate-400">{{ m.vecchio }}</span> → {{ m.nuovo }}<span v-if="j < e.modifiche.length - 1">, </span>
                </span>
              </li>
            </ul>
          </details>

          <ul v-if="analisi.avvisi.length" class="space-y-1">
            <li v-for="a in analisi.avvisi" :key="a.tipo" class="text-xs text-amber-700 flex gap-1.5">
              <span>⚠</span>
              <span>{{ a.conteggio ? n(a.conteggio) + ' · ' : '' }}{{ a.messaggio }}<span v-if="a.righe.length" class="text-amber-600/80"> (righe {{ a.righe.slice(0, 8).join(', ') }}{{ a.conteggio > 8 ? '…' : '' }})</span></span>
            </li>
          </ul>
        </div>

        <!-- Casi dubbi: clienti già inseriti a mano -->
        <div v-if="daDecidere.length" class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 bg-amber-50/60 border-b border-amber-100">
            <h2 class="text-sm font-semibold text-slate-800">{{ daDecidere.length === 1 ? 'Un cliente da controllare' : `${daDecidere.length} clienti da controllare` }}</h2>
            <p class="text-xs text-slate-600 mt-0.5">Clienti che avevate già inserito: dicci se sono la stessa persona del file, così non si creano doppioni.</p>
          </div>
          <ul class="divide-y divide-slate-100">
            <li v-for="c in daDecidere.slice(0, limiteDubbi)" :key="c.id" class="px-5 py-3 space-y-2">
              <div class="grid sm:grid-cols-2 gap-2 text-sm">
                <Persona titolo="In SinistroPro" :nome="c.nome" :info="[c.telefono, c.email, c.codice_fiscale]" :extra="c.pratiche ? `${c.pratiche} sinistr${c.pratiche === 1 ? 'o' : 'i'}` : ''" />
                <Persona v-if="c.match" titolo="Nel file" :nome="c.match.nome ?? ''" :codice="c.match.codice" :info="[c.match.telefono, c.match.email, c.match.codice_fiscale]" />
                <div v-else-if="c.candidati.length" class="text-xs text-slate-500">
                  <p class="text-[11px] uppercase tracking-wide text-slate-400 mb-1">Nel file, stesso {{ CRITERIO_LABEL[c.criterio ?? ''] ?? 'dato' }}</p>
                  <p v-for="k in c.candidati" :key="k.codice ?? ''">{{ k.nome }} <span class="font-mono text-slate-400">#{{ k.codice }}</span></p>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <template v-if="c.match">
                  <button type="button" @click="setAbbinamento(c.id, c.match.codice ?? '')" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Sì, è lo stesso</button>
                  <button type="button" @click="setAbbinamento(c.id, '')" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">No, sono diversi</button>
                </template>
                <template v-else>
                  <button v-for="k in c.candidati" :key="k.codice ?? ''" type="button" @click="setAbbinamento(c.id, k.codice ?? '')" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-indigo-300 text-indigo-700 hover:bg-indigo-50">
                    È #{{ k.codice }}
                  </button>
                  <button type="button" @click="setAbbinamento(c.id, '')" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">Nessuno dei due</button>
                </template>
                <span class="text-[11px] text-slate-400">trovato per {{ CRITERIO_LABEL[c.criterio ?? ''] ?? '—' }}</span>
              </div>
            </li>
          </ul>
          <div class="flex items-center border-t border-slate-100 text-xs">
            <button v-if="daDecidere.length > limiteDubbi" type="button" @click="limiteDubbi += 20" class="flex-1 py-2.5 text-indigo-600 hover:bg-slate-50">
              Mostra altri {{ daDecidere.length - limiteDubbi }}
            </button>
            <button type="button" @click="tieniSeparati" class="flex-1 py-2.5 text-slate-500 hover:bg-slate-50 hover:text-slate-800">
              Tieni separati {{ daDecidere.length === 1 ? 'questo' : `tutti i ${daDecidere.length}` }}
            </button>
          </div>
        </div>

        <!-- Tutti i clienti già inseriti (per chi vuole controllare) -->
        <details v-if="analisi && analisi.riconciliazione.length" class="bg-white rounded-xl border border-slate-200 shadow-sm group">
          <summary class="px-5 py-3 cursor-pointer text-sm text-slate-600 hover:text-slate-900 flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Clienti già inseriti: {{ contaStati.abbinati }} collegati al file, {{ contaStati.separati }} restano separati
          </summary>
          <ul class="divide-y divide-slate-100 border-t border-slate-100 max-h-96 overflow-y-auto">
            <li v-for="c in analisi.riconciliazione" :key="c.id" class="px-5 py-2 flex items-center gap-3 text-xs">
              <span class="font-medium text-slate-800 w-48 truncate">{{ c.nome }}</span>
              <span v-if="c.stato === 'abbinato'" class="text-slate-600 truncate flex-1">→ {{ c.match?.nome }} <span class="font-mono text-slate-400">#{{ c.match?.codice }}</span></span>
              <span v-else class="text-slate-400 flex-1">{{ RIC_LABEL[c.stato] }}</span>
              <button v-if="c.id in abbinamenti" type="button" @click="resetAbbinamento(c.id)" class="text-slate-400 hover:text-slate-700">annulla scelta</button>
              <button v-else-if="c.stato === 'abbinato'" type="button" @click="setAbbinamento(c.id, '')" class="text-slate-400 hover:text-red-600">scollega</button>
            </li>
          </ul>
        </details>

        <!-- Colonne -->
        <details :open="!hasNome" class="bg-white rounded-xl border border-slate-200 shadow-sm group">
          <summary class="px-5 py-3 cursor-pointer text-sm text-slate-600 hover:text-slate-900 flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Colonne: {{ colonneUsate }} di {{ preview.headers.length }} riconosciute
            <span class="text-slate-400 truncate">({{ riepilogoColonne }})</span>
          </summary>
          <div class="border-t border-slate-100 px-5 py-4 space-y-1.5">
            <div v-for="header in preview.headers" :key="header" class="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
              <div class="min-w-0">
                <p class="text-sm text-slate-700 truncate" :title="header">{{ header }}</p>
                <p class="text-[11px] text-slate-400 truncate">{{ sampleOf(header) || '—' }}</p>
              </div>
              <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
              <select
                v-model="mapping[header]"
                :class="['text-sm border rounded-lg px-2 py-1.5 outline-none bg-white focus:ring-2 focus:ring-indigo-500', mapping[header] ? 'border-slate-300 text-slate-800' : 'border-slate-200 text-slate-400']"
              >
                <option value="">Non importare</option>
                <optgroup v-for="(opts, group) in targetGroups" :key="group" :label="group">
                  <option v-for="t in opts" :key="t.value" :value="t.value">{{ t.label }}</option>
                </optgroup>
              </select>
            </div>
            <p v-if="hasNome && !hasCodice" class="text-xs text-amber-700 pt-2">
              Senza il codice cliente del gestionale, un cliente con più righe (es. più auto) diventa più clienti.
              Se il file ha un codice cliente, abbinalo.
            </p>
            <label class="flex items-start gap-2 pt-3 text-xs text-slate-600 cursor-pointer">
              <input v-model="aggiornaEsistenti" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              <span>Aggiorna i dati cambiati dei clienti già presenti (le correzioni fatte a mano in SinistroPro non vengono mai sovrascritte)</span>
            </label>
          </div>
        </details>

        <Errore :msg="error" />

        <!-- Azione -->
        <div class="sticky bottom-4 bg-white/90 backdrop-blur rounded-xl border border-slate-200 shadow-lg px-5 py-3 flex items-center gap-4">
          <p class="text-xs text-slate-500 flex-1">
            <template v-if="daDecidere.length">Prima rispondi {{ daDecidere.length === 1 ? 'al cliente' : `ai ${daDecidere.length} clienti` }} da controllare.</template>
            <template v-else-if="nienteDaFare">Tutto è già aggiornato: non c'è niente da importare.</template>
            <template v-else-if="analisi">Puoi lasciare la pagina durante l'importazione.</template>
          </p>
          <button
            type="button"
            :disabled="!analisi || analizzando || busy || nienteDaFare || daDecidere.length > 0 || !hasNome"
            @click="doEsegui"
            class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-indigo-700 disabled:opacity-40 transition"
          >
            <Spinner v-if="busy" />
            {{ testoImporta }}
          </button>
        </div>
      </template>

      <!-- ── Importazione / fatto ────────────────────────────── -->
      <div v-else class="bg-white rounded-2xl border border-slate-200 shadow-sm px-6 py-10 text-center space-y-4">
        <template v-if="inCorso">
          <Spinner class="w-7 h-7 mx-auto text-indigo-600" />
          <p class="text-base font-semibold text-slate-800">Importazione in corso…</p>
          <div class="max-w-sm mx-auto w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
            <div class="bg-indigo-600 h-1.5 transition-all" :style="{ width: progresso + '%' }" />
          </div>
          <p class="text-xs text-slate-500">Puoi lasciare questa pagina: continua da sola.</p>
        </template>

        <template v-else-if="stato.stato === 'completata'">
          <div class="w-12 h-12 mx-auto rounded-full bg-green-100 text-green-600 flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <p class="text-base font-semibold text-slate-800">Importazione completata</p>
          <p class="text-sm text-slate-600">
            {{ n(stato.riepilogo?.clienti_nuovi) }} clienti e {{ n(stato.riepilogo?.veicoli_nuovi) }} veicoli aggiunti<template v-if="(stato.riepilogo?.clienti_aggiornati ?? 0) + (stato.riepilogo?.veicoli_aggiornati ?? 0)">, {{ n((stato.riepilogo?.clienti_aggiornati ?? 0) + (stato.riepilogo?.veicoli_aggiornati ?? 0)) }} schede aggiornate</template><template v-if="stato.riepilogo?.veicoli_spostati">, {{ n(stato.riepilogo.veicoli_spostati) }} auto spostate sul nuovo proprietario</template>.
          </p>
          <div class="flex items-center justify-center gap-3 pt-2">
            <Link :href="route('clienti.index')" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700">Vai ai clienti</Link>
            <button type="button" @click="nuovaImportazione" class="px-4 py-2 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50">Importa un altro file</button>
          </div>
        </template>

        <template v-else>
          <p class="text-base font-semibold text-red-700">Importazione interrotta</p>
          <p v-if="stato.errore" class="text-xs text-red-600">{{ stato.errore }}</p>
          <p class="text-sm text-slate-600">I clienti già salvati restano. Ricarica lo stesso file: verranno aggiunti solo quelli mancanti.</p>
          <button type="button" @click="nuovaImportazione" class="px-4 py-2 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50">Ricarica il file</button>
        </template>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onBeforeUnmount, ref, watch, type PropType } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TastoIndietro from '@/Components/TastoIndietro.vue'
import axios from 'axios'

interface Target { value: string; label: string; group: string }
interface RiepilogoT {
  righe: number; clienti_file: number; clienti_nuovi: number; clienti_esistenti: number; clienti_aggiornati: number
  clienti_abbinati: number; veicoli_nuovi: number; veicoli_esistenti: number; veicoli_aggiornati: number; veicoli_spostati: number; scartati: number
  esempi_nuovi: string[]
  esempi_modifiche: { nome: string; modifiche: { campo: string; vecchio: string | null; nuovo: string }[] }[]
}
interface Riconciliazione {
  id: number; nome: string; telefono: string | null; email: string | null; codice_fiscale: string | null; pratiche: number
  stato: 'abbinato' | 'da_controllare' | 'conflitto' | 'piu_candidati' | 'nessuno' | 'escluso' | 'codice_non_trovato'
  criterio: string | null
  match: { codice: string | null; nome: string | null; telefono: string | null; email: string | null; codice_fiscale: string | null } | null
  candidati: { codice: string | null; nome: string | null; telefono: string | null }[]
}
interface Avviso { tipo: string; messaggio: string; conteggio: number; righe: number[] }
interface StoricoItem {
  id: number; file_name: string; stato: string; errore: string | null; created_at: string
  riepilogo: Partial<RiepilogoT> | null; user: { id: number; name: string } | null
}

const props = defineProps<{ targets: Target[]; formats: string[]; maxMb: number; storico: StoricoItem[] }>()

const STATO_LABEL: Record<string, string> = { in_coda: 'In coda', in_corso: 'In corso', completata: 'Completata', fallita: 'Interrotta' }
const STATO_DOT: Record<string, string> = { in_coda: 'bg-slate-300', in_corso: 'bg-indigo-500', completata: 'bg-green-500', fallita: 'bg-red-500' }
const RIC_LABEL: Record<string, string> = {
  abbinato: 'collegato', da_controllare: 'da controllare', conflitto: 'più clienti presenti sullo stesso del file: resta separato',
  piu_candidati: 'da controllare', nessuno: 'non presente nel file: resta com\'è', escluso: 'tenuto separato', codice_non_trovato: 'codice non trovato nel file',
}
const CRITERIO_LABEL: Record<string, string> = {
  codice_fiscale: 'codice fiscale', telefono: 'telefono', email: 'email', nome: 'nome', manuale: 'scelta manuale',
}
const DUBBI = ['da_controllare', 'conflitto', 'piu_candidati', 'codice_non_trovato']

type Fase = 'carica' | 'lettura' | 'verifica' | 'importa'
const fase = ref<Fase>('carica')
const fileName = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const dragOver = ref(false)
const busy = ref(false)
const analizzando = ref(false)
const error = ref('')

const preview = ref<{ id: number; headers: string[]; rows: Record<string, string>[]; total: number }>({ id: 0, headers: [], rows: [], total: 0 })
const mapping = ref<Record<string, string>>({})
const aggiornaEsistenti = ref(true)
const abbinamenti = ref<Record<number, string>>({})
const analisi = ref<{ riepilogo: RiepilogoT; avvisi: Avviso[]; riconciliazione: Riconciliazione[] } | null>(null)
const limiteDubbi = ref(20)
const stato = ref<{ stato: string; riepilogo: any; errore: string | null }>({ stato: 'in_coda', riepilogo: null, errore: null })
let pollTimer: ReturnType<typeof setTimeout> | null = null
let analisiTimer: ReturnType<typeof setTimeout> | null = null
let analisiSeq = 0

const n = (v: number | undefined | null) => (v ?? 0).toLocaleString('it-IT')
const acceptAttr = computed(() => props.formats.map(f => '.' + f).join(','))
const targetGroups = computed(() => {
  const groups: Record<string, Target[]> = {}
  for (const t of props.targets) (groups[t.group] ??= []).push(t)
  return groups
})
const hasNome = computed(() => Object.values(mapping.value).includes('nome'))
const hasCodice = computed(() => Object.values(mapping.value).includes('codice_esterno'))
const colonneUsate = computed(() => Object.values(mapping.value).filter(Boolean).length)
const riepilogoColonne = computed(() => {
  const v = Object.values(mapping.value)
  const parti = [v.includes('nome') && 'nome', v.includes('codice_esterno') && 'codice',
    v.some(t => t.startsWith('veicolo.')) && 'veicoli', v.some(t => ['telefono', 'email'].includes(t)) && 'contatti']
  return parti.filter(Boolean).join(', ') || 'nessuna'
})

// I clienti già inseriti su cui serve una risposta (quelli già decisi dall'utente spariscono dalla lista).
// Si chiede solo dove una risposta è possibile (serve il codice cliente del file).
const daDecidere = computed(() => (analisi.value?.riconciliazione ?? []).filter(c =>
  DUBBI.includes(c.stato) && !(c.id in abbinamenti.value) && (!!c.match?.codice || c.candidati.some(k => k.codice))))
const contaStati = computed(() => {
  const list = analisi.value?.riconciliazione ?? []
  const abbinati = list.filter(c => c.stato === 'abbinato').length
  return { abbinati, separati: list.length - abbinati }
})
const nienteDaFare = computed(() => {
  const r = analisi.value?.riepilogo
  return !!r && r.clienti_nuovi + r.veicoli_nuovi + r.clienti_aggiornati + r.veicoli_aggiornati + (r.veicoli_spostati ?? 0) + r.clienti_abbinati === 0
})
const testoImporta = computed(() => {
  const r = analisi.value?.riepilogo
  if (!r) return 'Importa'
  const parti = []
  if (r.clienti_nuovi) parti.push(`${n(r.clienti_nuovi)} clienti`)
  if (r.veicoli_nuovi) parti.push(`${n(r.veicoli_nuovi)} veicoli`)
  return parti.length ? `Importa ${parti.join(' e ')}` : 'Aggiorna i clienti'
})
const inCorso = computed(() => ['in_coda', 'in_corso'].includes(stato.value.stato))
const progresso = computed(() => {
  const r = stato.value.riepilogo
  return r?.totale ? Math.max(4, Math.round((r.elaborati / r.totale) * 100)) : 4
})

// Ogni modifica (colonne, opzione, risposte) rifà la verifica da sola: niente pulsante "Verifica".
watch([mapping, aggiornaEsistenti], () => scheduleAnalisi(400), { deep: true })

function sampleOf(header: string) {
  return preview.value.rows.map(r => r[header]).find(v => v !== '' && v != null) ?? ''
}

function formatDateTime(iso: string) {
  return new Date(iso).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function apiError(err: any, fallback: string) {
  return err.response?.data?.error ?? err.response?.data?.message ?? fallback
}

function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) carica(file)
}

function onDrop(e: DragEvent) {
  dragOver.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file && fase.value === 'carica') carica(file)
}

async function carica(file: File) {
  error.value = ''
  const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
  if (!props.formats.includes(ext)) {
    error.value = 'Formato non supportato. Usa Excel (.xls, .xlsx), CSV, JSON o XML.'
    return
  }
  if (file.size > props.maxMb * 1024 * 1024) {
    error.value = `Il file è troppo grande (max ${props.maxMb} MB).`
    return
  }

  fileName.value = file.name
  fase.value = 'lettura'
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data } = await axios.post(route('clienti.import.preview'), fd)
    preview.value = { id: data.importazione_id, headers: data.headers, rows: data.preview, total: data.total }
    abbinamenti.value = {}
    analisi.value = null
    mapping.value = data.mapping // fa partire la verifica (watch)
    fase.value = 'verifica'
  } catch (err: any) {
    error.value = apiError(err, 'Errore durante la lettura del file.')
    fase.value = 'carica'
  } finally {
    if (fileInput.value) fileInput.value.value = ''
  }
}

function scheduleAnalisi(delay: number) {
  if (analisiTimer) clearTimeout(analisiTimer)
  analisiTimer = setTimeout(analizza, delay)
}

async function analizza() {
  if (fase.value !== 'verifica' || !hasNome.value) return
  const mine = ++analisiSeq
  analizzando.value = true
  error.value = ''
  try {
    const { data } = await axios.post(route('clienti.import.analizza', preview.value.id), payload())
    if (mine === analisiSeq) analisi.value = data
  } catch (err: any) {
    if (mine === analisiSeq) error.value = apiError(err, 'Errore durante la verifica del file.')
  } finally {
    if (mine === analisiSeq) analizzando.value = false
  }
}

function payload() {
  return { mapping: mapping.value, aggiorna_esistenti: aggiornaEsistenti.value, abbinamenti: abbinamenti.value }
}

function setAbbinamento(id: number, codice: string) {
  abbinamenti.value = { ...abbinamenti.value, [id]: codice.trim() }
  scheduleAnalisi(0)
}

function tieniSeparati() {
  abbinamenti.value = { ...abbinamenti.value, ...Object.fromEntries(daDecidere.value.map(c => [c.id, ''])) }
  scheduleAnalisi(0)
}

function resetAbbinamento(id: number) {
  const { [id]: _, ...rest } = abbinamenti.value
  abbinamenti.value = rest
  scheduleAnalisi(0)
}

async function doEsegui() {
  busy.value = true
  error.value = ''
  try {
    await axios.post(route('clienti.import.esegui', preview.value.id), payload())
    stato.value = { stato: 'in_coda', riepilogo: null, errore: null }
    fase.value = 'importa'
    poll()
  } catch (err: any) {
    error.value = apiError(err, 'Errore durante l\'avvio dell\'importazione.')
  } finally {
    busy.value = false
  }
}

async function poll() {
  try {
    const { data } = await axios.get(route('clienti.import.stato', preview.value.id))
    stato.value = data
  } catch {
    // rete instabile: si riprova al giro successivo
  }
  if (inCorso.value) pollTimer = setTimeout(poll, 1000)
}

function reset() {
  fase.value = 'carica'
  fileName.value = ''
  error.value = ''
  preview.value = { id: 0, headers: [], rows: [], total: 0 }
  analisi.value = null
  abbinamenti.value = {}
  limiteDubbi.value = 20
  mapping.value = {}
}

function nuovaImportazione() {
  reset()
  router.reload({ only: ['storico'] })
}

onBeforeUnmount(() => {
  if (pollTimer) clearTimeout(pollTimer)
  if (analisiTimer) clearTimeout(analisiTimer)
})

// ── Componenti locali ──────────────────────────────────────────────────────

const Spinner = defineComponent({
  setup: () => () => h('svg', { class: 'animate-spin w-4 h-4', fill: 'none', viewBox: '0 0 24 24' }, [
    h('circle', { class: 'opacity-25', cx: 12, cy: 12, r: 10, stroke: 'currentColor', 'stroke-width': 4 }),
    h('path', { class: 'opacity-75', fill: 'currentColor', d: 'M4 12a8 8 0 018-8v8H4z' }),
  ]),
})

const Errore = defineComponent({
  props: { msg: { type: String, default: '' } },
  setup: (p) => () => p.msg ? h('div', { class: 'text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3' }, p.msg) : null,
})

const Stat = defineComponent({
  props: { value: { type: Number, required: true }, label: { type: String, required: true }, accent: Boolean },
  setup: (p) => () => h('div', { class: ['rounded-xl px-4 py-3', p.accent ? 'bg-indigo-50' : 'bg-slate-50'] }, [
    h('p', { class: ['text-2xl font-bold tabular-nums', p.accent ? 'text-indigo-700' : 'text-slate-700'] }, n(p.value)),
    h('p', { class: 'text-xs text-slate-500 mt-0.5' }, p.label),
  ]),
})

const Persona = defineComponent({
  props: {
    titolo: { type: String, required: true }, nome: { type: String, required: true },
    info: { type: Array as PropType<(string | null)[]>, default: () => [] }, codice: { type: String as PropType<string | null>, default: null },
    extra: { type: String, default: '' },
  },
  setup: (p) => () => h('div', { class: 'min-w-0' }, [
    h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-400' }, p.titolo),
    h('p', { class: 'font-medium text-slate-800 truncate' }, [p.nome, p.codice ? h('span', { class: 'font-mono font-normal text-slate-400 ml-1 text-xs' }, `#${p.codice}`) : null]),
    h('p', { class: 'text-xs text-slate-500 truncate' }, p.info.filter(Boolean).join(' · ') || '—'),
    p.extra ? h('p', { class: 'text-xs text-indigo-600' }, p.extra) : null,
  ]),
})
</script>
