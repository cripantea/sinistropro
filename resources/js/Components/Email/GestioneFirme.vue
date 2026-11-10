<template>
  <div class="fixed inset-0 bg-black/30 flex items-center justify-center z-[60] px-4" @click.self="emit('close')">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-xl max-h-[90vh] flex flex-col text-sm">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Firme email</h3>
        <button type="button" @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
      </div>

      <div class="overflow-y-auto px-5 py-4 space-y-3">
        <p v-if="!stato.firme.length && !modifica" class="text-gray-500">Nessuna firma. Creane una: potrai sceglierla ogni volta che scrivi.</p>

        <div v-for="f in stato.firme" :key="f.id" class="rounded-lg border p-3" :class="stato.preferita === f.id ? 'border-indigo-300 bg-indigo-50/40' : 'border-gray-200'">
          <template v-if="modifica?.id === f.id">
            <FormFirma v-model="modifica" :puo-agenzia="false" :errori="errori" :salvando="salvando" @salva="salva" @annulla="modifica = null" />
          </template>
          <template v-else>
            <div class="flex items-center justify-between gap-2">
              <p class="font-semibold text-gray-800 truncate">
                {{ f.nome }}
                <span v-if="f.agenzia" class="ml-1 text-[11px] font-medium text-gray-500 bg-gray-100 rounded px-1.5 py-0.5">di tutta l'agenzia</span>
                <span v-if="stato.preferita === f.id" class="ml-1 text-[11px] font-medium text-indigo-700 bg-indigo-100 rounded px-1.5 py-0.5">la usi di solito</span>
                <span v-if="stato.predefinitaAgenzia === f.id" class="ml-1 text-[11px] font-medium text-emerald-700 bg-emerald-100 rounded px-1.5 py-0.5">predefinita per tutti</span>
              </p>
              <div class="flex items-center gap-3 text-xs shrink-0">
                <button v-if="stato.preferita !== f.id" type="button" @click="preferita(f.id)" class="text-indigo-600 hover:underline">Usa di solito</button>
                <button v-else type="button" @click="preferita(null)" class="text-gray-500 hover:underline">Non proporla</button>
                <button v-if="f.agenzia && stato.puoAgenzia && stato.predefinitaAgenzia !== f.id" type="button" @click="predefinita(f.id)" class="text-emerald-700 hover:underline" title="Proposta di base a tutti i colleghi e usata nelle email automatiche">Predefinita per tutti</button>
                <template v-if="f.modificabile">
                  <button type="button" @click="modifica = { id: f.id, nome: f.nome, testo: f.testo, agenzia: f.agenzia, immagineUrl: f.immagine }" class="text-gray-600 hover:underline">Modifica</button>
                  <button type="button" @click="elimina(f.id, f.nome)" class="text-red-600 hover:underline">Elimina</button>
                </template>
              </div>
            </div>
            <p v-if="f.testo" class="mt-1.5 text-gray-600 whitespace-pre-line text-xs">{{ f.testo }}</p>
            <img v-if="f.immagine" :src="f.immagine" :alt="f.nome" class="mt-2 max-h-28 w-auto rounded border border-gray-100" />
          </template>
        </div>

        <div v-if="modifica && !modifica.id" class="rounded-lg border border-indigo-200 p-3">
          <FormFirma v-model="modifica" :puo-agenzia="stato.puoAgenzia" :errori="errori" :salvando="salvando" @salva="salva" @annulla="modifica = null" />
        </div>
        <button v-else-if="!modifica" type="button" @click="nuova" class="w-full rounded-lg border border-dashed border-gray-300 py-2.5 text-indigo-600 font-semibold hover:bg-gray-50">+ Nuova firma</button>
        <p v-if="erroreGenerale" class="text-xs text-red-600">{{ erroreGenerale }}</p>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
export interface Firma { id: number; nome: string; testo: string; immagine: string | null; agenzia: boolean; modificabile: boolean }
export interface StatoFirme { firme: Firma[]; preferita: number | null; predefinitaAgenzia?: number | null; puoAgenzia: boolean }
export interface BozzaFirma { id?: number; nome: string; testo: string; agenzia: boolean; immagine?: File | null; immagineUrl?: string | null; togliImmagine?: boolean }
</script>

<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import FormFirma from '@/Components/Email/FormFirma.vue'

const props = defineProps<{ stato: StatoFirme }>()
const emit = defineEmits<{ close: []; creata: [id: number] }>()

const modifica = ref<BozzaFirma | null>(null)
const errori = ref<Record<string, string>>({})
const erroreGenerale = ref<string | null>(null)
const salvando = ref(false)

function aggiorna(dati: StatoFirme) {
  props.stato.firme = dati.firme
  props.stato.preferita = dati.preferita
  props.stato.predefinitaAgenzia = dati.predefinitaAgenzia
  props.stato.puoAgenzia = dati.puoAgenzia
}

async function predefinita(id: number | null) {
  aggiorna((await axios.post(route('email.firme.predefinita'), { firma_id: id })).data)
}

function nuova() {
  errori.value = {}
  modifica.value = { nome: '', testo: '', agenzia: false }
}

async function salva() {
  if (!modifica.value || salvando.value) return
  salvando.value = true
  errori.value = {}
  erroreGenerale.value = null
  try {
    const b = modifica.value
    const fd = new FormData()
    fd.append('nome', b.nome)
    fd.append('testo', b.testo ?? '')
    fd.append('agenzia', b.agenzia ? '1' : '0')
    if (b.immagine) fd.append('immagine', b.immagine)
    if (b.togliImmagine) fd.append('togli_immagine', '1')
    if (!b.id && !props.stato.firme.length) fd.append('preferita', '1')
    const { data } = await axios.post(b.id ? route('email.firme.update-file', b.id) : route('email.firme.store'), fd)
    aggiorna(data)
    if (data.creata) emit('creata', data.creata)
    modifica.value = null
  } catch (e: any) {
    const err = e?.response?.data
    errori.value = Object.fromEntries(Object.entries(err?.errors ?? {}).map(([k, v]) => [k, (v as string[])[0]]))
    if (!err?.errors) erroreGenerale.value = err?.message ?? 'Salvataggio non riuscito.'
  } finally {
    salvando.value = false
  }
}

async function elimina(id: number, nome: string) {
  if (!confirm(`Eliminare la firma «${nome}»?`)) return
  try {
    aggiorna((await axios.delete(route('email.firme.destroy', id))).data)
  } catch (e: any) {
    erroreGenerale.value = e?.response?.data?.message ?? 'Eliminazione non riuscita.'
  }
}

async function preferita(id: number | null) {
  aggiorna((await axios.post(route('email.firme.preferita'), { firma_id: id })).data)
}
</script>
