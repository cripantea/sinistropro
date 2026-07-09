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

        <div v-for="m in messages" :key="m.id" class="flex" :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'">
          <div
            class="rounded-xl px-3 py-2 max-w-[75%] shadow-sm"
            :class="m.direction === 'outbound' ? 'bg-[#dcf8c6] rounded-tr-sm' : 'bg-white rounded-tl-sm'"
          >
            <p class="text-sm text-gray-800 whitespace-pre-wrap break-words leading-relaxed">
              {{ m.body || (m.mediaType ? `[${m.mediaType}]` : '') }}
            </p>
            <div class="flex items-center justify-end gap-1 mt-1">
              <span class="text-[10px] text-gray-400">{{ formatTime(m.createdAt) }}</span>
              <StatusTicks v-if="m.direction === 'outbound'" :status="m.status" />
            </div>
          </div>
        </div>
      </div>

      <!-- Input -->
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
  </div>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import StatusTicks from './StatusTicks.vue'
import type { ConversationSummary } from './ConversationList.vue'

export interface Message {
  id: number
  direction: 'inbound' | 'outbound'
  body: string | null
  mediaType: string | null
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
}>()

const emit = defineEmits<{ send: [body: string] }>()

const draft = ref('')
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

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })
}
</script>
