<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

defineProps<{
  canResetPassword?: boolean
  status?: string
}>()

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const currentYear = computed(() => new Date().getFullYear())

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <Head title="Accedi — SinistroPro" />

  <div class="min-h-screen flex">

    <!-- ── Pannello sinistro branded (desktop only) ── -->
    <div class="hidden lg:flex lg:w-[58%] relative overflow-hidden flex-col"
         style="background: linear-gradient(145deg, #0e3d2a 0%, #082519 55%, #041209 100%);">

      <!-- Dot-grid di sfondo -->
      <svg class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <pattern id="dotgrid" x="0" y="0" width="28" height="28" patternUnits="userSpaceOnUse">
            <circle cx="1.5" cy="1.5" r="1.2" fill="white" fill-opacity="0.06"/>
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#dotgrid)"/>
      </svg>

      <!-- Glow radiali -->
      <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full pointer-events-none"
           style="background: radial-gradient(circle, rgba(52,211,153,0.15) 0%, transparent 68%)"></div>
      <div class="absolute -bottom-32 -right-32 w-[520px] h-[520px] rounded-full pointer-events-none"
           style="background: radial-gradient(circle, rgba(52,211,153,0.10) 0%, transparent 68%)"></div>

      <!-- Linee decorative in basso -->
      <svg class="absolute bottom-0 left-0 w-full" viewBox="0 0 900 320" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <polyline points="0,250  180,130  360,200  540,80  720,160  900,100"
                  fill="none" stroke="#34d399" stroke-width="1.2" opacity="0.2"/>
        <polyline points="0,290  180,170  360,240  540,120  720,200  900,140"
                  fill="none" stroke="#34d399" stroke-width="0.7" opacity="0.10"/>
        <circle cx="180" cy="130" r="3.5" fill="#34d399" opacity="0.4"/>
        <circle cx="360" cy="200" r="3.5" fill="#34d399" opacity="0.4"/>
        <circle cx="540" cy="80"  r="3.5" fill="#34d399" opacity="0.4"/>
        <circle cx="720" cy="160" r="3.5" fill="#34d399" opacity="0.4"/>
        <line x1="180" y1="130" x2="180" y2="320" stroke="#34d399" stroke-width="0.6" opacity="0.10"/>
        <line x1="360" y1="200" x2="360" y2="320" stroke="#34d399" stroke-width="0.6" opacity="0.10"/>
        <line x1="540" y1="80"  x2="540" y2="320" stroke="#34d399" stroke-width="0.6" opacity="0.10"/>
        <line x1="720" y1="160" x2="720" y2="320" stroke="#34d399" stroke-width="0.6" opacity="0.10"/>
      </svg>

      <!-- Barra accent diagonale destra -->
      <div class="absolute top-0 right-0 w-1 h-full opacity-20"
           style="background: linear-gradient(to bottom, #34d399, transparent)"></div>

      <!-- Contenuto pannello -->
      <div class="relative z-10 flex flex-col h-full justify-between p-14">

        <!-- Logo -->
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
               style="background: rgba(255,255,255,0.10); border: 1px solid rgba(255,255,255,0.16);">
            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"
                    stroke="white" stroke-width="1.8" stroke-linejoin="round"/>
              <path d="M9 12l2 2 4-4" stroke="white" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div>
            <p class="text-white font-bold text-base leading-none tracking-wide">SinistroPro</p>
            <p class="text-emerald-400 text-[10px] font-semibold tracking-[0.18em] mt-0.5 uppercase">Piattaforma assicurativa</p>
          </div>
        </div>

        <!-- Hero copy -->
        <div class="max-w-md">
          <div class="inline-flex items-center gap-2 mb-5 px-3 py-1 rounded-full text-xs font-medium text-emerald-200"
               style="background: rgba(52,211,153,0.12); border: 1px solid rgba(52,211,153,0.22);">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            Gestione sinistri multi-azienda
          </div>
          <h1 class="text-white font-bold leading-tight mb-4"
              style="font-size: clamp(1.9rem, 3vw, 2.6rem);">
            Ogni sinistro,<br/>sotto controllo.<br/>In tempo reale.
          </h1>
          <p class="text-emerald-200 text-[0.93rem] leading-relaxed">
            Centralizza la gestione dei sinistri, monitora scadenze e avvisi,
            condividi sinistri con il tuo team — tutto in un unico posto sicuro.
          </p>

          <!-- Feature pills -->
          <div class="flex flex-wrap gap-2 mt-7">
            <span v-for="f in ['Sinistri in tempo reale', 'Avvisi automatici', 'Multi-azienda', 'Documenti sicuri']"
                  :key="f"
                  class="inline-flex items-center gap-1.5 text-xs text-emerald-100 px-3 py-1 rounded-full"
                  style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.10);">
              <svg class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24"
                   stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
              {{ f }}
            </span>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center gap-4">
          <p class="text-emerald-700 text-xs">© {{ currentYear }} SinistroPro</p>
          <span class="w-px h-3 bg-emerald-900"></span>
          <p class="text-emerald-700 text-xs">Tutti i diritti riservati</p>
        </div>

      </div>
    </div>

    <!-- ── Pannello destro form ── -->
    <div class="w-full lg:w-[42%] flex flex-col items-center justify-center bg-white px-8 sm:px-14 py-14">

      <!-- Header mobile -->
      <div class="mb-8 text-center lg:hidden">
        <p class="text-gray-900 font-bold text-xl">SinistroPro</p>
        <p class="text-xs font-semibold tracking-widest uppercase mt-0.5" style="color: #0e3d2a;">Piattaforma assicurativa</p>
      </div>

      <div class="w-full max-w-[340px]">

        <!-- Heading -->
        <div class="mb-8">
          <h2 class="text-2xl font-bold text-gray-900">Bentornato</h2>
          <p class="text-gray-400 text-sm mt-1">Accedi al pannello sinistri</p>
        </div>

        <!-- Status message (es. reset password completato) -->
        <div v-if="status"
             class="mb-5 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3">
          {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">

          <!-- Email -->
          <div>
            <label for="email" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
              Indirizzo Email
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="username"
              required
              autofocus
              placeholder="mario@azienda.it"
              class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 transition"
              :class="form.errors.email
                ? 'border-red-400 focus:ring-red-300'
                : 'focus:ring-emerald-300 focus:border-emerald-500'"
            />
            <InputError :message="form.errors.email" class="mt-1.5" />
          </div>

          <!-- Password -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label for="password" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide">
                Password
              </label>
              <Link
                v-if="canResetPassword"
                :href="route('password.request')"
                class="text-xs font-medium transition-colors"
                style="color: #0e3d2a;"
              >
                Password dimenticata?
              </Link>
            </div>
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
              class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 transition"
              :class="form.errors.password
                ? 'border-red-400 focus:ring-red-300'
                : 'focus:ring-emerald-300 focus:border-emerald-500'"
            />
            <InputError :message="form.errors.password" class="mt-1.5" />
          </div>

          <!-- Remember me -->
          <div class="flex items-center gap-2">
            <input v-model="form.remember" type="checkbox" id="remember"
                   class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
            <label for="remember" class="text-sm text-gray-500 cursor-pointer select-none">
              Ricordami
            </label>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="form.processing"
            class="signin-btn w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-white text-sm font-semibold transition-opacity disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <svg v-if="form.processing" class="animate-spin h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            {{ form.processing ? 'Accesso in corso…' : 'Accedi' }}
          </button>

        </form>
      </div>
    </div>

  </div>
</template>

<style scoped>
.signin-btn {
  background-color: #0e3d2a;
}
.signin-btn:hover:not(:disabled) {
  background-color: #0a2e1f;
}
</style>
