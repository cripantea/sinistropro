<template>
  <div class="flex flex-wrap items-center gap-1.5">
    <TagSinistro v-for="t in tags" :key="t" :tag="t">
      {{ t }}
      <button v-if="modificabile" type="button" class="-mr-0.5 opacity-60 hover:opacity-100" :title="`Togli ${t}`" @click="togli(t)">×</button>
    </TagSinistro>

    <template v-if="modificabile">
      <form v-if="aggiunta" class="inline-flex items-center gap-1" @submit.prevent="aggiungi">
        <input
          ref="campo"
          v-model="nuovo"
          :list="`tag-suggeriti-${praticaId}`"
          maxlength="30"
          placeholder="Nuovo tag"
          class="w-32 rounded-full border border-gray-300 px-2.5 py-0.5 text-xs focus:ring-1 focus:ring-indigo-500 outline-none"
          @keydown.esc="chiudi"
          @blur="nuovo.trim() ? aggiungi() : chiudi()"
        />
        <datalist :id="`tag-suggeriti-${praticaId}`">
          <option v-for="s in suggeriti" :key="s" :value="s" />
        </datalist>
      </form>
      <button
        v-else-if="tags.length < 10"
        type="button"
        class="rounded-full border border-dashed border-gray-300 px-2 py-0.5 text-[11px] text-gray-500 hover:border-indigo-400 hover:text-indigo-600"
        @click="apri"
      >+ Tag</button>
    </template>

    <span v-if="!tags.length && !modificabile" class="text-xs text-gray-400">—</span>
    <span v-if="errore" class="text-xs text-red-600">{{ errore }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import TagSinistro from './TagSinistro.vue'

const props = defineProps<{ praticaId: number; tags: string[]; suggeriti: string[]; modificabile: boolean }>()

const aggiunta = ref(false)
const nuovo = ref('')
const errore = ref('')
const campo = ref<HTMLInputElement | null>(null)
const suggeriti = computed(() => props.suggeriti.filter((s) => !props.tags.some((t) => t.toLowerCase() === s.toLowerCase())))

function apri() {
  aggiunta.value = true
  nextTick(() => campo.value?.focus())
}
function chiudi() {
  aggiunta.value = false
  nuovo.value = ''
}
function salva(tags: string[]) {
  errore.value = ''
  router.patch(route('pratiche.update-tag', props.praticaId), { tags }, {
    preserveScroll: true,
    preserveState: true,
    onError: (e) => { errore.value = Object.values(e)[0] as string },
  })
}
function aggiungi() {
  const t = nuovo.value.trim()
  if (t && !props.tags.some((x) => x.toLowerCase() === t.toLowerCase())) salva([...props.tags, t])
  chiudi()
}
function togli(t: string) {
  salva(props.tags.filter((x) => x !== t))
}
</script>
