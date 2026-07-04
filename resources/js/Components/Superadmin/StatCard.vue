<template>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex items-center gap-4">
    <div :class="`w-11 h-11 rounded-xl flex items-center justify-center bg-${color}-100 text-${color}-600 shrink-0`">
      <slot name="icon" />
    </div>
    <div>
      <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ value.toLocaleString('it-IT') }}</div>
      <div class="text-sm text-slate-500 mt-0.5">{{ label }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ label: string; value: number; color: string }>()
</script>
