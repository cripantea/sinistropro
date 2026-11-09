<template>
  <div class="flex flex-col h-full">
    <!-- Nessun thread selezionato -->
    <div v-if="!thread" class="flex-1 flex items-center justify-center text-sm text-gray-400">
      Seleziona un'email per iniziare.
    </div>

    <template v-else>
      <!-- Header -->
      <div class="flex items-center gap-3 px-4 py-3 bg-indigo-600 shrink-0">
        <div class="w-9 h-9 rounded-full bg-white/20 text-white flex items-center justify-center text-sm font-semibold shrink-0">
          {{ initials(thread.counterpartName || thread.counterpartEmail) }}
        </div>
        <div class="min-w-0">
          <p class="text-white text-sm font-semibold truncate">{{ thread.counterpartName || thread.counterpartEmail }}</p>
          <p class="text-indigo-200 text-xs truncate">{{ thread.counterpartEmail }}</p>
        </div>
      </div>

      <!-- Messaggi -->
      <div ref="scrollEl" class="flex-1 overflow-y-auto px-3 sm:px-6 py-4 space-y-3 bg-slate-50">
        <div v-if="loading" class="flex justify-center py-8">
          <svg class="animate-spin w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
        </div>

        <div v-else-if="messages.length === 0" class="flex justify-center py-8 text-xs text-gray-500">
          Nessun messaggio ancora.
        </div>

        <template v-for="(m, i) in messages" :key="m.id">
          <div v-if="nuovoGiorno(messages, i)" class="flex justify-center">
            <span class="text-[11px] font-medium text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-0.5 first-letter:uppercase">{{ giorno(m.createdAt) }}</span>
          </div>

          <article class="bg-white rounded-xl border shadow-sm overflow-hidden"
            :class="m.direction === 'outbound' ? 'border-indigo-200 ml-6 sm:ml-12' : 'border-gray-200 mr-6 sm:mr-12'">
            <!-- Intestazione: chi, a chi, quando -->
            <header class="flex items-start gap-3 px-4 py-3 border-b border-gray-100" :class="m.direction === 'outbound' ? 'bg-indigo-50/60' : ''">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold shrink-0"
                :class="m.direction === 'outbound' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'">
                {{ initials(m.fromName || m.fromAddress) }}
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm text-gray-900 truncate">
                  <span class="font-semibold">{{ m.direction === 'outbound' ? 'Tu' : (m.fromName || m.fromAddress) }}</span>
                  <span v-if="m.direction !== 'outbound' && m.fromName" class="text-gray-500"> &lt;{{ m.fromAddress }}&gt;</span>
                </p>
                <p v-if="m.toAddresses?.length" class="text-xs text-gray-500 truncate">a: {{ m.toAddresses.join(', ') }}<template v-if="m.ccAddresses?.length"> · cc: {{ m.ccAddresses.join(', ') }}</template></p>
                <p class="text-xs font-medium text-gray-700 mt-0.5 break-words">{{ m.subject || '(nessun oggetto)' }}</p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-xs text-gray-500">{{ ora(m.createdAt) }}</p>
                <p v-if="m.status === 'failed'" class="text-[11px] text-red-600 font-medium">invio fallito</p>
              </div>
            </header>

            <!-- Testo -->
            <div class="px-4 py-3">
              <div v-if="m.bodyHtml" class="email-body text-sm text-gray-800 leading-relaxed break-words" v-html="m.bodyHtml" />
              <p v-else-if="m.bodyText" class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap break-words">{{ m.bodyText }}</p>
              <p v-else class="text-sm text-gray-400 italic">Email senza testo.</p>
            </div>

            <!-- Allegati -->
            <footer v-if="allegati(m).length" class="px-4 pb-4">
              <p class="text-xs font-semibold text-gray-500 mb-2">{{ allegati(m).length }} {{ allegati(m).length === 1 ? 'allegato' : 'allegati' }}</p>
              <div class="flex flex-wrap gap-2">
                <template v-for="a in allegati(m)" :key="a.id">
                  <!-- Foto: miniatura, clic = grande -->
                  <button v-if="a.preview === 'image' && a.viewUrl && !rotte[a.id]" type="button" :title="a.filename"
                    class="group relative w-28 h-28 rounded-lg overflow-hidden border border-gray-200 bg-gray-50"
                    @click="foto = { src: a.viewUrl, titolo: a.filename }">
                    <img :src="a.viewUrl" :alt="a.filename" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition" @error="rotte[a.id] = true" />
                    <span class="absolute inset-x-0 bottom-0 bg-black/55 text-white text-[10px] px-1.5 py-0.5 truncate">{{ a.filename }}</span>
                  </button>

                  <!-- PDF e altri file -->
                  <div v-else class="flex items-center gap-3 w-64 max-w-full rounded-lg border border-gray-200 p-2.5 bg-white">
                    <span class="w-9 h-11 rounded-md flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                      :class="a.preview === 'pdf' ? 'bg-red-500' : a.preview === 'image' ? 'bg-sky-500' : 'bg-slate-500'">{{ a.preview === 'pdf' ? 'PDF' : estensione(a.filename) }}</span>
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-medium text-gray-800 truncate" :title="a.filename">{{ a.filename }}</p>
                      <div class="flex items-center gap-3 text-xs mt-0.5">
                        <span class="text-gray-400">{{ dimensione(a.size) }}</span>
                        <a v-if="a.preview === 'pdf' && a.viewUrl" :href="a.viewUrl" target="_blank" rel="noopener" class="font-semibold text-indigo-600 hover:underline">Apri</a>
                        <button type="button" class="font-semibold text-indigo-600 hover:underline" @click="$emit('download', a.id)">Scarica</button>
                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </footer>
          </article>
        </template>
      </div>

      <!-- Risposta -->
      <p v-if="disabled" class="px-3 py-2 text-xs text-gray-500 bg-[#f0f0f0] border-t border-gray-200 shrink-0">
        Nessuna casella email collegata: collegala dal menu utente → Casella email per rispondere.
      </p>
      <template v-else>
        <p v-if="sendError" class="px-3 pt-2 text-xs text-red-600 bg-[#f0f0f0] shrink-0">{{ sendError }}</p>
        <form @submit.prevent="submit" class="flex flex-col gap-2 px-3 py-2 bg-[#f0f0f0] border-t border-gray-200 shrink-0">
          <textarea
            v-model="draft"
            rows="3"
            placeholder="Scrivi una risposta…"
            class="text-sm bg-white border border-gray-200 rounded-lg px-3 py-2 resize-none focus:ring-2 focus:ring-indigo-500 outline-none"
          />
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
              <input ref="fileInput" type="file" multiple class="hidden" @change="aggiornaFile" />
              <button type="button" @click="fileInput?.click()" class="shrink-0 inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-lg hover:bg-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                Allega
              </button>
              <span v-if="fileScelti.length" class="text-xs text-gray-500 truncate" :title="fileScelti.join(', ')">{{ fileScelti.length === 1 ? fileScelti[0] : `${fileScelti.length} file` }}</span>
            </div>
            <button
              type="submit"
              :disabled="!draft.trim() || sending"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition"
              :class="draft.trim() && !sending ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-300 text-gray-400 cursor-not-allowed'"
            >
              {{ sending ? 'Invio...' : 'Rispondi' }}
            </button>
          </div>
        </form>
      </template>
    </template>

    <AnteprimaFoto :src="foto?.src ?? null" :titolo="foto?.titolo" @close="foto = null" />
  </div>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import type { ThreadSummary } from './ThreadList.vue'
import AnteprimaFoto from '@/Components/AnteprimaFoto.vue'
import { dimensione, estensione, giorno, nuovoGiorno, ora } from '@/chat'

export interface EmailMessageAttachment {
  id: number
  filename: string
  mimeType: string | null
  size: number | null
  /** immagine già mostrata dentro il testo dell'email */
  inline?: boolean
  preview?: 'image' | 'pdf' | null
  viewUrl?: string | null
}

export interface EmailMessageItem {
  id: number
  direction: 'inbound' | 'outbound'
  fromAddress: string
  fromName: string | null
  subject: string | null
  toAddresses?: string[] | null
  ccAddresses?: string[] | null
  bodyHtml: string | null
  bodyText?: string | null
  status: string
  createdAt: string
  attachments: EmailMessageAttachment[]
  /** true quando il corpo non è stato inviato via realtime (email troppo grande) */
  truncated?: boolean
}

const props = defineProps<{
  thread: ThreadSummary | null
  messages: EmailMessageItem[]
  loading: boolean
  sending: boolean
  sendError: string | null
  disabled?: boolean
}>()

const emit = defineEmits<{ send: [body: string, files: File[]]; download: [attachmentId: number] }>()

const draft = ref('')
const foto = ref<{ src: string; titolo: string } | null>(null)
const rotte = ref<Record<number, boolean>>({})
const fileScelti = ref<string[]>([])

function aggiornaFile() {
  fileScelti.value = fileInput.value?.files ? Array.from(fileInput.value.files).map((f) => f.name) : []
}

/** Le immagini già dentro il testo non si ripetono nella lista allegati. */
const allegati = (m: EmailMessageItem) => m.attachments.filter((a) => !a.inline)
const scrollEl = ref<HTMLElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

function submit() {
  const body = draft.value.trim()
  if (!body || props.sending) return
  const files = fileInput.value?.files ? Array.from(fileInput.value.files) : []
  emit('send', body, files)
  draft.value = ''
  if (fileInput.value) fileInput.value.value = ''
  fileScelti.value = []
}

function scrollToBottom() {
  nextTick(() => {
    if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight
  })
}

watch(() => props.messages.length, scrollToBottom)
watch(() => props.thread?.id, scrollToBottom)

function initials(name: string): string {
  return (name || '?').split(/[\s@.]+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase()
}

</script>

<style scoped>
/* Email impaginate (tabelle, citazioni, immagini) leggibili dentro la scheda, senza uscire dai bordi. */
.email-body :deep(img) { max-width: 100%; height: auto; display: inline-block; }
.email-body :deep(table) { max-width: 100%; border-collapse: collapse; }
.email-body :deep(td), .email-body :deep(th) { padding: 2px 4px; vertical-align: top; }
.email-body :deep(p) { margin: 0 0 .6em; }
.email-body :deep(a) { color: #4f46e5; text-decoration: underline; }
.email-body :deep(blockquote) { border-left: 3px solid #e5e7eb; margin: .6em 0; padding-left: .8em; color: #6b7280; }
.email-body :deep(ul) { list-style: disc; padding-left: 1.4em; }
.email-body :deep(ol) { list-style: decimal; padding-left: 1.4em; }
.email-body :deep(h1), .email-body :deep(h2), .email-body :deep(h3) { font-weight: 600; margin: .6em 0 .3em; }
.email-body :deep(pre) { white-space: pre-wrap; }
.email-body { overflow-x: auto; }
</style>
