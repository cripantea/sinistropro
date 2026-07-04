<template>
  <SuperadminLayout title="Categorie Documenti">
    <div class="p-6 max-w-5xl mx-auto">

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ── Lista categorie ──────────────────────────── -->
        <div class="lg:col-span-2 space-y-3">
          <p class="text-sm text-slate-500 mb-1">{{ categories.length }} categor{{ categories.length === 1 ? 'ia' : 'ie' }} globali</p>

          <div
            v-for="cat in categories"
            :key="cat.id"
            class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4 flex items-center gap-4"
          >
            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
              </svg>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-slate-800">{{ cat.name }}</p>
                <span class="text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ cat.allegati_count }} file</span>
              </div>
              <p v-if="cat.description" class="text-xs text-slate-500 truncate mt-0.5">{{ cat.description }}</p>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
              <!-- Edit button -->
              <button
                @click="openEdit(cat)"
                class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                title="Modifica"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
              </button>
              <!-- Delete button -->
              <button
                @click="confirmDelete(cat)"
                class="p-1.5 rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-colors"
                title="Elimina"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>
          </div>

          <div v-if="categories.length === 0" class="bg-white rounded-xl border border-dashed border-slate-300 px-5 py-12 text-center text-slate-400 text-sm">
            Nessuna categoria. Aggiungine una dal form.
          </div>
        </div>

        <!-- ── Form nuova categoria ─────────────────────── -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 h-fit">
          <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nuova categoria
          </h3>

          <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Nome</label>
              <input
                v-model="createForm.name"
                type="text"
                placeholder="Es. Contratti e Accordi"
                class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              />
              <p v-if="createForm.errors.name" class="text-xs text-red-600 mt-1">{{ createForm.errors.name }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">
                Descrizione <span class="text-slate-400 font-normal">(opzionale)</span>
              </label>
              <textarea
                v-model="createForm.description"
                rows="3"
                placeholder="A cosa servono i documenti di questa categoria..."
                class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none resize-none"
              />
              <p v-if="createForm.errors.description" class="text-xs text-red-600 mt-1">{{ createForm.errors.description }}</p>
            </div>

            <button
              type="submit"
              :disabled="createForm.processing"
              class="w-full bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60 flex items-center justify-center gap-2"
            >
              <svg v-if="createForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              Aggiungi categoria
            </button>
          </form>
        </div>

      </div>
    </div>

    <!-- ── Modale modifica ───────────────────────────────── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
        <div v-if="editingCat" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
          <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 scale-95" leave-active-class="transition duration-150" leave-to-class="opacity-0 scale-95">
            <div v-if="editingCat" class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>

              <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                  <p class="text-sm font-semibold text-slate-800">Modifica categoria</p>
                  <p class="text-xs text-slate-400">{{ editingCat.name }}</p>
                </div>
                <button @click="closeEdit" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <form @submit.prevent="submitEdit" class="px-6 py-5 space-y-4">
                <div>
                  <label class="block text-xs font-medium text-slate-700 mb-1">Nome</label>
                  <input
                    v-model="editForm.name"
                    type="text"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                  />
                  <p v-if="editForm.errors.name" class="text-xs text-red-600 mt-1">{{ editForm.errors.name }}</p>
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-700 mb-1">
                    Descrizione <span class="text-slate-400 font-normal">(opzionale)</span>
                  </label>
                  <textarea
                    v-model="editForm.description"
                    rows="3"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none resize-none"
                  />
                  <p v-if="editForm.errors.description" class="text-xs text-red-600 mt-1">{{ editForm.errors.description }}</p>
                </div>

                <div class="flex gap-3 pt-1">
                  <button
                    type="submit"
                    :disabled="editForm.processing"
                    class="flex-1 bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60 flex items-center justify-center gap-2"
                  >
                    <svg v-if="editForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    Salva
                  </button>
                  <button type="button" @click="closeEdit" class="px-4 py-2.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                    Annulla
                  </button>
                </div>
              </form>

            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

  </SuperadminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'

interface Category {
  id: number
  name: string
  description: string | null
  allegati_count: number
}

defineProps<{ categories: Category[] }>()

// ── Create ────────────────────────────────────────────────
const createForm = useForm({ name: '', description: '' })
function submitCreate() {
  createForm.post(route('superadmin.document-categories.store'), {
    onSuccess: () => createForm.reset(),
  })
}

// ── Edit ──────────────────────────────────────────────────
const editingCat = ref<Category | null>(null)
const editForm   = useForm({ name: '', description: '' })

function openEdit(cat: Category) {
  editingCat.value      = cat
  editForm.name         = cat.name
  editForm.description  = cat.description ?? ''
  editForm.clearErrors()
}

function closeEdit() {
  editingCat.value = null
  editForm.reset()
}

function submitEdit() {
  if (!editingCat.value) return
  editForm.patch(route('superadmin.document-categories.update', editingCat.value.id), {
    onSuccess: () => closeEdit(),
  })
}

// ── Delete ────────────────────────────────────────────────
function confirmDelete(cat: Category) {
  if (!confirm(`Eliminare la categoria "${cat.name}"? I file associati perderanno la categoria.`)) return
  router.delete(route('superadmin.document-categories.destroy', cat.id))
}
</script>
