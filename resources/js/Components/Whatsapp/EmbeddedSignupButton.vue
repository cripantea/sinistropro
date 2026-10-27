<template>
  <div>
    <div v-if="!token" class="text-xs text-gray-400">
      Collegamento non ancora configurato lato piattaforma.
    </div>
    <template v-else>
      <div :id="containerId"></div>
      <p v-if="error" class="text-xs text-red-600 mt-2">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'

declare global {
  interface Window {
    FusionWA?: {
      init: (params: { token: string; containerId: string }) => void
    }
  }
}

interface FusionWaStatus {
  status: 'NOT_SUBSCRIBED' | 'SUBSCRIBED_UNCONNECTED' | 'CONNECTED'
  phoneNumber?: string
  wabaId?: string
  phoneNumberId?: string
}

const props = defineProps<{
  token: string | null
  fusionwaBaseUrl: string
  tenantId: number | string
}>()

const emit = defineEmits<{
  connected: [session: { status: string; phoneNumber: string | null }]
}>()

const containerId = `fusionwa-widget-${props.tenantId}`
const error = ref<string | null>(null)

const POLL_INTERVAL_MS = 3000

let pollTimer: ReturnType<typeof setInterval> | null = null

function loadFusionWaSdk(): Promise<void> {
  return new Promise((resolve, reject) => {
    if (window.FusionWA) {
      resolve()
      return
    }

    const script = document.createElement('script')
    script.src = `${props.fusionwaBaseUrl}/sdk/v1.js`
    script.async = true
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('Impossibile caricare lo script FusionWA'))
    document.body.appendChild(script)
  })
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

// Il widget FusionWA non espone un callback "connesso": rileviamo il
// completamento interrogando noi stessi lo stato del cliente a intervalli,
// finché non risulta CONNECTED — a quel punto registriamo il collegamento
// anche lato sinistripro (waba/phone number id, mai il token).
async function checkStatus() {
  try {
    const { data } = await axios.get<FusionWaStatus>(
      `${props.fusionwaBaseUrl}/api/v1/widget/status`,
      { params: { token: props.token } }
    )

    if (data.status !== 'CONNECTED' || !data.wabaId || !data.phoneNumberId || !data.phoneNumber) {
      return
    }

    stopPolling()

    try {
      const { data: syncResult } = await axios.post(route('whatsapp.embedded-signup.sync'), {
        waba_id: data.wabaId,
        phone_number_id: data.phoneNumberId,
        phone_number: data.phoneNumber,
      })
      emit('connected', syncResult.session)
    } catch (e: unknown) {
      const message = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      error.value = message ?? 'Collegamento riuscito su WhatsApp ma non salvato. Ricarica la pagina.'
    }
  } catch {
    // Errore transitorio di rete: si ritenta al prossimo giro di polling.
  }
}

function startPolling() {
  checkStatus()
  pollTimer = setInterval(checkStatus, POLL_INTERVAL_MS)
}

onMounted(async () => {
  if (!props.token) return
  try {
    await loadFusionWaSdk()
    window.FusionWA?.init({
      token: props.token,
      containerId,
    })
    startPolling()
  } catch {
    error.value = 'Impossibile caricare il widget di collegamento. Riprova.'
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>
