<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">WhatsApp</h2>
    </template>

    <!-- Flash -->
    <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
      <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 mx-4 mt-4 rounded">
        {{ flash.success }}
      </div>
    </Transition>

    <!-- Connesso: lista chat + conversazione -->
    <div v-if="status === 'connected'" class="h-[calc(100vh-3.5rem)] flex flex-col">
      <div class="flex items-center justify-between gap-3 px-4 py-2 bg-green-50 border-b border-green-100 shrink-0">
        <p class="text-xs text-green-700">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span>
          Connesso — {{ phoneNumber ? `+${phoneNumber}` : '—' }}
        </p>
        <button
          v-if="isTenantAdmin"
          @click="stop"
          :disabled="processing"
          class="text-xs text-red-600 hover:underline disabled:opacity-60"
        >
          Disconnetti
        </button>
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
    <div v-else class="py-6 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">

        <!-- QR da scansionare -->
        <div v-if="status === 'qr' && qrCode">
          <h3 class="text-lg font-semibold text-gray-800 mb-1">Scansiona il QR code</h3>
          <p class="text-sm text-gray-500 mb-4">
            Apri WhatsApp sul telefono del numero da collegare → Impostazioni → Dispositivi collegati → Collega un dispositivo.
          </p>
          <img :src="qrCode" alt="QR code WhatsApp" class="mx-auto rounded-lg border border-gray-200 w-56 h-56 object-contain" />
          <p class="text-xs text-gray-400 mt-4">Il codice si aggiorna automaticamente se scade.</p>
        </div>

        <!-- Avvio in corso -->
        <div v-else-if="status === 'starting'">
          <svg class="animate-spin w-8 h-8 mx-auto text-indigo-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          <p class="text-sm text-gray-500 mt-3">Avvio della connessione in corso…</p>
        </div>

        <!-- Disconnesso -->
        <div v-else>
          <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636L5.636 18.364M12 3a9 9 0 100 18 9 9 0 000-18z"/>
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-800">WhatsApp non connesso</h3>

          <template v-if="isTenantAdmin">
            <p class="text-sm text-gray-500 mt-1">Collega il numero WhatsApp del tenant per iniziare a comunicare con i clienti.</p>
            <button
              @click="start"
              :disabled="processing"
              class="mt-6 bg-indigo-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60"
            >
              Connetti WhatsApp
            </button>
          </template>
          <p v-else class="text-sm text-gray-500 mt-1">
            In attesa che un amministratore del tenant collechi il numero WhatsApp.
          </p>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import { useEcho } from '@laravel/echo-vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ConversationList from '@/Components/Whatsapp/ConversationList.vue'
import type { ConversationSummary } from '@/Components/Whatsapp/ConversationList.vue'
import MessageThread from '@/Components/Whatsapp/MessageThread.vue'
import type { Message } from '@/Components/Whatsapp/MessageThread.vue'
import type { PageProps } from '@/types'

interface SessionProps {
  status: 'disconnected' | 'starting' | 'qr' | 'connected'
  phoneNumber: string | null
  qrCode: string | null
}

const props = defineProps<{ session: SessionProps }>()
const page  = usePage<PageProps>()
const flash = computed(() => page.props.flash)

const isTenantAdmin = computed(() => page.props.auth.user.role === 'tenant-admin')
const tenantId       = computed(() => page.props.auth.user.tenant_id)

const status      = ref(props.session.status)
const phoneNumber = ref(props.session.phoneNumber)
const qrCode      = ref(props.session.qrCode)
const processing  = ref(false)

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
  if (value === 'connected' && conversations.value.length === 0) {
    loadConversations()
  }
}, { immediate: true })

// ── Realtime ─────────────────────────────────────────────────────────────
if (tenantId.value) {
  const channel = `whatsapp.${tenantId.value}`

  useEcho<{ qrCode: string | null }>(channel, '.qr', (e) => {
    status.value = 'qr'
    qrCode.value = e.qrCode
  })

  useEcho<{ phoneNumber: string | null }>(channel, '.ready', (e) => {
    status.value = 'connected'
    phoneNumber.value = e.phoneNumber
    qrCode.value = null
  })

  useEcho<{ state: string | null }>(channel, '.state', (e) => {
    if (e.state === 'CONNECTED') {
      status.value = 'connected'
    } else if (['DISCONNECTED', 'CONFLICT', 'UNPAIRED', 'UNLAUNCHED', 'TIMEOUT'].includes(e.state ?? '')) {
      status.value = 'disconnected'
      qrCode.value = null
    }
  })

  useEcho(channel, '.stopped', () => {
    status.value = 'disconnected'
    phoneNumber.value = null
    qrCode.value = null
    conversations.value = []
    selectedConversationId.value = null
    messages.value = []
  })

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

function start() {
  processing.value = true
  status.value = 'starting'
  router.post(route('whatsapp.start'), {}, {
    preserveScroll: true,
    onFinish: () => { processing.value = false },
  })
}

function stop() {
  processing.value = true
  router.post(route('whatsapp.stop'), {}, {
    preserveScroll: true,
    onFinish: () => { processing.value = false },
  })
}
</script>
