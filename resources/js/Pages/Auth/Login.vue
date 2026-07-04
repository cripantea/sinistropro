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
  <Head title="Login — Kryptodoc" />

  <div class="min-h-screen flex">

    <!-- ── LEFT PANEL: visual identity (desktop only) ── -->
    <div class="hidden md:flex md:w-[65%] relative bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 flex-col justify-between p-12 overflow-hidden select-none">

      <!-- Grid pattern overlay -->
      <div
        class="absolute inset-0 pointer-events-none"
        style="background-image: linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px); background-size: 48px 48px;"
      />

      <!-- Glow accent -->
      <div class="absolute -top-40 -right-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none" />
      <div class="absolute -bottom-40 -left-20 w-80 h-80 bg-indigo-800/20 rounded-full blur-3xl pointer-events-none" />

      <!-- Top: brand -->
      <div class="relative z-10">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-11 h-11 rounded-xl bg-indigo-500 flex items-center justify-center shadow-lg shadow-indigo-500/40 flex-shrink-0">
            <!-- Shield icon -->
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <div>
            <span class="text-white font-bold text-3xl tracking-tight leading-none">Kryptodoc</span>
          </div>
        </div>
        <p class="text-indigo-300/80 text-xs font-medium tracking-wide uppercase mt-1">
          Sistema di Archiviazione Documentale Multi-Tenant Sicuro
        </p>
      </div>

      <!-- Center: headline + impact text -->
      <div class="relative z-10">
        <h1 class="text-4xl xl:text-5xl font-bold text-white leading-[1.15] mb-5">
          Gestione<br>
          <span class="text-indigo-400">documentale</span><br>
          professionale.
        </h1>
        <p class="text-slate-300 text-base max-w-sm leading-relaxed">
          I tuoi documenti, protetti da crittografia e isolamento avanzato.<br>
          Benvenuto nella tua cassaforte digitale.
        </p>
      </div>

      <!-- Bottom: feature pills + quote -->
      <div class="relative z-10 space-y-6">
        <div class="flex flex-wrap gap-2">
          <span class="inline-flex items-center gap-1.5 bg-white/5 border border-white/10 text-slate-300 text-xs px-3 py-1.5 rounded-full">
            <svg class="w-3 h-3 text-indigo-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            Multi-tenant sicuro
          </span>
          <span class="inline-flex items-center gap-1.5 bg-white/5 border border-white/10 text-slate-300 text-xs px-3 py-1.5 rounded-full">
            <svg class="w-3 h-3 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Allegati su S3
          </span>
          <span class="inline-flex items-center gap-1.5 bg-white/5 border border-white/10 text-slate-300 text-xs px-3 py-1.5 rounded-full">
            <svg class="w-3 h-3 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Audit log completo
          </span>
          <span class="inline-flex items-center gap-1.5 bg-white/5 border border-white/10 text-slate-300 text-xs px-3 py-1.5 rounded-full">
            <svg class="w-3 h-3 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            Export PDF
          </span>
        </div>

        <blockquote class="border-l-2 border-indigo-500/40 pl-4">
          <p class="text-slate-500 text-sm italic leading-relaxed">
            "La sicurezza non è un optional — è il fondamento di ogni archivio che dura nel tempo."
          </p>
        </blockquote>
      </div>

    </div>

    <!-- ── RIGHT PANEL: login form ── -->
    <div class="w-full md:w-[35%] flex flex-col bg-white">

      <div class="flex-1 flex flex-col justify-center px-8 sm:px-12 md:px-10 lg:px-14 py-12">

        <!-- Mobile logo -->
        <div class="flex items-center gap-2.5 mb-10 md:hidden">
          <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <span class="text-gray-900 font-bold text-xl tracking-tight">Kryptodoc</span>
        </div>

        <div class="max-w-sm w-full mx-auto">

          <!-- Heading -->
          <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Accedi a Kryptodoc</h2>
            <p class="text-sm text-gray-500">Inserisci le tue credenziali per entrare</p>
          </div>

          <!-- Status message (es. reset password completato) -->
          <div v-if="status" class="mb-6 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ status }}
          </div>

          <!-- Form -->
          <form @submit.prevent="submit" class="space-y-5">

            <!-- Email -->
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                Indirizzo email
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                </div>
                <input
                  id="email"
                  v-model="form.email"
                  type="email"
                  autocomplete="username"
                  required
                  autofocus
                  placeholder="nome@azienda.it"
                  class="block w-full pl-10 pr-3 py-2.5 text-sm text-gray-900 border rounded-lg outline-none transition-all placeholder-gray-400"
                  :class="form.errors.email
                    ? 'border-red-400 focus:ring-2 focus:ring-red-400/30 focus:border-red-400'
                    : 'border-gray-300 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500'"
                />
              </div>
              <InputError :message="form.errors.email" class="mt-1.5" />
            </div>

            <!-- Password -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <Link
                  v-if="canResetPassword"
                  :href="route('password.request')"
                  class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition-colors"
                >
                  Password dimenticata?
                </Link>
              </div>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                  </svg>
                </div>
                <input
                  id="password"
                  v-model="form.password"
                  type="password"
                  autocomplete="current-password"
                  required
                  placeholder="••••••••"
                  class="block w-full pl-10 pr-3 py-2.5 text-sm text-gray-900 border rounded-lg outline-none transition-all placeholder-gray-400"
                  :class="form.errors.password
                    ? 'border-red-400 focus:ring-2 focus:ring-red-400/30 focus:border-red-400'
                    : 'border-gray-300 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500'"
                />
              </div>
              <InputError :message="form.errors.password" class="mt-1.5" />
            </div>

            <!-- Remember me -->
            <div class="flex items-center gap-2.5">
              <input
                id="remember"
                v-model="form.remember"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
              />
              <label for="remember" class="text-sm text-gray-600 cursor-pointer select-none">
                Ricordami su questo dispositivo
              </label>
            </div>

            <!-- Submit -->
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold py-2.5 px-4 rounded-lg transition-all duration-150 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 outline-none disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2 mt-1 shadow-sm"
            >
              <svg v-if="form.processing" class="animate-spin w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              {{ form.processing ? 'Accesso in corso…' : 'Accedi' }}
            </button>

          </form>

        </div>
      </div>

      <!-- Footer -->
      <div class="px-8 sm:px-12 md:px-10 lg:px-14 pb-6">
        <p class="text-xs text-gray-400 text-center">
          &copy; {{ currentYear }} Kryptodoc &mdash; Archiviatore Documentale Multi-Tenant
        </p>
      </div>

    </div>

  </div>
</template>
