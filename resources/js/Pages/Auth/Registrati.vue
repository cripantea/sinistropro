<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const form = useForm({
  nome: '',
  agenzia: '',
  email: '',
  password: '',
  sito: '', // campo nascosto anti-bot
})

const mostraPassword = ref(false)

const submit = () => {
  form.post(route('registrati'), {
    onFinish: () => form.reset('password'),
  })
}

const campo = (errore?: string) => [
  'w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 transition',
  errore ? 'border-red-400 focus:ring-red-300' : 'border-gray-300 focus:ring-blue-300 focus:border-blue-500',
]
</script>

<template>
  <Head title="Crea il tuo account — SinistroPro" />

  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-12">
    <div class="mb-8 text-center">
      <p class="text-gray-900 font-bold text-2xl">SinistroPro</p>
      <p class="text-xs font-semibold tracking-widest uppercase mt-0.5" style="color: #1e3a8a;">Piattaforma assicurativa</p>
    </div>

    <div class="w-full max-w-[400px] bg-white rounded-xl border border-gray-200 shadow-sm p-8">
      <h1 class="text-xl font-bold text-gray-900">Crea il tuo account</h1>
      <p class="text-gray-500 text-sm mt-1">Un minuto: poi scegli il piano e paghi online.</p>

      <form @submit.prevent="submit" class="space-y-4 mt-6">
        <div>
          <label for="nome" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Nome e cognome</label>
          <input id="nome" v-model="form.nome" type="text" autocomplete="name" required autofocus placeholder="Mario Rossi" :class="campo(form.errors.nome)" />
          <InputError :message="form.errors.nome" class="mt-1.5" />
        </div>

        <div>
          <label for="agenzia" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Nome dell'agenzia</label>
          <input id="agenzia" v-model="form.agenzia" type="text" autocomplete="organization" required placeholder="Agenzia Rossi Assicurazioni" :class="campo(form.errors.agenzia)" />
          <InputError :message="form.errors.agenzia" class="mt-1.5" />
        </div>

        <div>
          <label for="email" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Email</label>
          <input id="email" v-model="form.email" type="email" autocomplete="username" required placeholder="mario@agenzia.it" :class="campo(form.errors.email)" />
          <InputError :message="form.errors.email" class="mt-1.5" />
        </div>

        <div>
          <label for="password" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Password</label>
          <div class="relative">
            <input id="password" v-model="form.password" :type="mostraPassword ? 'text' : 'password'" autocomplete="new-password" required minlength="8" placeholder="Almeno 8 caratteri" :class="[...campo(form.errors.password), 'pr-16']" />
            <button type="button" @click="mostraPassword = !mostraPassword" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-gray-500 hover:text-gray-700">
              {{ mostraPassword ? 'Nascondi' : 'Mostra' }}
            </button>
          </div>
          <InputError :message="form.errors.password" class="mt-1.5" />
        </div>

        <input v-model="form.sito" type="text" name="sito" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" />

        <button type="submit" :disabled="form.processing" class="crea-btn w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-white text-sm font-semibold transition-opacity disabled:opacity-60 disabled:cursor-not-allowed">
          <svg v-if="form.processing" class="animate-spin h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          {{ form.processing ? 'Creazione in corso…' : 'Crea account e vai al pagamento' }}
        </button>
      </form>

      <p class="text-sm text-gray-500 text-center mt-6">
        Hai già un account?
        <Link :href="route('login')" class="font-medium" style="color: #1e3a8a;">Accedi</Link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.crea-btn { background-color: #1e3a8a; }
.crea-btn:hover:not(:disabled) { background-color: #172554; }
</style>
