<template>
  <SuperadminLayout title="Dashboard">
    <div class="p-6 lg:p-8 space-y-6 max-w-6xl">

      <!-- ── Incasso e obiettivo ─────────────────────────────────────────── -->
      <section class="rounded-2xl bg-slate-900 text-white p-6 lg:p-7">
        <div class="flex flex-wrap items-start justify-between gap-6">
          <div>
            <p class="text-sm text-slate-400">Incasso mensile</p>
            <p class="mt-1 text-4xl font-bold tabular-nums tracking-tight">{{ euro(incasso_mensile_cents) }}</p>
            <p class="mt-2 text-sm text-slate-400">
              <span class="text-white font-semibold">{{ agenzie_paganti }}</span> agenzie paganti su {{ agenzie_totali }}
              <span class="text-slate-500"> · IVA inclusa, annuali divisi per 12</span>
            </p>
          </div>

          <div class="min-w-[220px]">
            <form v-if="modificaObiettivo" @submit.prevent="salvaObiettivo" class="flex items-center gap-2">
              <span class="text-sm text-slate-400">€/mese</span>
              <input v-model.number="formObiettivo.euro" type="number" min="0" step="1" class="w-28 text-sm text-slate-900 rounded-lg border-0 px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-400" autofocus />
              <button type="submit" :disabled="formObiettivo.processing" class="text-sm font-semibold bg-indigo-500 rounded-lg px-3 py-1.5 hover:bg-indigo-400 disabled:opacity-60">Salva</button>
              <button type="button" @click="modificaObiettivo = false" class="text-sm text-slate-400 hover:text-white">Annulla</button>
            </form>
            <template v-else>
              <p class="text-sm text-slate-400 text-right">Obiettivo</p>
              <p class="text-2xl font-semibold tabular-nums text-right">{{ obiettivo_cents ? euro(obiettivo_cents) : '—' }}</p>
              <button type="button" @click="modificaObiettivo = true" class="mt-1 block ml-auto text-sm font-medium text-indigo-300 hover:text-white">
                {{ obiettivo_cents ? 'Cambia obiettivo' : 'Imposta obiettivo' }}
              </button>
            </template>
          </div>
        </div>

        <div v-if="obiettivo_cents" class="mt-6">
          <div class="h-3 rounded-full bg-slate-700 overflow-hidden">
            <div class="h-full rounded-full bg-emerald-400 transition-all duration-700" :style="{ width: Math.min(percentuale, 100) + '%' }"></div>
          </div>
          <div class="mt-2 flex flex-wrap justify-between gap-2 text-sm">
            <span class="font-semibold text-emerald-300">{{ percentuale }}% raggiunto</span>
            <span v-if="mancano > 0" class="text-slate-400">
              Mancano <span class="text-white font-semibold">{{ euro(mancano) }}</span> al mese ·
              circa <span class="text-white font-semibold">{{ agenzieMancanti }}</span> agenzie a {{ euro(PREZZO_STANDARD) }}
            </span>
            <span v-else class="text-emerald-300">Obiettivo raggiunto 🎉</span>
          </div>
        </div>
      </section>

      <!-- ── Uso e vendite ───────────────────────────────────────────────── -->
      <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="k in riquadri" :key="k.titolo" class="rounded-2xl border p-5" :class="k.allarme ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white'">
          <div class="flex items-center gap-2 text-sm" :class="k.allarme ? 'text-amber-800' : 'text-slate-500'">
            <span class="w-2 h-2 rounded-full" :class="k.punto"></span>
            {{ k.titolo }}
          </div>
          <p class="mt-3 text-3xl font-bold tabular-nums" :class="k.allarme ? 'text-amber-700' : 'text-slate-900'">
            {{ k.valore }}<span v-if="k.su !== undefined" class="text-base font-medium text-slate-400"> / {{ k.su }}</span>
          </p>
          <p class="mt-1 text-xs" :class="k.allarme ? 'text-amber-700' : 'text-slate-400'">{{ k.nota }}</p>
        </div>
      </section>

      <!-- ── Salute delle agenzie ────────────────────────────────────────── -->
      <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100">
          <div>
            <h2 class="text-base font-semibold text-slate-900">Salute delle agenzie</h2>
            <p class="text-xs text-slate-500 mt-0.5">Le più a rischio in cima. Passa sul punteggio per il dettaglio.</p>
          </div>
          <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-1 text-sm">
            <button type="button" @click="soloInattive = false" :class="['px-3 py-1 rounded-md transition', !soloInattive ? 'bg-white shadow-sm font-semibold text-slate-900' : 'text-slate-500']">Tutte ({{ agenzie.length }})</button>
            <button type="button" @click="soloInattive = true" :class="['px-3 py-1 rounded-md transition', soloInattive ? 'bg-white shadow-sm font-semibold text-slate-900' : 'text-slate-500']">Ferme ({{ inattive }})</button>
          </div>
        </div>

        <div v-if="!righe.length" class="px-5 py-12 text-center text-sm text-slate-400">
          {{ soloInattive ? 'Nessuna agenzia ferma. 🎉' : 'Nessuna agenzia.' }}
        </div>

        <ul v-else class="divide-y divide-slate-100">
          <li
            v-for="a in righe"
            :key="a.id"
            @click="apri(a.id)"
            class="grid grid-cols-12 items-center gap-4 px-5 py-4 hover:bg-slate-50 cursor-pointer transition"
          >
            <!-- Agenzia -->
            <div class="col-span-12 md:col-span-4 flex items-center gap-3 min-w-0">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shrink-0" :class="livello(a.salute).avatar">
                {{ iniziali(a.nome) }}
              </div>
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 truncate">{{ a.nome }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <BillingBadge :state="a.stato" />
                  <span v-if="a.mensile_cents" class="text-xs text-slate-600 tabular-nums">{{ euro(a.mensile_cents) }}/mese</span>
                  <span v-else-if="a.prezzo_mancante" class="text-xs text-amber-700">prezzo da impostare</span>
                </div>
              </div>
            </div>

            <!-- Salute -->
            <div class="col-span-6 md:col-span-3" :title="`Uso recente ${a.voci_salute.recente}/40 · Utenti attivi ${a.voci_salute.utenti}/20 · Sinistri del mese ${a.voci_salute.sinistri}/20 · Configurazione ${a.voci_salute.setup}/20`">
              <div class="flex items-baseline justify-between">
                <span class="text-xs font-semibold" :class="livello(a.salute).testoColore">{{ livello(a.salute).testo }}</span>
                <span class="text-sm font-bold tabular-nums text-slate-800">{{ a.salute }}</span>
              </div>
              <div class="mt-1.5 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full" :class="livello(a.salute).barra" :style="{ width: a.salute + '%' }"></div>
              </div>
            </div>

            <!-- Uso -->
            <div class="col-span-6 md:col-span-3 text-sm">
              <p class="text-slate-700"><span class="font-semibold tabular-nums">{{ a.utenti_attivi_7 }}/{{ a.utenti_totali }}</span> utenti attivi · <span class="font-semibold tabular-nums">{{ a.sinistri_30 }}</span> sinistri</p>
              <p class="text-xs mt-0.5" :class="a.inattiva ? 'text-amber-700 font-semibold' : 'text-slate-400'">
                {{ a.inattiva ? '⚠ ' : '' }}Ultimo uso: {{ quando(a) }}
              </p>
            </div>

            <!-- Collegamenti -->
            <div class="hidden md:flex col-span-2 justify-end gap-3 text-xs">
              <span v-for="c in collegamenti(a)" :key="c.nome" class="flex items-center gap-1" :class="c.ok ? 'text-slate-700' : 'text-slate-300'" :title="c.ok ? `${c.nome}: attivo` : `${c.nome}: da configurare`">
                <span class="w-1.5 h-1.5 rounded-full" :class="c.ok ? 'bg-emerald-500' : 'bg-slate-300'"></span>{{ c.nome }}
              </span>
            </div>
          </li>
        </ul>
      </section>

    </div>
  </SuperadminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'
import BillingBadge from '@/Components/Superadmin/BillingBadge.vue'
import type { BillingState } from '@/types'

interface Agenzia {
  id: number
  nome: string
  stato: BillingState
  mensile_cents: number
  prezzo_mancante: boolean
  utenti_totali: number
  utenti_attivi_7: number
  azioni_7: number
  sinistri_30: number
  ultima_attivita: string | null
  giorni_fermo: number | null
  email: boolean
  whatsapp: boolean
  automazioni: number
  salute: number
  voci_salute: { recente: number; utenti: number; sinistri: number; setup: number }
  inattiva: boolean
}

const props = defineProps<{
  incasso_mensile_cents: number
  obiettivo_cents: number
  agenzie_paganti: number
  agenzie_totali: number
  utenti_attivi_7: number
  utenti_totali: number
  azioni_7: number
  sinistri_30: number
  nuove_agenzie_30: number
  demo_30: number
  inattive: number
  agenzie: Agenzia[]
}>()

const GIORNI_INATTIVA = 14
const PREZZO_STANDARD = 9900

const percentuale = computed(() => props.obiettivo_cents ? Math.round(props.incasso_mensile_cents / props.obiettivo_cents * 100) : 0)
const mancano = computed(() => Math.max(props.obiettivo_cents - props.incasso_mensile_cents, 0))
const agenzieMancanti = computed(() => Math.ceil(mancano.value / PREZZO_STANDARD))

const soloInattive = ref(false)
const righe = computed(() => soloInattive.value ? props.agenzie.filter(a => a.inattiva) : props.agenzie)

const modificaObiettivo = ref(false)
const formObiettivo = useForm({ euro: props.obiettivo_cents / 100 || 1000 })
function salvaObiettivo() {
  formObiettivo.post(route('superadmin.obiettivo'), { preserveScroll: true, onSuccess: () => { modificaObiettivo.value = false } })
}

function livello(s: number) {
  if (s >= 70) return { testo: 'Sana', testoColore: 'text-emerald-700', barra: 'bg-emerald-500', avatar: 'bg-emerald-50 text-emerald-700' }
  if (s >= 40) return { testo: 'Da seguire', testoColore: 'text-amber-700', barra: 'bg-amber-400', avatar: 'bg-amber-50 text-amber-700' }
  return { testo: 'A rischio', testoColore: 'text-red-700', barra: 'bg-red-500', avatar: 'bg-red-50 text-red-700' }
}

function collegamenti(a: Agenzia) {
  return [
    { nome: 'Email', ok: a.email },
    { nome: 'WhatsApp', ok: a.whatsapp },
    { nome: 'Autom.', ok: a.automazioni > 0 },
  ]
}

function iniziali(nome: string) {
  return nome.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
}

const riquadri = computed(() => [
  { titolo: 'Utenti attivi', valore: props.utenti_attivi_7, su: props.utenti_totali, nota: 'hanno usato il programma negli ultimi 7 giorni', punto: 'bg-indigo-500', allarme: false },
  { titolo: 'Sinistri aperti', valore: props.sinistri_30, nota: `ultimi 30 giorni · ${props.azioni_7} azioni in 7 giorni`, punto: 'bg-sky-500', allarme: false },
  { titolo: 'Demo', valore: props.demo_30, nota: `giorni con l'account demo in uso (30 gg) · ${props.nuove_agenzie_30} agenzie nuove`, punto: 'bg-violet-500', allarme: false },
  { titolo: 'Agenzie ferme', valore: props.inattive, nota: `nessun uso da ${GIORNI_INATTIVA} giorni o più`, punto: props.inattive ? 'bg-amber-500' : 'bg-emerald-500', allarme: props.inattive > 0 },
])

function quando(a: Agenzia) {
  if (a.giorni_fermo === null) return 'mai usato'
  if (a.giorni_fermo === 0) return 'oggi'
  if (a.giorni_fermo === 1) return 'ieri'
  return `${a.giorni_fermo} giorni fa`
}

function euro(cents: number) {
  return (cents / 100).toLocaleString('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
}

function apri(id: number) { router.visit(route('superadmin.tenants.edit', id)) }
</script>
