<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

defineProps<{ nome: string }>()

const form = useForm({ password: '' })
const mostraPassword = ref(false)

const submit = () => form.put(route('password.scegli.store'), { onFinish: () => form.reset('password') })
</script>

<template>
  <Head title="Scegli la tua password — SinistroPro" />

  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-12">
    <div class="mb-8 text-center">
      <p class="text-gray-900 font-bold text-2xl">SinistroPro</p>
      <p class="text-xs font-semibold tracking-widest uppercase mt-0.5" style="color: #1e3a8a;">Piattaforma assicurativa</p>
    </div>

    <div class="w-full max-w-[400px] bg-white rounded-xl border border-gray-200 shadow-sm p-8">
      <h1 class="text-xl font-bold text-gray-900">Benvenuto{{ nome ? `, ${nome}` : '' }}</h1>
      <p class="text-gray-500 text-sm mt-1">Hai usato una password provvisoria. Scegline una tua per continuare.</p>

      <form @submit.prevent="submit" class="space-y-4 mt-6">
        <div>
          <label for="password" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Nuova password</label>
          <div class="relative">
            <input id="password" v-model="form.password" :type="mostraPassword ? 'text' : 'password'" autocomplete="new-password" required minlength="8" autofocus placeholder="Almeno 8 caratteri"
                   :class="['w-full border rounded-lg px-3 py-2.5 pr-16 text-sm focus:outline-none focus:ring-2 transition', form.errors.password ? 'border-red-400 focus:ring-red-300' : 'border-gray-300 focus:ring-blue-300 focus:border-blue-500']" />
            <button type="button" @click="mostraPassword = !mostraPassword" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-gray-500 hover:text-gray-700">
              {{ mostraPassword ? 'Nascondi' : 'Mostra' }}
            </button>
          </div>
          <InputError :message="form.errors.password" class="mt-1.5" />
        </div>

        <button type="submit" :disabled="form.processing" class="salva-btn w-full py-2.5 px-4 rounded-lg text-white text-sm font-semibold disabled:opacity-60">
          {{ form.processing ? 'Salvataggio…' : 'Salva e continua' }}
        </button>
      </form>

      <p class="text-sm text-gray-500 text-center mt-6">
        <Link :href="route('logout')" method="post" as="button" class="font-medium" style="color: #1e3a8a;">Esci</Link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.salva-btn { background-color: #1e3a8a; }
.salva-btn:hover:not(:disabled) { background-color: #172554; }
</style>
