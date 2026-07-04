<template>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50">
      <h3 class="text-sm font-semibold text-slate-700">{{ title }}</h3>
    </div>
    <div class="p-5">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ title: string }>()
</script>
