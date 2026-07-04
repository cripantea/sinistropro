<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <Link :href="route('pratiche.index')" class="text-sm text-gray-500 hover:text-gray-700 transition shrink-0">← Lista</Link>
          <h2 class="text-xl font-semibold text-gray-800 leading-tight truncate">
            Pratica <span class="font-mono text-indigo-600">#{{ pratica.id }}</span>
          </h2>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <button
            v-if="moduleTemplates.length > 0"
            type="button"
            @click="moduleModalOpen = true"
            class="inline-flex items-center gap-1.5 border border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 text-xs font-medium px-3 py-1.5 rounded-lg transition"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Compila Modulo
          </button>
          <Link
            :href="route('pratiche.edit', pratica.id)"
            class="text-xs border border-gray-300 text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition"
          >Modifica</Link>
          <a
            :href="route('pratiche.export-pdf', pratica.id)"
            target="_blank"
            class="inline-flex items-center gap-1.5 bg-red-600 text-white text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-red-700 transition"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Scarica Pacchetto PDF
          </a>
        </div>
      </div>
    </template>

    <!-- Flash messages -->
    <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
      <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 mx-4 mt-4 rounded">
        {{ flash.success }}
      </div>
    </Transition>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- Summary bar -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <div>
          <span class="text-gray-400 text-xs uppercase tracking-wide">Creata da</span>
          <p class="font-medium text-gray-800">{{ pratica.utente_creatore?.name ?? '—' }}</p>
        </div>
        <div>
          <span class="text-gray-400 text-xs uppercase tracking-wide">Data apertura</span>
          <p class="font-medium text-gray-800">{{ formatDate(pratica.created_at) }}</p>
        </div>
        <div>
          <span class="text-gray-400 text-xs uppercase tracking-wide">Prossimo avviso</span>
          <p :class="isOverdue(pratica.data_prossimo_avviso) ? 'text-red-600 font-semibold' : 'font-medium text-gray-800'">
            {{ pratica.data_prossimo_avviso ? formatDate(pratica.data_prossimo_avviso) : '—' }}
          </p>
        </div>
        <div class="ml-auto">
          <span class="text-gray-400 text-xs uppercase tracking-wide">Stato attuale</span>
          <div class="mt-0.5">
            <span
              v-if="pratica.current_status"
              class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold"
              :style="{ backgroundColor: pratica.current_status.color + '22', color: pratica.current_status.color }"
            >
              {{ pratica.current_status.name }}
            </span>
            <span v-else class="text-gray-400 text-xs italic">Nessuno stato</span>
          </div>
        </div>
      </div>

      <!-- Main 2-column layout -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        <!-- LEFT: Campi custom + cambio stato rapido -->
        <div class="space-y-5">

          <!-- Campi custom (read-only) -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Dati della pratica</h3>
            <dl class="space-y-3">
              <template v-if="schema.length > 0">
                <div v-for="field in schema" :key="field.name" class="flex flex-col">
                  <dt class="text-xs text-gray-400 uppercase tracking-wide">{{ field.label }}</dt>
                  <dd class="mt-0.5 text-sm font-medium text-gray-800">
                    <template v-if="field.type === 'boolean'">
                      <span :class="customFields[field.name] ? 'text-green-600' : 'text-gray-400'">
                        {{ customFields[field.name] ? 'Sì' : 'No' }}
                      </span>
                    </template>
                    <template v-else>
                      {{ customFields[field.name] ?? '—' }}
                    </template>
                  </dd>
                </div>
              </template>
              <p v-else class="text-sm text-gray-400 italic">Nessun campo personalizzato configurato.</p>
            </dl>
          </div>

          <!-- Cambio stato rapido -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Cambia stato</h3>
            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="status in pratica.tenant.statuses"
                :key="status.id"
                type="button"
                :disabled="statusForm.processing"
                @click="changeStatus(status.id)"
                :class="[
                  'flex items-center gap-2 px-3 py-2.5 rounded-lg border text-sm font-medium transition',
                  selectedStatusId === status.id
                    ? 'ring-2 ring-offset-1 border-transparent shadow-sm'
                    : 'border-gray-200 hover:border-gray-300 bg-white',
                  statusForm.processing ? 'opacity-60 cursor-wait' : ''
                ]"
                :style="selectedStatusId === status.id ? { boxShadow: `0 0 0 2px ${status.color}55`, borderColor: status.color } : {}"
              >
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: status.color }"/>
                {{ status.name }}
              </button>
            </div>
            <p v-if="statusForm.errors.current_status_id" class="text-xs text-red-600 mt-2">
              {{ statusForm.errors.current_status_id }}
            </p>
          </div>

        </div>

        <!-- RIGHT: Note / feed -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col">
          <h3 class="text-sm font-semibold text-gray-700 mb-4">Note</h3>

          <!-- Feed cronologico -->
          <div class="flex-1 overflow-y-auto max-h-96 space-y-4 mb-4 pr-1">
            <p v-if="pratica.note.length === 0" class="text-sm text-gray-400 italic text-center py-6">
              Nessuna nota ancora.
            </p>
            <div v-for="nota in pratica.note" :key="nota.id" class="flex gap-3">
              <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-xs font-bold">
                {{ initials(nota.user?.name) }}
              </div>
              <div class="flex-1 bg-gray-50 rounded-xl px-3.5 py-2.5">
                <div class="flex items-center justify-between gap-2 mb-1">
                  <span class="text-xs font-semibold text-gray-700">{{ nota.user?.name ?? 'Utente eliminato' }}</span>
                  <span class="text-xs text-gray-400">{{ formatDatetime(nota.created_at) }}</span>
                </div>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ nota.nota }}</p>
                <div class="mt-1.5 text-right">
                  <button
                    v-if="canDeleteNota(nota)"
                    @click="deleteNota(nota.id)"
                    class="text-xs text-red-400 hover:text-red-600 transition"
                  >Elimina</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Nuova nota -->
          <form @submit.prevent="submitNota" class="border-t border-gray-100 pt-4">
            <textarea
              v-model="notaForm.nota"
              rows="3"
              placeholder="Scrivi una nota…"
              class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 resize-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
            />
            <p v-if="notaForm.errors.nota" class="text-xs text-red-600 mt-1">{{ notaForm.errors.nota }}</p>
            <button
              type="submit"
              :disabled="notaForm.processing || !notaForm.nota.trim()"
              class="mt-2 w-full bg-indigo-600 text-white text-sm font-medium py-2 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50"
            >
              Aggiungi nota
            </button>
          </form>
        </div>

      </div>

      <!-- WHATSAPP -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <!-- Header -->
        <div class="flex items-center gap-3 px-5 py-3.5 bg-[#075E54]">
          <svg class="w-5 h-5 text-white shrink-0" viewBox="0 0 24 24" fill="currentColor">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
          </svg>
          <span class="text-white font-semibold text-sm">Comunicazione WhatsApp</span>
        </div>

        <div class="p-5">
          <!-- Phone input row -->
          <div class="flex items-center gap-3 mb-4">
            <label class="text-xs font-medium text-gray-500 shrink-0 w-28">Numero cliente</label>
            <input
              v-model="waPhone"
              type="tel"
              placeholder="+39 333 1234567"
              class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none"
            />
            <span v-if="waPhoneDetected" class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full shrink-0">Auto</span>
          </div>

          <!-- Chat mockup -->
          <div
            class="rounded-xl overflow-hidden border border-gray-200"
            style="background-color:#e5ddd5; background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAABESURBVDiNY2AYBYMHMDIy/mdiYPiPxGZiYGD4TwIGBGBgYGD4TwIGJGA0BsYeGAVDGIyKIQxGxRAGo2IIg1ExBAAAyv4FxPMAAAAASUVORK5CYII=')"
          >
            <!-- Fake top bar -->
            <div class="flex items-center gap-2.5 px-3 py-2 bg-[#075E54]">
              <div class="w-7 h-7 rounded-full bg-gray-300 shrink-0"/>
              <div>
                <p class="text-white text-xs font-semibold leading-tight">{{ contactName }}</p>
                <p class="text-green-200 text-[10px]">online</p>
              </div>
            </div>

            <!-- Messages area -->
            <div class="px-4 py-3 min-h-[120px] flex flex-col justify-end space-y-2">
              <!-- Outgoing message bubble (preview) -->
              <div class="flex justify-end">
                <div class="bg-[#dcf8c6] rounded-xl rounded-tr-sm px-3 py-2 max-w-[80%] shadow-sm">
                  <p class="text-sm text-gray-800 whitespace-pre-wrap break-words leading-relaxed">
                    {{ waMessage || '…' }}
                  </p>
                  <div class="flex items-center justify-end gap-0.5 mt-1">
                    <span class="text-[10px] text-gray-400">{{ currentTime }}</span>
                    <svg class="w-3.5 h-3.5 text-blue-500 ml-0.5" viewBox="0 0 16 11" fill="currentColor">
                      <path d="M11.071.653a.75.75 0 0 1 .025 1.06l-6.5 7a.75.75 0 0 1-1.08.013L.97 6.09a.75.75 0 1 1 1.06-1.062l2.051 2.05 5.93-6.4a.75.75 0 0 1 1.06-.025zm3 0a.75.75 0 0 1 .025 1.06l-6.5 7a.75.75 0 0 1-1.085.003l-.725-.73a.75.75 0 1 1 1.062-1.06l.193.195 5.97-6.443a.75.75 0 0 1 1.06-.025z"/>
                    </svg>
                  </div>
                </div>
              </div>
            </div>

            <!-- Input area mockup -->
            <div class="flex items-end gap-2 px-3 py-2 bg-[#f0f0f0] border-t border-gray-200">
              <textarea
                v-model="waMessage"
                rows="2"
                placeholder="Scrivi un messaggio…"
                class="flex-1 text-sm bg-white border-0 rounded-xl px-3 py-2 resize-none focus:ring-2 focus:ring-green-500 outline-none"
              />
              <button
                @click="openWhatsApp"
                :disabled="!waPhone.trim() || !waMessage.trim()"
                class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition"
                :class="waPhone.trim() && waMessage.trim()
                  ? 'bg-[#25D366] hover:bg-[#20bf59] text-white shadow'
                  : 'bg-gray-300 text-gray-400 cursor-not-allowed'"
                title="Invia su WhatsApp"
              >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
              </button>
            </div>
          </div>

          <p class="text-xs text-gray-400 mt-2 text-center">
            Apre WhatsApp Web in una nuova scheda con il messaggio precompilato.
          </p>
        </div>
      </div>

      <!-- ALLEGATI — griglia per categoria -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-gray-700">Allegati</h3>
          <span class="text-xs text-gray-400">{{ allegatiList.length }} file totali</span>
        </div>

        <!-- Hidden shared file input -->
        <input ref="tileFileInput" type="file" multiple class="hidden" @change="handleTileFileInput"/>

        <!-- Per-category tile grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 mb-4">

          <!-- One tile per enabled category -->
          <div
            v-for="cat in categories"
            :key="cat.id"
            class="rounded-xl border-2 border-dashed transition-all overflow-hidden"
            :class="tileDragOver[cat.id] ? 'border-indigo-400 bg-indigo-50 shadow-md' : 'border-gray-200 hover:border-gray-300 bg-gray-50 hover:bg-gray-100'"
            @dragover.prevent="tileDragOver[cat.id] = true"
            @dragleave.prevent="tileDragOver[cat.id] = false"
            @drop.prevent="handleTileDrop($event, cat.id)"
          >
            <!-- Tile header — click to browse -->
            <button
              type="button"
              class="w-full p-3 text-left flex items-start gap-3 cursor-pointer"
              @click="openTilePicker(cat.id)"
            >
              <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-gray-700 leading-tight">{{ cat.name }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5">
                  {{ filesForCategory(cat.id).length }} file · max {{ cat.max_file_size_mb }} MB
                </p>
              </div>
            </button>

            <!-- Per-tile upload queue -->
            <div v-if="queueForCategory(cat.id).length > 0" class="px-3 pb-2 space-y-1">
              <div v-for="item in queueForCategory(cat.id)" :key="item.id" class="flex items-center gap-2 text-xs">
                <span class="flex-1 truncate text-gray-600">{{ item.name }}</span>
                <div class="w-16 bg-gray-200 rounded-full h-1 overflow-hidden shrink-0">
                  <div class="h-full rounded-full transition-all" :class="item.error ? 'bg-red-500' : 'bg-indigo-500'" :style="{ width: `${item.progress}%` }"/>
                </div>
                <span v-if="item.error" class="text-red-500 shrink-0">!</span>
                <span v-else-if="item.progress < 100" class="text-gray-400 tabular-nums shrink-0">{{ item.progress }}%</span>
                <span v-else class="text-green-600 shrink-0">✓</span>
              </div>
            </div>

            <!-- Files in this category -->
            <div v-if="filesForCategory(cat.id).length > 0" class="border-t border-gray-200 divide-y divide-gray-100">
              <div
                v-for="allegato in filesForCategory(cat.id)"
                :key="allegato.id"
                class="flex items-center gap-2 px-3 py-2 hover:bg-white transition text-xs"
              >
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="flex-1 text-gray-700 truncate">{{ allegato.nome_file }}</span>
                <button @click="downloadAllegato(allegato.id)" class="text-indigo-500 hover:underline shrink-0">↓</button>
                <button @click="deleteAllegato(allegato.id)" class="text-red-400 hover:text-red-600 shrink-0">✕</button>
              </div>
            </div>
          </div>

          <!-- "Altri Documenti" tile (uncategorized) -->
          <div
            class="rounded-xl border-2 border-dashed transition-all overflow-hidden"
            :class="tileDragOver['none'] ? 'border-gray-400 bg-gray-100 shadow-md' : 'border-gray-200 hover:border-gray-300 bg-gray-50 hover:bg-gray-100'"
            @dragover.prevent="tileDragOver['none'] = true"
            @dragleave.prevent="tileDragOver['none'] = false"
            @drop.prevent="handleTileDrop($event, null)"
          >
            <button
              type="button"
              class="w-full p-3 text-left flex items-start gap-3 cursor-pointer"
              @click="openTilePicker(null)"
            >
              <div class="w-8 h-8 rounded-lg bg-gray-200 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-gray-600 leading-tight">Altri Documenti</p>
                <p class="text-[11px] text-gray-400 mt-0.5">{{ filesForCategory(null).length }} file · senza categoria</p>
              </div>
            </button>

            <!-- Per-tile queue for null category -->
            <div v-if="queueForCategory(null).length > 0" class="px-3 pb-2 space-y-1">
              <div v-for="item in queueForCategory(null)" :key="item.id" class="flex items-center gap-2 text-xs">
                <span class="flex-1 truncate text-gray-600">{{ item.name }}</span>
                <div class="w-16 bg-gray-200 rounded-full h-1 overflow-hidden shrink-0">
                  <div class="h-full rounded-full transition-all" :class="item.error ? 'bg-red-500' : 'bg-gray-500'" :style="{ width: `${item.progress}%` }"/>
                </div>
                <span v-if="item.error" class="text-red-500 shrink-0">!</span>
                <span v-else-if="item.progress < 100" class="text-gray-400 tabular-nums shrink-0">{{ item.progress }}%</span>
                <span v-else class="text-green-600 shrink-0">✓</span>
              </div>
            </div>

            <div v-if="filesForCategory(null).length > 0" class="border-t border-gray-200 divide-y divide-gray-100">
              <div
                v-for="allegato in filesForCategory(null)"
                :key="allegato.id"
                class="flex items-center gap-2 px-3 py-2 hover:bg-white transition text-xs"
              >
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="flex-1 text-gray-700 truncate">{{ allegato.nome_file }}</span>
                <button @click="downloadAllegato(allegato.id)" class="text-indigo-500 hover:underline shrink-0">↓</button>
                <button @click="deleteAllegato(allegato.id)" class="text-red-400 hover:text-red-600 shrink-0">✕</button>
              </div>
            </div>
          </div>

        </div>

        <p v-if="allegatiList.length === 0 && uploadQueue.length === 0" class="text-sm text-gray-400 italic text-center py-2">
          Clicca su una categoria o trascina i file nel riquadro corrispondente.
        </p>
      </div>

    </div>

    <!-- Modulo dinamico modal -->
    <ModuleFormModal
      :show="moduleModalOpen"
      :pratica-id="pratica.id"
      :templates="moduleTemplates"
      :pratica-modules="praticaModules"
      @close="moduleModalOpen = false"
      @saved="onModuleSaved"
    />

  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { Link, useForm, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ModuleFormModal from '@/Components/ModuleFormModal.vue'
import type { PageProps } from '@/types'

// ----- Types -----
interface FieldSchema { name: string; label: string; type: 'text' | 'date' | 'number' | 'boolean'; required?: boolean; options?: string[] }

interface ModuleTemplate {
  id: number
  name: string
  fields_schema: FieldSchema[]
  pdf_template_s3_key: string | null
}

interface PraticaModule {
  id: number
  module_template_id: number
  values: Record<string, unknown>
}
interface TenantStatus { id: number; name: string; color: string }
interface Nota { id: number; nota: string; user: { id: number; name: string } | null; created_at: string }
interface DocumentCategory { id: number; name: string; max_file_size_mb: number }
interface Allegato {
  id: number
  nome_file: string
  created_at: string
  document_category_id: number | null
  category: { id: number; name: string } | null
}

interface Pratica {
  id: number
  custom_fields: Record<string, string | boolean> | null
  data_prossimo_avviso: string | null
  created_at: string
  current_status: TenantStatus | null
  current_status_id: number | null
  utente_creatore: { id: number; name: string; email: string } | null
  tenant: { id: number; settings: { custom_fields_schema: FieldSchema[] } | null; statuses: TenantStatus[] }
  note: Nota[]
  allegati: Allegato[]
}

const props   = defineProps<{
  pratica: Pratica
  categories: DocumentCategory[]
  moduleTemplates: ModuleTemplate[]
  praticaModules: PraticaModule[]
}>()
const page    = usePage<PageProps>()
const flash   = computed(() => page.props.flash)
const authUser = computed(() => page.props.auth.user)

// ----- Custom fields -----
const schema       = computed<FieldSchema[]>(() => props.pratica.tenant.settings?.custom_fields_schema ?? [])
const customFields = computed(() => props.pratica.custom_fields ?? {})

// ----- Status change -----
const selectedStatusId = ref<number | null>(props.pratica.current_status_id)
const statusForm = useForm({ current_status_id: props.pratica.current_status_id })

function changeStatus(id: number) {
  if (selectedStatusId.value === id) return
  selectedStatusId.value = id
  statusForm.current_status_id = id
  statusForm.patch(route('pratiche.update-status', props.pratica.id), { preserveScroll: true })
}

// ----- Note -----
const notaForm = useForm({ nota: '' })

function submitNota() {
  notaForm.post(route('pratiche.note.store', props.pratica.id), {
    preserveScroll: true,
    onSuccess: () => notaForm.reset(),
  })
}

function deleteNota(id: number) {
  if (!confirm('Eliminare questa nota?')) return
  router.delete(route('pratiche.note.destroy', [props.pratica.id, id]), { preserveScroll: true })
}

function canDeleteNota(nota: Nota) {
  return nota.user?.id === authUser.value.id || authUser.value.role === 'tenant-admin'
}

// ----- Moduli dinamici -----
const moduleModalOpen = ref(false)

function onModuleSaved(allegato: Allegato | null, warning: string | null) {
  if (allegato) {
    allegatiList.value.push(allegato)
  }
  if (!warning) {
    moduleModalOpen.value = false
  }
}

// ----- Allegati -----
const allegatiList = ref<Allegato[]>([...props.pratica.allegati])

// Per-tile drag state keyed by category id or 'none'
const tileDragOver   = reactive<Record<string | number, boolean>>({})
const tileFileInput  = ref<HTMLInputElement | null>(null)
const activeCatId    = ref<number | null>(null)

interface UploadItem { id: number; name: string; progress: number; error: boolean; categoryId: number | null }
const uploadQueue = ref<UploadItem[]>([])
let _uid = 0

function filesForCategory(catId: number | null): Allegato[] {
  return allegatiList.value.filter(a => a.document_category_id === catId)
}

function queueForCategory(catId: number | null): UploadItem[] {
  return uploadQueue.value.filter(q => q.categoryId === catId)
}

function openTilePicker(catId: number | null) {
  activeCatId.value = catId
  tileFileInput.value?.click()
}

function handleTileFileInput(e: Event) {
  Array.from((e.target as HTMLInputElement).files ?? []).forEach(f => uploadFile(f, activeCatId.value))
  if (tileFileInput.value) tileFileInput.value.value = ''
}

function handleTileDrop(e: DragEvent, catId: number | null) {
  tileDragOver[catId ?? 'none'] = false
  Array.from(e.dataTransfer?.files ?? []).forEach(f => uploadFile(f, catId))
}

async function uploadFile(file: File, categoryId: number | null) {
  const item: UploadItem = { id: ++_uid, name: file.name, progress: 0, error: false, categoryId }
  uploadQueue.value.push(item)

  const data = new FormData()
  data.append('file', file)
  if (categoryId !== null) data.append('document_category_id', String(categoryId))

  try {
    const resp = await axios.post<{ allegato: Allegato }>(
      route('allegati.store', props.pratica.id),
      data,
      { onUploadProgress: (e) => { item.progress = e.total ? Math.round((e.loaded * 100) / e.total) : 50 } }
    )
    item.progress = 100
    allegatiList.value.push(resp.data.allegato)
  } catch (err: unknown) {
    item.error = true
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    if (msg) alert(msg)
  }
}

async function downloadAllegato(id: number) {
  const resp = await axios.get<{ url: string }>(route('allegati.download', id))
  window.open(resp.data.url, '_blank')
}

function deleteAllegato(id: number) {
  if (!confirm('Eliminare questo allegato?')) return
  router.delete(route('allegati.web.destroy', id), {
    preserveScroll: true,
    onSuccess: () => { allegatiList.value = allegatiList.value.filter(a => a.id !== id) },
  })
}

// ----- WhatsApp -----
const PHONE_KEYS = ['telefono', 'tel', 'phone', 'cellulare', 'mobile', 'numero_tel']

const waPhoneDetected = computed(() => {
  if (!props.pratica.custom_fields) return false
  return Object.keys(props.pratica.custom_fields).some(k =>
    PHONE_KEYS.some(p => k.toLowerCase().includes(p))
  )
})

const _detectedPhone = (() => {
  if (!props.pratica.custom_fields) return ''
  const entry = Object.entries(props.pratica.custom_fields).find(([k]) =>
    PHONE_KEYS.some(p => k.toLowerCase().includes(p))
  )
  return entry ? String(entry[1]) : ''
})()
const waPhone = ref(_detectedPhone)

const waMessage = ref('')

const contactName = computed(() => {
  if (!props.pratica.custom_fields) return `Pratica #${props.pratica.id}`
  const nameKeys = ['nome', 'cliente', 'contraente', 'assicurato', 'nominativo']
  const entry = Object.entries(props.pratica.custom_fields).find(([k]) =>
    nameKeys.some(p => k.toLowerCase().includes(p))
  )
  return entry ? String(entry[1]) : `Pratica #${props.pratica.id}`
})

const currentTime = computed(() => {
  const now = new Date()
  return now.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })
})

function openWhatsApp() {
  const digits = waPhone.value.replace(/\D/g, '')
  if (!digits || !waMessage.value.trim()) return
  const url = `https://wa.me/${digits}?text=${encodeURIComponent(waMessage.value)}`
  window.open(url, '_blank', 'noopener,noreferrer')
}

// ----- Helpers -----
function formatDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('it-IT')
}
function formatDatetime(iso: string): string {
  return new Date(iso).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function isOverdue(iso: string | null): boolean {
  if (!iso) return false
  return new Date(iso) < new Date()
}
function initials(name?: string | null): string {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase()
}
</script>
