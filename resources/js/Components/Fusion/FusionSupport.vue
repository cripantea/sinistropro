<script setup lang="ts">
// Fusion Kit — sorgente unica in fusion-id/kit/laravel (copiata da kit/sync.sh: non modificare qui).
// Carica il widget Assistenza (Fusion Desk) e lo toglie quando il layout riservato si smonta:
// con Inertia un <script> in app.blade.php non partirebbe dopo il login senza ricaricare la pagina.
import { onBeforeUnmount, watch } from 'vue'

const props = defineProps<{ widget?: { src: string; attrs: Record<string, string> } | null }>()

type DeskWindow = Window & { FusionDesk?: { destroy?: () => void } }
let script: HTMLScriptElement | null = null

function unload() {
  ;(window as DeskWindow).FusionDesk?.destroy?.()
  script?.remove()
  script = null
}

function load() {
  unload()
  if (!props.widget) return
  script = document.createElement('script')
  script.src = props.widget.src
  script.async = true
  for (const [k, v] of Object.entries(props.widget.attrs)) script.setAttribute(k, v)
  document.body.appendChild(script)
}

watch(() => props.widget?.attrs['data-token'], load, { immediate: true })
onBeforeUnmount(unload)
</script>

<template>
  <span hidden></span>
</template>
