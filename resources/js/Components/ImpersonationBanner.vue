<template>
  <Transition
    enter-active-class="transition duration-300"
    enter-from-class="opacity-0 -translate-y-2"
    leave-active-class="transition duration-200"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div
      v-if="isImpersonating"
      class="w-full bg-amber-500 text-white px-4 py-2.5 flex items-center justify-between gap-4 text-sm font-medium shadow-sm z-50"
    >
      <div class="flex items-center gap-2">
        <!-- Warning icon -->
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <span>
          Stai operando in modalità assistenza come
          <strong>{{ user.name }}</strong> ({{ user.email }})
          per conto di questo tenant.
        </span>
      </div>

      <button
        @click="leaveImpersonation"
        class="shrink-0 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-full border border-white/40 transition"
      >
        ✕ Termina assistenza
      </button>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'

const page = usePage<PageProps>()
const isImpersonating = computed(() => page.props.auth.isImpersonating)
const user = computed(() => page.props.auth.user)

function leaveImpersonation() {
  router.post(route('impersonate.leave'), {}, {
    onSuccess: () => {
      window.location.href = '/superadmin'
    },
  })
}
</script>
