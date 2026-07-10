<template>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div class="flex items-center gap-3 px-5 py-3.5 bg-[#075E54]">
      <svg class="w-5 h-5 text-white shrink-0" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>
      <span class="text-white font-semibold text-sm">Comunicazione WhatsApp</span>
    </div>

    <!-- Nessun numero configurato -->
    <div v-if="loaded && !phoneNumber" class="p-6 text-center">
      <p class="text-sm text-gray-500">
        Nessun numero di telefono configurato per questa pratica.
      </p>
      <Link :href="route('pratiche.edit', praticaId)" class="text-sm text-indigo-600 hover:underline mt-1 inline-block">
        Aggiungi il numero del cliente →
      </Link>
    </div>

    <!-- Chat -->
    <div v-else-if="loaded" class="h-[520px]">
      <MessageThread
        :conversation="conversation"
        :messages="messages"
        :loading="messagesLoading"
        :sending="sending"
        :send-error="sendError"
        :disabled="!sessionConnected"
        @send="sendMessage"
      />
    </div>

    <div v-else class="p-6 flex justify-center">
      <svg class="animate-spin w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
      </svg>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Link, usePage } from '@inertiajs/vue3'
import { useEcho } from '@laravel/echo-vue'
import MessageThread from './MessageThread.vue'
import type { Message } from './MessageThread.vue'
import type { ConversationSummary } from './ConversationList.vue'
import type { PageProps } from '@/types'

const props = defineProps<{ praticaId: number }>()

const page = usePage<PageProps>()
const tenantId = page.props.auth.user.tenant_id

const loaded            = ref(false)
const phoneNumber        = ref<string | null>(null)
const sessionConnected    = ref(false)
const conversation        = ref<ConversationSummary | null>(null)
const messages             = ref<Message[]>([])
const messagesLoading      = ref(false)
const sending               = ref(false)
const sendError              = ref<string | null>(null)

async function load() {
  const { data } = await axios.get(route('pratiche.whatsapp', props.praticaId))
  phoneNumber.value = data.phoneNumber
  sessionConnected.value = data.sessionConnected
  conversation.value = data.conversation
  loaded.value = true

  if (conversation.value) {
    messagesLoading.value = true
    try {
      const res = await axios.get(route('whatsapp.conversations.messages', conversation.value.id))
      messages.value = res.data.messages
    } finally {
      messagesLoading.value = false
    }
  }
}

async function sendMessage(body: string) {
  if (!conversation.value) return
  sending.value = true
  sendError.value = null
  try {
    const { data } = await axios.post(route('whatsapp.conversations.store', conversation.value.id), { body })
    messages.value.push(data.message)
  } catch {
    sendError.value = 'Invio non riuscito. Riprova.'
  } finally {
    sending.value = false
  }
}

onMounted(load)

if (tenantId) {
  const channel = `whatsapp.${tenantId}`

  useEcho<{ conversation: ConversationSummary; message: Message }>(channel, '.message', (e) => {
    if (!conversation.value || e.conversation.id !== conversation.value.id) return
    conversation.value = { ...conversation.value, ...e.conversation, unreadCount: 0 }
    messages.value.push(e.message)
  })

  useEcho<{ messageId: number; status: string }>(channel, '.ack', (e) => {
    const m = messages.value.find((msg) => msg.id === e.messageId)
    if (m) m.status = e.status
  })
}
</script>
