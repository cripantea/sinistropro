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

    <div class="py-6 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">

        <!-- Connesso -->
        <div v-if="status === 'connected'">
          <div class="w-14 h-14 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-800">WhatsApp connesso</h3>
          <p class="text-sm text-gray-500 mt-1">
            Numero collegato: <span class="font-medium text-gray-700">{{ phoneNumber ?? '—' }}</span>
          </p>

          <button
            v-if="isTenantAdmin"
            @click="stop"
            :disabled="processing"
            class="mt-6 text-sm text-red-600 border border-red-200 hover:bg-red-50 px-4 py-2 rounded-lg transition disabled:opacity-60"
          >
            Disconnetti
          </button>
        </div>

        <!-- QR da scansionare -->
        <div v-else-if="status === 'qr' && qrCode">
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
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useEcho } from '@laravel/echo-vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
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
