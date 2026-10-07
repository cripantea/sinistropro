<script setup lang="ts">
defineProps<{ dark?: boolean }>()
</script>

<template>
  <a
    href="https://fusionsoft.it"
    target="_blank"
    rel="noopener"
    :class="['inline-flex items-center gap-2.5 text-left transition-colors', dark ? 'text-gray-400 hover:text-white' : 'text-gray-500 hover:text-gray-900']"
  >
    <img src="/images/fusionsoft-mark.png" alt="" class="h-5 w-5 shrink-0 object-contain" />
    <span class="flex flex-col leading-tight">
      <span class="text-xs">Un prodotto <strong class="font-semibold" :class="dark ? 'text-gray-200' : 'text-gray-700'">Fusionsoft</strong></span>
      <span class="text-[10px] opacity-80">Fusion Media di Luca Gabriele Pantea · P.IVA 13148540969</span>
    </span>
  </a>
</template>
