<template>
  <div class="flex flex-col h-full">
    <!-- Nessuna conversazione selezionata -->
    <div v-if="!conversation" class="flex-1 flex items-center justify-center text-sm text-gray-400">
      Seleziona una chat per iniziare.
    </div>

    <template v-else>
      <!-- Header -->
      <div class="flex items-center gap-3 px-4 py-3 bg-[#075E54] shrink-0">
        <div class="w-9 h-9 rounded-full bg-white/20 text-white flex items-center justify-center text-sm font-semibold shrink-0">
          {{ initials(conversation.contactName || conversation.phoneNumber) }}
        </div>
        <div class="min-w-0">
          <p class="text-white text-sm font-semibold truncate">{{ conversation.contactName || `+${conversation.phoneNumber}` }}</p>
          <p class="text-green-200 text-xs truncate">+{{ conversation.phoneNumber }}</p>
        </div>
      </div>

      <!-- Messaggi -->
      <div
        ref="scrollEl"
        class="flex-1 overflow-y-auto px-4 py-4 space-y-2"
        style="background-color:#e5ddd5; background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAABESURBVDiNY2AYBYMHMDIy/mdiYPiPxGZiYGD4TwIGBGBgYGD4TwIGJGA0BsYeGAVDGIyKIQxGxRAGo2IIg1ExBAAAyv4FxPMAAAAASUVORK5CYII=')"
      >
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
          <div v-if="nuovoGiorno(messages, i)" class="flex justify-center py-1">
            <span class="text-[11px] font-medium text-gray-600 bg-white/90 rounded-lg px-3 py-1 shadow-sm first-letter:uppercase">{{ giorno(m.createdAt) }}</span>
          </div>

          <div class="flex" :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'">
            <div
              class="rounded-xl shadow-sm max-w-[75%] min-w-0"
              :class="[
                m.mediaType === 'sticker' ? 'bg-transparent shadow-none' : (m.direction === 'outbound' ? 'bg-[#dcf8c6] rounded-tr-sm' : 'bg-white rounded-tl-sm'),
                conMedia(m) ? 'p-1' : 'px-3 py-2',
              ]"
            >
              <!-- Foto e sticker: clic = grande -->
              <button v-if="isFoto(m) && m.mediaUrl && !failed[m.id]" type="button" class="block" @click="foto = { src: m.mediaUrl, titolo: titoloFoto(m) }">
                <img :src="m.mediaUrl" :alt="titoloFoto(m)" loading="lazy"
                  :class="m.mediaType === 'sticker' ? 'w-32 h-32 object-contain' : 'max-h-80 max-w-full rounded-lg object-cover'"
                  @error="failed[m.id] = true" />
              </button>

              <!-- Video -->
              <video v-else-if="m.mediaType === 'video' && m.mediaUrl && !failed[m.id]" :src="m.mediaUrl" controls preload="metadata"
                class="max-h-80 max-w-full rounded-lg bg-black" @error="failed[m.id] = true" />

              <!-- Vocale -->
              <audio v-else-if="m.mediaType === 'audio' && m.mediaUrl && !failed[m.id]" :src="m.mediaUrl" controls preload="none"
                class="w-64 max-w-full h-10" @error="failed[m.id] = true" />

              <!-- Documento -->
              <div v-else-if="m.mediaType === 'document' && m.mediaUrl" class="flex items-center gap-3 rounded-lg p-2 min-w-[14rem]"
                :class="m.direction === 'outbound' ? 'bg-[#cfe9ba]' : 'bg-gray-100'">
                <span class="w-10 h-12 rounded-md flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                  :class="isPdf(m) ? 'bg-red-500' : 'bg-slate-500'">{{ isPdf(m) ? 'PDF' : estensione(m.mediaFilename) }}</span>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-gray-800 truncate">{{ m.mediaFilename || 'Documento' }}</p>
                  <div class="flex gap-3 mt-0.5 text-xs font-semibold">
                    <a v-if="isPdf(m)" :href="m.mediaUrl" target="_blank" rel="noopener" class="text-[#075E54] hover:underline">Apri</a>
                    <a :href="m.mediaUrl" :download="m.mediaFilename || ''" class="text-[#075E54] hover:underline">Scarica</a>
                  </div>
                </div>
              </div>

              <!-- Allegato non scaricabile (scaduto, o messaggio vecchio senza riferimento) -->
              <p v-else-if="m.mediaType && apribile(m)" class="text-xs text-gray-500 italic">
                {{ etichetta(m.mediaType) }} non più disponibile (WhatsApp tiene gli allegati circa 30 giorni).
              </p>
              <p v-else-if="m.mediaType && !m.body" class="text-sm text-gray-600">{{ etichetta(m.mediaType) }}</p>

              <p v-if="m.body" class="text-sm text-gray-800 whitespace-pre-wrap break-words leading-relaxed"
                :class="conMedia(m) ? 'px-2 pt-1' : ''">{{ m.body }}</p>

              <div class="flex items-center justify-end gap-1 mt-1" :class="conMedia(m) ? 'px-2 pb-1' : ''">
                <span v-if="m.userName && m.direction === 'outbound'" class="text-[10px] text-gray-500">{{ m.userName }} ·</span>
                <span v-if="m.source === 'echo'" class="text-[10px] text-gray-500 italic">inviato da telefono</span>
                <span class="text-[10px] text-gray-400">{{ ora(m.createdAt) }}</span>
                <StatusTicks v-if="m.direction === 'outbound'" :status="m.status" />
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Input -->
      <p v-if="disabled" class="px-3 py-2 text-xs text-gray-500 bg-[#f0f0f0] border-t border-gray-200 shrink-0">
        WhatsApp non connesso — <Link :href="route('whatsapp.index')" class="text-indigo-600 hover:underline">collega il numero</Link> per scrivere.
      </p>
      <template v-else>
        <p v-if="sendError" class="px-3 pt-2 text-xs text-red-600 bg-[#f0f0f0] shrink-0">{{ sendError }}</p>
        <form @submit.prevent="submit" class="flex items-end gap-2 px-3 py-2 bg-[#f0f0f0] border-t border-gray-200 shrink-0">
          <textarea
            v-model="draft"
            rows="1"
            placeholder="Scrivi un messaggio…"
            class="flex-1 text-sm bg-white border-0 rounded-xl px-3 py-2 resize-none focus:ring-2 focus:ring-green-500 outline-none"
            @keydown.enter.exact.prevent="submit"
          />
          <button
            type="submit"
            :disabled="!draft.trim() || sending"
            class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition"
            :class="draft.trim() && !sending ? 'bg-[#25D366] hover:bg-[#20bf59] text-white shadow' : 'bg-gray-300 text-gray-400 cursor-not-allowed'"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
              <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
          </button>
        </form>
      </template>
    </template>

    <AnteprimaFoto :src="foto?.src ?? null" :titolo="foto?.titolo" @close="foto = null" />
  </div>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import StatusTicks from './StatusTicks.vue'
import AnteprimaFoto from '@/Components/AnteprimaFoto.vue'
import { estensione, giorno, nuovoGiorno, ora } from '@/chat'
import type { ConversationSummary } from './ConversationList.vue'

export interface Message {
  id: number
  direction: 'inbound' | 'outbound'
  source?: 'api' | 'echo' | 'history'
  body: string | null
  mediaType: string | null
  mediaUrl?: string | null
  mediaMime?: string | null
  mediaFilename?: string | null
  status: string
  userName: string | null
  createdAt: string
}

const props = defineProps<{
  conversation: ConversationSummary | null
  messages: Message[]
  loading: boolean
  sending: boolean
  sendError: string | null
  disabled?: boolean
}>()

const emit = defineEmits<{ send: [body: string] }>()

const draft = ref('')
const failed = ref<Record<number, boolean>>({})
const foto = ref<{ src: string; titolo: string } | null>(null)

const isFoto = (m: Message) => m.mediaType === 'image' || m.mediaType === 'sticker'
const isPdf = (m: Message) => m.mediaMime === 'application/pdf' || /\.pdf$/i.test(m.mediaFilename ?? '')
/** Foto o video visibili: bolla senza margini interni, il testo sotto ha i suoi. */
const conMedia = (m: Message) => (isFoto(m) || m.mediaType === 'video') && !!m.mediaUrl && !failed.value[m.id]
const apribile = (m: Message) => ['image', 'sticker', 'video', 'audio', 'document'].includes(m.mediaType ?? '')
const titoloFoto = (m: Message) => m.body || `Foto del ${new Date(m.createdAt).toLocaleString('it-IT')}`

function etichetta(tipo: string): string {
  return ({ image: 'Foto', sticker: 'Sticker', video: 'Video', audio: 'Messaggio vocale', document: 'Documento', location: '📍 Posizione', contacts: '👤 Contatto' } as Record<string, string>)[tipo] ?? 'Allegato'
}
const scrollEl = ref<HTMLElement | null>(null)

function submit() {
  const body = draft.value.trim()
  if (!body || props.sending) return
  emit('send', body)
  draft.value = ''
}

function scrollToBottom() {
  nextTick(() => {
    if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight
  })
}

watch(() => props.messages.length, scrollToBottom)
watch(() => props.conversation?.id, scrollToBottom)

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
}

</script>
