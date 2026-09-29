<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h1 class="text-base font-semibold text-slate-800">Liste Valori</h1>
        <button
          @click="openCreate"
          class="inline-flex items-center gap-1.5 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Nuova Lista
        </button>
      </div>
    </template>

    <div class="p-6 max-w-3xl space-y-4">

      <p class="text-sm text-slate-500">
        Definisci liste di valori riusabili (es. "Compagnie assicurative", "Tipi di veicolo"). Puoi usarle come opzioni nei campi personalizzati dei sinistri e dei clienti.
      </p>

      <!-- Empty state -->
      <div v-if="liste.length === 0" class="bg-white border border-dashed border-slate-300 rounded-xl px-8 py-14 text-center">
        <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
        <p class="text-sm font-medium text-slate-500">Nessuna lista ancora</p>
        <p class="text-xs text-slate-400 mt-1">Clicca "Nuova Lista" per crearne una.</p>
      </div>

      <!-- Lista cards -->
      <div v-else class="space-y-3">
        <div
          v-for="lista in liste"
          :key="lista.id"
          class="bg-white border border-slate-200 rounded-xl shadow-sm"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
            <div>
              <span class="font-semibold text-slate-800 text-sm">{{ lista.nome }}</span>
              <code class="ml-2 text-xs text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">{{ lista.slug }}</code>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-400">{{ (lista.items ?? []).length }} valori</span>
              <button @click="openEdit(lista)" class="text-xs text-indigo-600 hover:underline">Modifica</button>
              <button @click="confirmDelete(lista)" class="text-xs text-red-500 hover:underline">Elimina</button>
            </div>
          </div>

          <!-- Values chips -->
          <div class="px-5 py-3 flex flex-wrap gap-1.5">
            <span
              v-for="item in (lista.items ?? [])"
              :key="item"
              class="text-xs bg-slate-100 text-slate-700 px-2.5 py-1 rounded-full"
            >{{ item }}</span>
            <span v-if="!(lista.items ?? []).length" class="text-xs text-slate-400 italic">Nessun valore</span>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Modal crea/modifica ────────────────────────── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0" leave-active-class="transition duration-100" leave-to-class="opacity-0">
        <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" @mousedown.self="closeModal">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>

            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
              <h3 class="text-base font-semibold text-slate-900">{{ editing ? 'Modifica Lista' : 'Nuova Lista' }}</h3>
              <button @click="closeModal" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <form @submit.prevent="submit" class="px-6 py-5 space-y-5">

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Nome lista <span class="text-red-500">*</span></label>
                <input
                  v-model="form.nome"
                  type="text"
                  class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                  :class="{ 'border-red-400': form.errors.nome }"
                  placeholder="Es. Compagnie assicurative"
                  @input="updateSlugPreview"
                />
                <p v-if="slugPreview" class="text-xs text-slate-400 mt-1">Slug: <code class="bg-slate-100 px-1 rounded">{{ slugPreview }}</code></p>
                <p v-if="form.errors.nome" class="text-xs text-red-500 mt-1">{{ form.errors.nome }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-2">Valori</label>

                <TransitionGroup name="list" tag="div" class="space-y-2">
                  <div v-for="(item, i) in form.items" :key="i" class="flex items-center gap-2">
                    <input
                      v-model="form.items[i]"
                      type="text"
                      class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                      :placeholder="`Valore ${i + 1}`"
                      @keydown.enter.prevent="addItem(i)"
                    />
                    <button type="button" @click="removeItem(i)" class="shrink-0 text-slate-400 hover:text-red-500 transition p-1">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                </TransitionGroup>

                <button type="button" @click="addItem()" class="mt-2 inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium transition">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  Aggiungi valore
                </button>

                <div
                  v-if="form.items.length === 0"
                  class="mt-2 border-2 border-dashed border-slate-200 rounded-lg px-4 py-3 text-center text-xs text-slate-400"
                >
                  Nessun valore ancora — clicca "Aggiungi valore" o incolla qui sotto
                </div>

                <!-- Bulk paste -->
                <div class="mt-3">
                  <label class="block text-xs font-medium text-slate-500 mb-1">Incolla lista (un valore per riga)</label>
                  <textarea
                    rows="3"
                    class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none resize-none"
                    placeholder="UnipolSai&#10;Generali&#10;Allianz"
                    @blur="onBulkPaste"
                    ref="bulkRef"
                  />
                </div>
              </div>

              <div class="flex items-center gap-3 pt-1">
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition"
                >
                  {{ form.processing ? 'Salvataggio…' : (editing ? 'Salva modifiche' : 'Crea lista') }}
                </button>
                <button type="button" @click="closeModal" class="px-4 py-2.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition">Annulla</button>
              </div>

            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface Lista { id: number; nome: string; slug: string; items: string[] | null }

const props = defineProps<{ liste: Lista[] }>()

const modalOpen   = ref(false)
const editing     = ref<Lista | null>(null)
const slugPreview = ref('')
const bulkRef     = ref<HTMLTextAreaElement | null>(null)

const form = useForm({
  nome:  '',
  items: [] as string[],
})

function slugify(s: string): string {
  return s.toLowerCase().trim().replace(/[àáâ]/g, 'a').replace(/[èéê]/g, 'e').replace(/[ìíî]/g, 'i').replace(/[òóô]/g, 'o').replace(/[ùúû]/g, 'u').replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
}

function updateSlugPreview() {
  if (!editing.value) slugPreview.value = slugify(form.nome)
}

function openCreate() {
  editing.value     = null
  slugPreview.value = ''
  form.reset()
  form.items        = ['']
  modalOpen.value   = true
}

function openEdit(lista: Lista) {
  editing.value     = lista
  slugPreview.value = ''
  form.nome         = lista.nome
  form.items        = lista.items ? [...lista.items] : []
  modalOpen.value   = true
}

function closeModal() {
  modalOpen.value = false
  editing.value   = null
  form.reset()
}

function addItem(afterIndex?: number) {
  if (afterIndex !== undefined) {
    form.items.splice(afterIndex + 1, 0, '')
  } else {
    form.items.push('')
  }
}

function removeItem(i: number) {
  form.items.splice(i, 1)
}

function onBulkPaste() {
  if (!bulkRef.value) return
  const lines = bulkRef.value.value.split('\n').map(l => l.trim()).filter(l => l.length > 0)
  if (lines.length === 0) return
  form.items = [...form.items.filter(v => v.trim() !== ''), ...lines]
  bulkRef.value.value = ''
}

function submit() {
  if (editing.value) {
    form.put(route('liste.update', editing.value.id), { onSuccess: closeModal })
  } else {
    form.post(route('liste.store'), { onSuccess: closeModal })
  }
}

function confirmDelete(lista: Lista) {
  if (confirm(`Eliminare la lista "${lista.nome}"? L'operazione è irreversibile.`)) {
    router.delete(route('liste.destroy', lista.id))
  }
}
</script>

<style scoped>
.list-enter-active,
.list-leave-active { transition: all 0.15s ease; }
.list-enter-from,
.list-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
