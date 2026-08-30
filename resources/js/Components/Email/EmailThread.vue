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
      <div ref="scrollEl" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-slate-50">
        <div v-if="loading" class="flex justify-center py-8">
          <svg class="animate-spin w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
        </div>

        <div v-else-if="messages.length === 0" class="flex justify-center py-8 text-xs text-gray-500">
          Nessun messaggio ancora.
        </div>

        <div v-for="m in messages" :key="m.id" class="flex" :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'">
          <div
            class="rounded-xl px-4 py-3 max-w-[80%] shadow-sm bg-white"
            :class="m.direction === 'outbound' ? 'rounded-tr-sm border border-indigo-100' : 'rounded-tl-sm border border-gray-100'"
          >
            <p class="text-xs font-semibold text-gray-500 mb-1 truncate">{{ m.subject || '(nessun oggetto)' }}</p>
            <div class="text-sm text-gray-800 leading-relaxed prose-sm max-w-none" v-html="m.bodyHtml || ''" />

            <div v-if="m.attachments.length" class="flex flex-wrap gap-2 mt-2">
              <button
                v-for="a in m.attachments"
                :key="a.id"
                type="button"
                @click="$emit('download', a.id)"
                class="inline-flex items-center gap-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-full px-3 py-1 transition"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ a.filename }}
              </button>
            </div>

            <div class="flex items-center justify-end gap-1 mt-2">
              <span v-if="m.status === 'failed'" class="text-[10px] text-red-500 font-medium">invio fallito</span>
              <span class="text-[10px] text-gray-400">{{ formatTime(m.createdAt) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Risposta -->
      <p v-if="disabled" class="px-3 py-2 text-xs text-gray-500 bg-[#f0f0f0] border-t border-gray-200 shrink-0">
        Nessuna casella email collegata per questo tenant — configurala da Superadmin per rispondere.
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
            <input ref="fileInput" type="file" multiple class="text-xs text-gray-500" />
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
  </div>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import type { ThreadSummary } from './ThreadList.vue'

export interface EmailMessageAttachment {
  id: number
  filename: string
  mimeType: string | null
  size: number | null
}

export interface EmailMessageItem {
  id: number
  direction: 'inbound' | 'outbound'
  fromAddress: string
  fromName: string | null
  subject: string | null
  bodyHtml: string | null
  status: string
  createdAt: string
  attachments: EmailMessageAttachment[]
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
const scrollEl = ref<HTMLElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

function submit() {
  const body = draft.value.trim()
  if (!body || props.sending) return
  const files = fileInput.value?.files ? Array.from(fileInput.value.files) : []
  emit('send', body, files)
  draft.value = ''
  if (fileInput.value) fileInput.value.value = ''
}

function scrollToBottom() {
  nextTick(() => {
    if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight
  })
}

watch(() => props.messages.length, scrollToBottom)
watch(() => props.thread?.id, scrollToBottom)

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}
</script>
