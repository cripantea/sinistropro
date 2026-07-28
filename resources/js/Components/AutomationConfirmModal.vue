<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
      >
        <Transition
          enter-active-class="transition duration-200"
          enter-from-class="opacity-0 scale-95"
          leave-active-class="transition duration-150"
          leave-to-class="opacity-0 scale-95"
        >
          <div v-if="show" class="bg-white rounded-2xl shadow-2xl w-full max-w-md">

            <div class="flex items-start gap-3 px-6 py-4 border-b border-slate-100">
              <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <div>
                <p class="text-sm font-semibold text-slate-800">Questa azione attiva delle automazioni</p>
                <p class="text-xs text-slate-500 mt-0.5">Decidi come procedere prima di confermare.</p>
              </div>
            </div>

            <div class="px-6 py-4">
              <ul class="space-y-1.5">
                <li v-for="a in automations" :key="a.id" class="flex items-center gap-2 text-sm text-slate-700">
                  <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                  </svg>
                  {{ a.name }}
                </li>
              </ul>
            </div>

            <div class="flex flex-col gap-2 px-6 py-4 border-t border-slate-100">
              <button
                type="button"
                @click="$emit('accept')"
                class="w-full bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition"
              >
                Accetta e procedi
              </button>
              <button
                type="button"
                @click="$emit('block-automations')"
                class="w-full border border-slate-300 text-slate-700 text-sm font-medium py-2.5 rounded-lg hover:bg-slate-50 transition"
              >
                Procedi ma blocca solo le automazioni
              </button>
              <button
                type="button"
                @click="$emit('block-action')"
                class="w-full text-slate-500 text-sm font-medium py-2 rounded-lg hover:bg-slate-50 transition"
              >
                Blocca l'azione (annulla)
              </button>
            </div>

          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
interface AutomationSummary { id: number; name: string }

defineProps<{
  show: boolean
  automations: AutomationSummary[]
}>()

defineEmits<{
  accept: []
  'block-automations': []
  'block-action': []
}>()
</script>
