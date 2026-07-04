<template>
  <Link
    :href="href"
    class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors"
    :class="active
      ? 'bg-indigo-600 text-white'
      : 'text-slate-300 hover:bg-slate-800 hover:text-slate-100'"
  >
    <span class="shrink-0"><slot name="icon" /></span>
    <slot />
  </Link>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
defineProps<{ href: string; active?: boolean }>()
</script>
