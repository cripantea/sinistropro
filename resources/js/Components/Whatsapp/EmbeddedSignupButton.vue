<template>
  <div>
    <button
      type="button"
      :disabled="loading || !configId"
      @click="startSignup"
      class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-medium text-white shadow-sm transition"
      :class="loading || !configId ? 'bg-gray-300 cursor-not-allowed' : 'bg-[#25D366] hover:bg-[#20bf59]'"
    >
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
        <path d="M20.52 3.48A11.93 11.93 0 0012.04 0C5.5 0 .2 5.3.2 11.85c0 2.09.55 4.13 1.6 5.93L0 24l6.4-1.68a11.86 11.86 0 005.64 1.44h.01c6.54 0 11.85-5.3 11.85-11.85 0-3.17-1.23-6.14-3.38-8.43zM12.04 21.4h-.01a9.5 9.5 0 01-4.85-1.33l-.35-.21-3.6.94.96-3.5-.23-.36a9.5 9.5 0 01-1.46-5.09c0-5.25 4.28-9.53 9.55-9.53a9.5 9.5 0 019.53 9.55c0 5.25-4.28 9.53-9.54 9.53z"/>
      </svg>
      {{ loading ? 'Collegamento in corso…' : 'Collega numero WhatsApp' }}
    </button>

    <p v-if="!configId" class="text-xs text-gray-400 mt-2">
      Collegamento non ancora configurato lato piattaforma.
    </p>
    <p v-if="error" class="text-xs text-red-600 mt-2">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'

declare global {
  interface Window {
    FB?: {
      init: (params: Record<string, unknown>) => void
      login: (callback: (response: FbLoginResponse) => void, params: Record<string, unknown>) => void
    }
    fbAsyncInit?: () => void
  }
}

interface FbLoginResponse {
  authResponse?: { code?: string }
  status?: string
}

interface EmbeddedSignupData {
  phone_number_id?: string
  waba_id?: string
  business_id?: string
}

const props = defineProps<{
  facebookAppId: string
  facebookGraphVersion: string
  configId: string
}>()

const emit = defineEmits<{
  connected: [session: { status: string; phoneNumber: string | null }]
}>()

const loading = ref(false)
const error = ref<string | null>(null)

let signupData: EmbeddedSignupData | null = null
let loginCode: string | null = null

function onFbMessage(event: MessageEvent) {
  if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return

  try {
    const payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data
    if (payload?.type === 'WA_EMBEDDED_SIGNUP' && payload?.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') {
      signupData = payload.data ?? null
      tryCompleteSignup()
    }
  } catch {
    // Messaggi non pertinenti (altri postMessage del dominio Facebook): ignorati.
  }
}

function loadFacebookSdk(): Promise<void> {
  return new Promise((resolve) => {
    if (window.FB) {
      resolve()
      return
    }

    window.fbAsyncInit = () => {
      window.FB?.init({
        appId: props.facebookAppId,
        version: `v${props.facebookGraphVersion.replace(/^v/, '')}`,
        xfbml: false,
      })
      resolve()
    }

    const script = document.createElement('script')
    script.src = 'https://connect.facebook.net/it_IT/sdk.js'
    script.async = true
    script.defer = true
    document.body.appendChild(script)
  })
}

async function startSignup() {
  error.value = null
  loading.value = true
  signupData = null
  loginCode = null

  try {
    await loadFacebookSdk()

    window.FB?.login(
      (response) => {
        if (response.authResponse?.code) {
          loginCode = response.authResponse.code
          tryCompleteSignup()
        } else {
          loading.value = false
          error.value = 'Collegamento annullato o non autorizzato.'
        }
      },
      {
        config_id: props.configId,
        response_type: 'code',
        override_default_response_type: true,
        extras: {
          featureType: 'whatsapp_business_app_onboarding',
          sessionInfoVersion: '3',
        },
      }
    )
  } catch {
    loading.value = false
    error.value = 'Impossibile caricare il collegamento con Meta. Riprova.'
  }
}

async function tryCompleteSignup() {
  // Il postMessage con i dati WABA e il callback FB.login con il code non
  // arrivano in un ordine garantito: si procede solo quando entrambi sono pronti.
  if (!loginCode || !signupData?.waba_id || !signupData?.phone_number_id) {
    return
  }

  try {
    const { data } = await axios.post(route('whatsapp.embedded-signup.callback'), {
      code: loginCode,
      waba_id: signupData.waba_id,
      phone_number_id: signupData.phone_number_id,
      business_id: signupData.business_id ?? null,
    })

    emit('connected', data.session)
  } catch (e: unknown) {
    const message = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    error.value = message ?? 'Collegamento non riuscito. Riprova.'
  } finally {
    loading.value = false
    signupData = null
    loginCode = null
  }
}

onMounted(() => {
  window.addEventListener('message', onFbMessage)
})

onUnmounted(() => {
  window.removeEventListener('message', onFbMessage)
})
</script>
