<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">Email</h2>
    </template>

    <div v-if="!emailConfigured" class="py-6 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
        <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 flex items-center justify-center mb-4">
          <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-800">Nessuna casella email collegata</h3>
        <p class="text-sm text-gray-500 mt-1">Contatta l'amministratore per configurare la casella email di questo tenant.</p>
      </div>
    </div>

    <div v-else class="h-[calc(100vh-3.5rem)] flex">
      <div class="w-80 border-r border-gray-200 bg-white shrink-0 min-h-0">
        <ThreadList
          :threads="threads"
          :selected-id="selectedThreadId"
          :syncing="syncing"
          @select="selectThread"
          @compose="composing = true"
          @refresh="syncNow"
        />
      </div>
      <div class="flex-1 min-h-0">
        <EmailThread
          :thread="selectedThread"
          :messages="messages"
          :loading="messagesLoading"
          :sending="sending"
          :send-error="sendError"
          @send="sendReply"
          @download="downloadAttachment"
        />
      </div>
    </div>

    <!-- Composizione nuova email -->
    <div v-if="composing" class="fixed inset-0 bg-black/30 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Nuova email</h3>
        <form @submit.prevent="submitCompose" class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Destinatario</label>
            <input v-model="compose.to" type="email" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Oggetto</label>
            <input v-model="compose.subject" type="text" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Messaggio</label>
            <textarea v-model="compose.body" rows="5" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 resize-none" />
          </div>
          <input ref="composeFileInput" type="file" multiple class="text-xs text-gray-500" />
          <p v-if="composeError" class="text-xs text-red-600">{{ composeError }}</p>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="composing = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Annulla</button>
            <button type="submit" :disabled="composeSending" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
              {{ composeSending ? 'Invio...' : 'Invia' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { useEcho } from '@laravel/echo-vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ThreadList from '@/Components/Email/ThreadList.vue'
import type { ThreadSummary } from '@/Components/Email/ThreadList.vue'
import EmailThread from '@/Components/Email/EmailThread.vue'
import type { EmailMessageItem } from '@/Components/Email/EmailThread.vue'
import type { PageProps } from '@/types'

const props = defineProps<{ emailConfigured: boolean }>()

const page = usePage<PageProps>()
const tenantId = computed(() => page.props.auth.user.tenant_id)

const threads = ref<ThreadSummary[]>([])
const selectedThreadId = ref<number | null>(null)
const messages = ref<EmailMessageItem[]>([])
const messagesLoading = ref(false)
const sending = ref(false)
const sendError = ref<string | null>(null)

const selectedThread = computed(
  () => threads.value.find((t) => t.id === selectedThreadId.value) ?? null
)

async function loadThreads() {
  const { data } = await axios.get(route('email.threads.index'))
  threads.value = data.threads
}

const syncing = ref(false)

async function syncNow() {
  if (syncing.value) return
  syncing.value = true
  try {
    const { data } = await axios.post(route('email.sync'))
    threads.value = data.threads
  } finally {
    syncing.value = false
  }
}

async function selectThread(id: number) {
  selectedThreadId.value = id
  messagesLoading.value = true
  const thread = threads.value.find((t) => t.id === id)
  if (thread) thread.unreadCount = 0
  try {
    const { data } = await axios.get(route('email.threads.messages', id))
    messages.value = data.messages
  } finally {
    messagesLoading.value = false
  }
}

async function sendReply(body: string, files: File[]) {
  if (!selectedThreadId.value) return
  sending.value = true
  sendError.value = null
  try {
    const formData = new FormData()
    formData.append('body', body)
    files.forEach((f) => formData.append('attachments[]', f))

    const { data } = await axios.post(route('email.threads.reply', selectedThreadId.value), formData)
    messages.value.push(data.message)

    const thread = threads.value.find((t) => t.id === selectedThreadId.value)
    if (thread) {
      thread.lastMessagePreview = body.slice(0, 200)
      thread.lastMessageAt = data.message.createdAt
    }
  } catch {
    sendError.value = 'Invio non riuscito. Riprova.'
  } finally {
    sending.value = false
  }
}

async function downloadAttachment(attachmentId: number) {
  const { data } = await axios.get(route('email.attachments.download', attachmentId))
  window.open(data.url, '_blank')
}

// ── Composizione nuova email ─────────────────────────────────────────────────

const composing = ref(false)
const composeSending = ref(false)
const composeError = ref<string | null>(null)
const composeFileInput = ref<HTMLInputElement | null>(null)
const compose = reactive({ to: '', subject: '', body: '' })

async function submitCompose() {
  composeSending.value = true
  composeError.value = null
  try {
    const formData = new FormData()
    formData.append('to', compose.to)
    formData.append('subject', compose.subject)
    formData.append('body', compose.body)
    const files = composeFileInput.value?.files ? Array.from(composeFileInput.value.files) : []
    files.forEach((f) => formData.append('attachments[]', f))

    const { data } = await axios.post(route('email.compose'), formData)
    threads.value.unshift(data.thread)
    composing.value = false
    compose.to = ''
    compose.subject = ''
    compose.body = ''
    await selectThread(data.thread.id)
  } catch (err: unknown) {
    composeError.value = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? 'Invio non riuscito.'
  } finally {
    composeSending.value = false
  }
}

// ── Realtime ─────────────────────────────────────────────────────────────

if (props.emailConfigured && tenantId.value) {
  const channel = `email.${tenantId.value}`

  useEcho<{ thread: ThreadSummary; message: EmailMessageItem }>(channel, '.message', (e) => {
    const existing = threads.value.find((t) => t.id === e.thread.id)
    const isOpen = selectedThreadId.value === e.thread.id
    const merged = { ...e.thread, unreadCount: isOpen ? 0 : e.thread.unreadCount }

    if (existing) {
      Object.assign(existing, merged)
    } else {
      threads.value.unshift(merged)
    }

    threads.value.sort((a, b) => {
      if (!a.lastMessageAt) return 1
      if (!b.lastMessageAt) return -1
      return new Date(b.lastMessageAt).getTime() - new Date(a.lastMessageAt).getTime()
    })

    if (isOpen) {
      messages.value.push(e.message)
    }
  })
}

loadThreads()
</script>
