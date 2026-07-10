<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">WhatsApp</h2>
    </template>

    <!-- Attivo: lista chat + conversazione -->
    <div v-if="status === 'active'" class="h-[calc(100vh-3.5rem)] flex flex-col">
      <!-- Flash -->
      <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
        <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 mx-4 mt-4 rounded shrink-0">
          {{ flash.success }}
        </div>
      </Transition>

      <div class="flex items-center justify-between gap-3 px-4 py-2 bg-green-50 border-b border-green-100 shrink-0">
        <p class="text-xs text-green-700">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span>
          Numero collegato — {{ phoneNumber ? `+${phoneNumber}` : '—' }}
        </p>
      </div>

      <div class="flex-1 flex min-h-0">
        <div class="w-80 border-r border-gray-200 bg-white shrink-0 min-h-0">
          <ConversationList
            :conversations="conversations"
            :selected-id="selectedConversationId"
            @select="selectConversation"
          />
        </div>
        <div class="flex-1 min-h-0">
          <MessageThread
            :conversation="selectedConversation"
            :messages="messages"
            :loading="messagesLoading"
            :sending="sending"
            :send-error="sendError"
            @send="sendMessage"
          />
        </div>
      </div>
    </div>

    <!-- Altri stati: card centrata -->
    <div v-else>
      <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
        <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 mx-4 mt-4 rounded">
          {{ flash.success }}
        </div>
      </Transition>

    <div class="py-6 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">

        <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 flex items-center justify-center mb-4">
          <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636L5.636 18.364M12 3a9 9 0 100 18 9 9 0 000-18z"/>
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-800">Nessun numero WhatsApp collegato</h3>
        <p class="text-sm text-gray-500 mt-1">
          Contatta l'amministratore della piattaforma per collegare un numero WhatsApp a questo tenant.
        </p>

      </div>
    </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { useEcho } from '@laravel/echo-vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ConversationList from '@/Components/Whatsapp/ConversationList.vue'
import type { ConversationSummary } from '@/Components/Whatsapp/ConversationList.vue'
import MessageThread from '@/Components/Whatsapp/MessageThread.vue'
import type { Message } from '@/Components/Whatsapp/MessageThread.vue'
import type { PageProps } from '@/types'

interface SessionProps {
  status: 'pending' | 'active' | 'disabled'
  phoneNumber: string | null
}

const props = defineProps<{ session: SessionProps }>()
const page  = usePage<PageProps>()
const flash = computed(() => page.props.flash)

const tenantId = computed(() => page.props.auth.user.tenant_id)

const status      = ref(props.session.status)
const phoneNumber = ref(props.session.phoneNumber)

// ── Conversazioni + messaggi ────────────────────────────────────────────────
const conversations         = ref<ConversationSummary[]>([])
const selectedConversationId = ref<number | null>(null)
const messages               = ref<Message[]>([])
const messagesLoading        = ref(false)
const sending                = ref(false)
const sendError               = ref<string | null>(null)

const selectedConversation = computed(
  () => conversations.value.find((c) => c.id === selectedConversationId.value) ?? null
)

async function loadConversations() {
  const { data } = await axios.get(route('whatsapp.conversations.index'))
  conversations.value = data.conversations
}

async function selectConversation(id: number) {
  selectedConversationId.value = id
  messagesLoading.value = true
  const conv = conversations.value.find((c) => c.id === id)
  if (conv) conv.unreadCount = 0
  try {
    const { data } = await axios.get(route('whatsapp.conversations.messages', id))
    messages.value = data.messages
  } finally {
    messagesLoading.value = false
  }
}

async function sendMessage(body: string) {
  if (!selectedConversationId.value) return
  sending.value = true
  sendError.value = null
  try {
    const { data } = await axios.post(route('whatsapp.conversations.store', selectedConversationId.value), { body })
    messages.value.push(data.message)
    const conv = conversations.value.find((c) => c.id === selectedConversationId.value)
    if (conv) {
      conv.lastMessagePreview = body.slice(0, 200)
      conv.lastMessageAt = data.message.createdAt
    }
  } catch {
    sendError.value = 'Invio non riuscito. Riprova.'
  } finally {
    sending.value = false
  }
}

function upsertConversation(summary: ConversationSummary, isOpen: boolean) {
  const existing = conversations.value.find((c) => c.id === summary.id)
  const merged = { ...summary, unreadCount: isOpen ? 0 : summary.unreadCount }
  if (existing) {
    Object.assign(existing, merged)
  } else {
    conversations.value.unshift(merged)
  }
  conversations.value.sort((a, b) => {
    if (!a.lastMessageAt) return 1
    if (!b.lastMessageAt) return -1
    return new Date(b.lastMessageAt).getTime() - new Date(a.lastMessageAt).getTime()
  })
}

watch(status, (value) => {
  if (value === 'active' && conversations.value.length === 0) {
    loadConversations()
  }
}, { immediate: true })

// ── Realtime ─────────────────────────────────────────────────────────────
if (tenantId.value) {
  const channel = `whatsapp.${tenantId.value}`

  useEcho<{ conversation: ConversationSummary; message: Message }>(channel, '.message', (e) => {
    const isOpen = selectedConversationId.value === e.conversation.id
    upsertConversation(e.conversation, isOpen)
    if (isOpen) {
      messages.value.push(e.message)
    }
  })

  useEcho<{ messageId: number; status: string }>(channel, '.ack', (e) => {
    const m = messages.value.find((msg) => msg.id === e.messageId)
    if (m) m.status = e.status
  })
}
</script>
