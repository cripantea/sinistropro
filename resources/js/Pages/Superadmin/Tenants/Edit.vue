<template>
  <SuperadminLayout :title="`Modifica Tenant: ${tenant.name}`">

    <!-- ── Tab bar ──────────────────────────────────────────────────────────── -->
    <div class="px-6 pt-6">
      <nav class="flex gap-1 bg-slate-100 rounded-xl p-1 max-w-4xl">
        <button
          v-for="tab in TABS"
          :key="tab.id"
          type="button"
          @click="activeTab = tab.id"
          :class="[
            'flex-1 text-sm font-medium py-2 px-3 rounded-lg transition whitespace-nowrap',
            activeTab === tab.id
              ? 'bg-white text-slate-900 shadow-sm'
              : 'text-slate-500 hover:text-slate-700 hover:bg-slate-200/60'
          ]"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- ── Tab 1: Configurazione ─────────────────────────────────────────────── -->
    <form v-show="activeTab === 'config'" @submit.prevent="submit" class="p-6 max-w-3xl space-y-8">

      <FormSection title="Informazioni generali">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="field-label">Nome azienda / tenant *</label>
            <input v-model="form.name" type="text" class="field-input" />
            <FieldError :message="form.errors.name" />
          </div>
          <div>
            <label class="field-label">Giorni preavviso default *</label>
            <input v-model.number="form.default_notice_days" type="number" min="1" max="365" class="field-input" />
            <FieldError :message="form.errors.default_notice_days" />
          </div>
        </div>
      </FormSection>

      <FormSection title="Campi personalizzati dei sinistri">
        <div class="space-y-3">
          <TransitionGroup name="list" tag="div" class="space-y-3">
            <div v-for="(field, i) in form.custom_fields_schema" :key="field._uid" class="flex items-start gap-3 bg-slate-50 border border-slate-200 rounded-lg p-3">
              <div class="grid grid-cols-3 gap-3 flex-1">
                <div>
                  <label class="field-label text-xs">Nome tecnico</label>
                  <input v-model="field.name" type="text" class="field-input font-mono text-sm" @input="field.name = field.name.toLowerCase().replace(/[^a-z0-9_]/g, '_')" />
                  <FieldError :message="form.errors[`custom_fields_schema.${i}.name`]" />
                </div>
                <div>
                  <label class="field-label text-xs">Etichetta</label>
                  <input v-model="field.label" type="text" class="field-input" />
                </div>
                <div>
                  <label class="field-label text-xs">Tipo</label>
                  <select v-model="field.type" class="field-input">
                    <option value="text">Testo</option>
                    <option value="date">Data</option>
                    <option value="number">Numero</option>
                    <option value="boolean">Sì / No</option>
                  </select>
                </div>
              </div>
              <label class="mt-5 flex items-center gap-1.5 text-xs text-slate-600 shrink-0 cursor-pointer" title="Il campo diventa obbligatorio nel form del sinistro">
                <input v-model="field.required" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                Obbligatorio
              </label>
              <button type="button" @click="removeField(i)" class="mt-5 text-red-400 hover:text-red-600 transition p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </TransitionGroup>
        </div>
        <button type="button" @click="addField" class="mt-4 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Aggiungi campo
        </button>
      </FormSection>

      <FormSection title="Stati dei sinistri">
        <div class="space-y-2">
          <TransitionGroup name="list" tag="div" class="space-y-2">
            <div v-for="(status, i) in form.statuses" :key="status._uid" class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5">
              <span class="inline-block w-3 h-3 rounded-full shrink-0 border border-white shadow" :style="{ backgroundColor: status.color }" />
              <input v-model="status.name" type="text" class="field-input flex-1 py-1.5" placeholder="Nome stato" />
              <input v-model="status.color" type="color" class="w-9 h-9 rounded cursor-pointer border border-slate-300 p-0.5" />
              <label class="flex items-center gap-1.5 text-xs text-slate-600 whitespace-nowrap cursor-pointer select-none">
                <input v-model="status.is_closed" type="checkbox" class="rounded border-slate-300 text-red-500 focus:ring-red-400" />
                Chiuso
              </label>
              <label class="flex items-center gap-1.5 text-xs text-slate-600 whitespace-nowrap cursor-pointer select-none">
                <input :checked="status.is_initial" @change="setInitialStatus(i)" type="radio" name="initial-status" class="border-slate-300 text-indigo-600 focus:ring-indigo-400" />
                Iniziale
              </label>
              <button type="button" @click="removeStatus(i)" class="text-red-400 hover:text-red-600 transition p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </TransitionGroup>
        </div>
        <button type="button" @click="addStatus" class="mt-4 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Aggiungi stato
        </button>
      </FormSection>

      <div class="flex items-center gap-4 pt-2">
        <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60">
          <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
          Salva modifiche
        </button>
        <Link :href="route('superadmin.tenants.index')" class="text-sm text-slate-500 hover:underline">Annulla</Link>
      </div>

    </form>

    <!-- ── Tab 2: Categorie Documenti ─────────────────────────────────────────── -->
    <form v-show="activeTab === 'categories'" @submit.prevent="submitCategories" class="p-6 max-w-3xl mt-4">
      <FormSection title="Categorie Documenti">
        <p class="text-xs text-slate-500 mb-4">Abilita le categorie disponibili per questo tenant e imposta il limite di dimensione per ogni categoria.</p>

        <div class="space-y-2">
          <div
            v-for="cat in catForm.categories"
            :key="cat.id"
            class="flex items-center gap-4 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3"
          >
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input type="checkbox" v-model="cat.is_enabled" class="sr-only peer" />
              <div class="w-9 h-5 bg-slate-300 peer-checked:bg-indigo-600 rounded-full transition peer-focus:ring-2 peer-focus:ring-indigo-400 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
            </label>

            <span class="flex-1 text-sm font-medium text-slate-700" :class="!cat.is_enabled ? 'opacity-40' : ''">{{ cat.name }}</span>

            <div class="flex items-center gap-1.5 shrink-0" :class="!cat.is_enabled ? 'opacity-40' : ''">
              <label class="text-xs text-slate-500 shrink-0">Max</label>
              <input
                v-model.number="cat.max_file_size_mb"
                type="number" min="1" max="500"
                :disabled="!cat.is_enabled"
                class="w-16 text-sm text-center border border-slate-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-indigo-500 outline-none disabled:bg-slate-100 disabled:cursor-not-allowed"
              />
              <span class="text-xs text-slate-400 shrink-0">MB</span>
            </div>
          </div>
        </div>

        <button
          type="submit"
          :disabled="catForm.processing"
          class="mt-5 inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60"
        >
          <svg v-if="catForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
          Salva categorie
        </button>
      </FormSection>
    </form>

    <!-- ── Tab 3: Automazioni Workflow ───────────────────────────────────────── -->
    <div v-show="activeTab === 'automations'" class="p-6 max-w-5xl">

      <div class="flex items-center justify-between mb-5">
        <div>
          <h3 class="text-base font-semibold text-slate-900">Automazioni Workflow</h3>
          <p class="text-xs text-slate-500 mt-0.5">Azioni automatiche eseguite al cambio di stato dei sinistri.</p>
        </div>
        <button type="button" @click="openCreateAuto" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Nuova Automazione
        </button>
      </div>

      <div v-if="automations.length > 0" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Nome</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Stato Trigger</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Canale</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Destinatario</th>
              <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Attivo</th>
              <th class="w-24"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="auto in automations" :key="auto.id" class="hover:bg-slate-50/70 transition">
              <td class="px-4 py-3 font-medium text-slate-800">{{ auto.name }}</td>
              <td class="px-4 py-3">
                <span v-if="auto.status" class="inline-flex items-center gap-1.5 text-xs text-slate-700">
                  <span class="inline-block w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: auto.status.color }"></span>
                  {{ auto.status.name }}
                </span>
                <span v-else class="text-xs text-slate-400 italic">Qualsiasi</span>
              </td>
              <td class="px-4 py-3">
                <span :class="channelBadgeClass(auto.channel)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">{{ channelLabel(auto.channel) }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">{{ recipientLabel(auto.recipient) }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <button type="button" @click="toggleIsActive(auto)" :class="['relative inline-flex h-5 w-9 items-center rounded-full transition focus:outline-none', auto.is_active ? 'bg-indigo-600' : 'bg-slate-300']">
                  <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition', auto.is_active ? 'translate-x-4' : 'translate-x-0.5']"></span>
                </button>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1.5 justify-end">
                  <button type="button" @click="openEditAuto(auto)" class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition" title="Modifica">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                  </button>
                  <button type="button" @click="deleteAutomation(auto)" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition" title="Elimina">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="bg-slate-50 border border-dashed border-slate-300 rounded-xl px-8 py-14 text-center">
        <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <p class="text-sm text-slate-500 font-medium">Nessuna automazione configurata</p>
        <p class="text-xs text-slate-400 mt-1">Clicca "Nuova Automazione" per aggiungerne una.</p>
      </div>
    </div>

    <!-- ── Tab 4: Template Moduli PDF ───────────────────────────────────────── -->
    <div v-show="activeTab === 'modules'" class="p-6 max-w-5xl">

      <div class="flex items-center justify-between mb-5">
        <div>
          <h3 class="text-base font-semibold text-slate-900">Template Moduli PDF</h3>
          <p class="text-xs text-slate-500 mt-0.5">Definisci i PDF matrice e i campi compilabili per ogni modulo sinistro.</p>
        </div>
        <button type="button" @click="openCreateModule" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Nuovo Template
        </button>
      </div>

      <div v-if="moduleTemplates.length > 0" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Nome</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Campi</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Categoria Output</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">PDF Matrice</th>
              <th class="w-24"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="tmpl in moduleTemplates" :key="tmpl.id" class="hover:bg-slate-50/70 transition">
              <td class="px-4 py-3 font-medium text-slate-800">{{ tmpl.name }}</td>
              <td class="px-4 py-3 text-slate-600">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                  {{ tmpl.fields_schema.length }} {{ tmpl.fields_schema.length === 1 ? 'campo' : 'campi' }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-600 text-xs">
                {{ tmpl.output_category?.name ?? '—' }}
              </td>
              <td class="px-4 py-3">
                <span v-if="tmpl.pdf_template_s3_key" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                  Configurato
                </span>
                <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                  Non configurato
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1.5 justify-end">
                  <button type="button" @click="openEditModule(tmpl)" class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition" title="Modifica">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                  </button>
                  <button type="button" @click="deleteModule(tmpl)" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition" title="Elimina">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="bg-slate-50 border border-dashed border-slate-300 rounded-xl px-8 py-14 text-center">
        <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm text-slate-500 font-medium">Nessun template configurato</p>
        <p class="text-xs text-slate-400 mt-1">Clicca "Nuovo Template" per caricarne uno.</p>
      </div>
    </div>

    <!-- ── Automation Modal ──────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
        <div v-if="autoModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" @click.self="closeAutoModal">
          <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 scale-95" leave-active-class="transition duration-150" leave-to-class="opacity-0 scale-95">
            <div v-if="autoModalOpen" class="bg-white rounded-2xl shadow-2xl w-full max-w-xl" @click.stop>

              <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="text-base font-semibold text-slate-900">{{ editingAutomation ? 'Modifica Automazione' : 'Nuova Automazione' }}</h3>
                <button type="button" @click="closeAutoModal" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>

              <form @submit.prevent="submitAuto" class="px-6 py-5 space-y-4 max-h-[68vh] overflow-y-auto">

                <div>
                  <label class="field-label">Nome automazione *</label>
                  <input v-model="autoForm.name" type="text" class="field-input" placeholder="Es: Email conferma ricezione" />
                  <FieldError :message="autoForm.errors.name" />
                </div>

                <div>
                  <label class="field-label">Stato trigger</label>
                  <select v-model="autoForm.tenant_status_id" class="field-input">
                    <option :value="null">— Qualsiasi stato —</option>
                    <option v-for="s in tenant.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                  <FieldError :message="autoForm.errors.tenant_status_id" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="field-label">Canale *</label>
                    <select v-model="autoForm.channel" class="field-input">
                      <option value="email">Email</option>
                      <option value="whatsapp">WhatsApp</option>
                      <option value="both">Email + WhatsApp</option>
                    </select>
                    <FieldError :message="autoForm.errors.channel" />
                  </div>
                  <div>
                    <label class="field-label">Destinatario *</label>
                    <select v-model="autoForm.recipient" class="field-input">
                      <option value="cliente">Cliente</option>
                      <option value="perito">Perito</option>
                      <option value="gestore">Gestore</option>
                    </select>
                    <FieldError :message="autoForm.errors.recipient" />
                  </div>
                </div>

                <div>
                  <label class="field-label">Messaggio *</label>
                  <textarea v-model="autoForm.message_template" rows="5" class="field-input resize-none" placeholder="Gentile {nome_cliente}, il sinistro #{numero_pratica} è passato allo stato {stato_corrente}."></textarea>
                  <p class="text-xs text-slate-400 mt-1">Variabili: <code class="bg-slate-100 px-1 rounded">{nome_cliente}</code> <code class="bg-slate-100 px-1 rounded ml-1">{numero_pratica}</code> <code class="bg-slate-100 px-1 rounded ml-1">{stato_corrente}</code> <code class="bg-slate-100 px-1 rounded ml-1">{link_documenti}</code></p>
                  <FieldError :message="autoForm.errors.message_template" />
                </div>

                <div v-if="allDocCategories.length > 0">
                  <label class="field-label">Categorie documenti da allegare</label>
                  <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-2 max-h-36 overflow-y-auto">
                    <label v-for="cat in allDocCategories" :key="cat.id" class="flex items-center gap-2 cursor-pointer">
                      <input
                        type="checkbox"
                        :checked="autoForm.document_category_ids.includes(cat.id)"
                        @change="(e) => toggleDocCategory(cat.id, (e.target as HTMLInputElement).checked)"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                      />
                      <span class="text-sm text-slate-700">{{ cat.name }}</span>
                    </label>
                  </div>
                </div>

                <div class="flex items-center gap-3 pt-1">
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="autoForm.is_active" class="sr-only peer" />
                    <div class="w-9 h-5 bg-slate-300 peer-checked:bg-indigo-600 rounded-full transition peer-focus:ring-2 peer-focus:ring-indigo-400 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                  </label>
                  <span class="text-sm font-medium text-slate-700">Automazione attiva</span>
                </div>

              </form>

              <div class="flex items-center gap-3 px-6 py-4 border-t border-slate-100">
                <button type="button" :disabled="autoForm.processing" @click="submitAuto" class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                  <svg v-if="autoForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                  {{ autoForm.processing ? 'Salvataggio...' : 'Salva automazione' }}
                </button>
                <button type="button" :disabled="autoForm.processing" @click="closeAutoModal" class="px-4 py-2.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition disabled:opacity-50">Annulla</button>
              </div>

            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Module Template Modal ─────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
        <div v-if="moduleModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" @click.self="closeModuleModal">
          <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 scale-95" leave-active-class="transition duration-150" leave-to-class="opacity-0 scale-95">
            <div v-if="moduleModalOpen" class="bg-white rounded-2xl shadow-2xl w-full transition-all duration-200" :class="coordEditorMode ? 'max-w-5xl' : 'max-w-2xl'" @click.stop>

              <!-- Header -->
              <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                  <h3 class="text-base font-semibold text-slate-900">{{ editingTemplate ? 'Modifica Template' : 'Nuovo Template Modulo PDF' }}</h3>
                  <p class="text-xs text-slate-400 mt-0.5">Carica il PDF matrice e definisci i campi compilabili.</p>
                </div>
                <button type="button" @click="closeModuleModal" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>

              <!-- Body -->
              <div class="px-6 py-5 space-y-5 overflow-y-auto" :class="coordEditorMode ? 'max-h-[84vh]' : 'max-h-[74vh]'">

                <!-- Nome + Categoria + Font size -->
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="field-label">Nome template *</label>
                    <input v-model="moduleForm.name" type="text" class="field-input" placeholder="Es: Verbale sopralluogo" />
                    <FieldError :message="moduleForm.errors.name" />
                  </div>
                  <div>
                    <label class="field-label">Categoria output</label>
                    <select v-model="moduleForm.output_document_category_id" class="field-input">
                      <option :value="null">— Nessuna —</option>
                      <option v-for="cat in allDocCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <FieldError :message="moduleForm.errors.output_document_category_id" />
                  </div>
                </div>

                <!-- Font size -->
                <div class="flex items-center gap-3">
                  <label class="field-label mb-0 shrink-0">Dimensione font PDF</label>
                  <div class="flex gap-1 flex-wrap">
                    <button
                      v-for="size in [7, 8, 9, 10, 11, 12, 14]"
                      :key="size"
                      type="button"
                      @click="moduleForm.font_size = size"
                      class="px-2.5 py-1 text-xs rounded border transition"
                      :class="moduleForm.font_size === size
                        ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                        : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
                    >{{ size }}pt</button>
                  </div>
                </div>

                <!-- PDF Upload + Genera Campi con IA -->
                <div>
                  <label class="field-label">PDF matrice</label>
                  <input ref="pdfFileInputRef" type="file" accept="application/pdf" class="hidden" @change="onPdfFileSelected" />

                  <div class="flex items-center gap-3 flex-wrap">
                    <button type="button" @click="pdfFileInputRef?.click()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition">
                      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                      {{ pdfFile ? pdfFile.name : 'Scegli file PDF' }}
                    </button>

                    <button
                      v-if="pdfFile"
                      type="button"
                      @click="runExtraction"
                      :disabled="extraction.loading"
                      class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                      :class="extraction.done ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-violet-600 text-white hover:bg-violet-700'"
                    >
                      <svg v-if="extraction.loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                      <svg v-else-if="extraction.done" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                      {{ extraction.loading ? 'Analisi PDF...' : extraction.done ? 'Rigenera Campi' : 'Genera Campi con IA' }}
                    </button>
                  </div>

                  <!-- Feedback IA -->
                  <p v-if="extraction.done && !extraction.loading" class="text-xs text-emerald-600 mt-2 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ moduleForm.fields_schema.length }} campi estratti — PDF caricato su S3
                  </p>
                  <p v-if="extraction.error" class="text-xs text-red-600 mt-2 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    {{ extraction.error }}
                  </p>
                  <!-- Edit mode: show existing PDF key -->
                  <p v-if="moduleForm.pdf_template_s3_key && !pdfFile" class="text-xs text-slate-500 mt-2 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    PDF configurato: {{ moduleForm.pdf_template_s3_key.split('/').pop() }}
                  </p>
                </div>

                <!-- Fields Schema Editor ─────────────────────────────────────── -->
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <label class="field-label mb-0">Schema Campi</label>
                    <div class="flex items-center gap-2">
                      <!-- Editor Coordinate: visible only when PDF + fields exist -->
                      <button
                        v-if="moduleForm.pdf_template_s3_key && moduleForm.fields_schema.length > 0"
                        type="button"
                        @click="toggleCoordMode"
                        class="text-xs font-medium transition flex items-center gap-1"
                        :class="coordEditorMode
                          ? 'text-emerald-700 hover:text-emerald-900'
                          : 'text-slate-500 hover:text-slate-700'"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        {{ coordEditorMode ? 'Chiudi Editor' : 'Editor Coordinate' }}
                      </button>
                      <!-- Vista JSON: hidden while coord editor is open -->
                      <button
                        v-if="!coordEditorMode"
                        type="button"
                        @click="toggleJsonMode"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ jsonViewMode ? 'Vista Visuale' : 'Vista JSON' }}
                      </button>
                    </div>
                  </div>

                  <!-- Visual Editor -->
                  <template v-if="!jsonViewMode && !coordEditorMode">
                    <div v-if="moduleForm.fields_schema.length > 0" class="space-y-2 mb-3">
                      <div v-for="(field, i) in moduleForm.fields_schema" :key="field._uid" class="grid grid-cols-[1fr_1fr_auto_auto_auto] gap-2 items-center bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
                        <div>
                          <input
                            v-model="field.name"
                            type="text"
                            placeholder="nome_tecnico"
                            @input="field.name = (field.name as string).toLowerCase().replace(/[^a-z0-9_]/g, '_')"
                            class="w-full text-xs font-mono border border-slate-300 rounded-md px-2 py-1.5 focus:ring-1 focus:ring-indigo-500 outline-none bg-white"
                          />
                        </div>
                        <div>
                          <input
                            v-model="field.label"
                            type="text"
                            placeholder="Etichetta"
                            class="w-full text-xs border border-slate-300 rounded-md px-2 py-1.5 focus:ring-1 focus:ring-indigo-500 outline-none bg-white"
                          />
                        </div>
                        <select v-model="field.type" class="text-xs border border-slate-300 rounded-md px-2 py-1.5 focus:ring-1 focus:ring-indigo-500 outline-none bg-white">
                          <option value="text">Testo</option>
                          <option value="date">Data</option>
                          <option value="number">Numero</option>
                          <option value="boolean">Sì/No</option>
                          <option value="select">Selezione</option>
                        </select>
                        <label class="flex items-center gap-1 text-xs text-slate-600 whitespace-nowrap cursor-pointer select-none">
                          <input v-model="field.required" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                          <span class="hidden sm:inline">Req.</span>
                        </label>
                        <button type="button" @click="removeModuleField(i)" class="text-red-400 hover:text-red-600 transition p-0.5">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                      </div>
                    </div>
                    <p v-else class="text-xs text-slate-400 italic mb-3 py-3 text-center bg-slate-50 border border-dashed border-slate-300 rounded-lg">
                      Nessun campo definito — carica un PDF e clicca "Genera Campi con IA", oppure aggiungili manualmente.
                    </p>
                    <button type="button" @click="addModuleField" class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                      Aggiungi campo manualmente
                    </button>
                  </template>

                  <!-- JSON Editor -->
                  <template v-else-if="jsonViewMode && !coordEditorMode">
                    <textarea
                      v-model="rawJson"
                      rows="12"
                      spellcheck="false"
                      class="w-full text-xs font-mono border border-slate-300 rounded-lg px-3 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-slate-950 text-emerald-300 resize-none"
                      placeholder='[{"name":"campo","label":"Etichetta","type":"text","required":true}]'
                    ></textarea>
                    <p v-if="jsonError" class="text-xs text-red-600 mt-1">{{ jsonError }}</p>
                    <p class="text-xs text-slate-400 mt-1">Modifica il JSON e clicca "Vista Visuale" per applicare le modifiche.</p>
                  </template>

                  <!-- Coord Editor -->
                  <template v-else-if="coordEditorMode">
                    <FieldCoordEditor
                      :fields="moduleForm.fields_schema"
                      :s3-key="moduleForm.pdf_template_s3_key || null"
                      :tenant-id="tenant.id"
                      @update:fields="moduleForm.fields_schema = $event"
                    />
                  </template>

                </div>
              </div>

              <!-- Footer -->
              <div class="flex items-center gap-3 px-6 py-4 border-t border-slate-100">
                <button
                  type="button"
                  :disabled="moduleForm.processing"
                  @click="saveModule"
                  class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="moduleForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                  {{ moduleForm.processing ? 'Salvataggio...' : 'Salva Template' }}
                </button>
                <button type="button" :disabled="moduleForm.processing" @click="closeModuleModal" class="px-4 py-2.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition disabled:opacity-50">
                  Annulla
                </button>
              </div>

            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

  </SuperadminLayout>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { useForm, usePage, Link, router } from '@inertiajs/vue3'
import axios from 'axios'

// Isolated instance — no Inertia global interceptors, so JSON responses from
// AJAX-only endpoints are not hijacked as Inertia navigations.
const http = axios.create()

import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'
import FormSection from '@/Components/Superadmin/FormSection.vue'
import FieldError from '@/Components/Superadmin/FieldError.vue'
import FieldCoordEditor from '@/Components/Superadmin/FieldCoordEditor.vue'

// ── Interfaces ───────────────────────────────────────────────────────────────

interface CustomField   { _uid: number; name: string; label: string; type: string; required: boolean }
interface StatusRow     { _uid: number; id?: number; name: string; color: string; is_closed: boolean; is_initial: boolean; order: number }
interface CategoryConfig { id: number; name: string; description: string | null; is_enabled: boolean; max_file_size_mb: number }
interface DocCategory   { id: number; name: string }

interface Automation {
  id: number; name: string; tenant_status_id: number | null; channel: string; recipient: string
  message_template: string; is_active: boolean; document_category_ids: number[]
  status: { id: number; name: string; color: string } | null
}

interface FieldSchema {
  name: string; label: string; type: string; required: boolean
  page?: number; x?: number; y?: number; w?: number
}
interface FieldSchemaRow extends FieldSchema { _uid: number }

interface ModuleTemplate {
  id: number; name: string; pdf_template_s3_key: string | null
  output_document_category_id: number | null; fields_schema: FieldSchema[]
  output_category: { id: number; name: string } | null
  font_size: number | null
}

interface TenantFull {
  id: number; name: string
  settings: { default_notice_days: number; custom_fields_schema: Omit<CustomField, '_uid'>[] } | null
  statuses: Omit<StatusRow, '_uid'>[]
}

// ── Props ────────────────────────────────────────────────────────────────────

const props = defineProps<{
  tenant: TenantFull
  categoriesConfig: CategoryConfig[]
  automations: Automation[]
  allDocCategories: DocCategory[]
  moduleTemplates: ModuleTemplate[]
}>()

// ── UID counter (shared across all lists) ────────────────────────────────────

let _uid = 0
const uid = () => ++_uid

// ── Tab navigation ───────────────────────────────────────────────────────────

const TABS = [
  { id: 'config',      label: 'Configurazione' },
  { id: 'categories',  label: 'Categorie Documenti' },
  { id: 'automations', label: 'Automazioni Workflow' },
  { id: 'modules',     label: 'Template Moduli PDF' },
] as const

type TabId = typeof TABS[number]['id']

function tabFromUrl(url: string): TabId {
  const search = url.includes('?') ? url.split('?')[1] : ''
  const tab = new URLSearchParams(search).get('tab')
  return (['config', 'categories', 'automations', 'modules'] as const).includes(tab as TabId)
    ? (tab as TabId)
    : 'config'
}

const page      = usePage()
const activeTab = ref<TabId>(tabFromUrl(page.url))
watch(() => page.url, (url) => { activeTab.value = tabFromUrl(url) })

// ── Configurazione form ──────────────────────────────────────────────────────

const form = useForm({
  name:                 props.tenant.name,
  default_notice_days:  props.tenant.settings?.default_notice_days ?? 30,
  custom_fields_schema: (props.tenant.settings?.custom_fields_schema ?? []).map(f => ({ ...f, _uid: uid() })) as CustomField[],
  statuses:             (props.tenant.statuses ?? []).map(s => ({ ...s, _uid: uid() })) as StatusRow[],
})

const PALETTE = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#6B7280']

function addField()             { form.custom_fields_schema.push({ _uid: uid(), name: '', label: '', type: 'text', required: false }) }
function removeField(i: number) { form.custom_fields_schema.splice(i, 1) }

function addStatus() {
  const color = PALETTE[form.statuses.length % PALETTE.length]
  const isFirst = form.statuses.length === 0
  form.statuses.push({ _uid: uid(), name: '', color, is_closed: false, is_initial: isFirst, order: form.statuses.length })
}
function removeStatus(i: number) { form.statuses.splice(i, 1) }
function setInitialStatus(i: number) {
  form.statuses.forEach((s, idx) => { s.is_initial = idx === i })
}

function submit() {
  form
    .transform((data) => ({
      ...data,
      custom_fields_schema: data.custom_fields_schema.map(({ _uid: _u, ...f }: CustomField) => f),
      statuses:             data.statuses.map(({ _uid: _u, ...s }: StatusRow) => s),
    }))
    .put(route('superadmin.tenants.update', props.tenant.id))
}

// ── Categorie ────────────────────────────────────────────────────────────────

const catForm = useForm({ categories: props.categoriesConfig.map(c => ({ ...c })) })

function submitCategories() {
  catForm.post(route('superadmin.tenants.document-categories.sync', props.tenant.id))
}

// ── Automazioni ──────────────────────────────────────────────────────────────

const autoModalOpen     = ref(false)
const editingAutomation = ref<Automation | null>(null)

const autoForm = useForm({
  name:                  '' as string,
  tenant_status_id:      null as number | null,
  channel:               'email' as string,
  recipient:             'cliente' as string,
  message_template:      '' as string,
  document_category_ids: [] as number[],
  is_active:             true as boolean,
})

function openCreateAuto() {
  editingAutomation.value = null
  autoForm.reset()
  autoForm.document_category_ids = []
  autoModalOpen.value = true
}
function openEditAuto(auto: Automation) {
  editingAutomation.value = auto
  autoForm.name                  = auto.name
  autoForm.tenant_status_id      = auto.tenant_status_id
  autoForm.channel               = auto.channel
  autoForm.recipient             = auto.recipient
  autoForm.message_template      = auto.message_template
  autoForm.document_category_ids = [...auto.document_category_ids]
  autoForm.is_active             = auto.is_active
  autoModalOpen.value = true
}
function closeAutoModal() {
  autoModalOpen.value = false
  editingAutomation.value = null
  autoForm.reset()
  autoForm.document_category_ids = []
}
function toggleDocCategory(id: number, checked: boolean) {
  autoForm.document_category_ids = checked
    ? [...autoForm.document_category_ids, id]
    : autoForm.document_category_ids.filter(x => x !== id)
}
function submitAuto() {
  if (editingAutomation.value) {
    autoForm.patch(route('superadmin.tenants.automations.update', [props.tenant.id, editingAutomation.value.id]), { onSuccess: closeAutoModal })
  } else {
    autoForm.post(route('superadmin.tenants.automations.store', props.tenant.id), { onSuccess: closeAutoModal })
  }
}
function toggleIsActive(auto: Automation) {
  router.patch(route('superadmin.tenants.automations.update', [props.tenant.id, auto.id]), { is_active: !auto.is_active }, { preserveScroll: true })
}
function deleteAutomation(auto: Automation) {
  if (!confirm(`Eliminare l'automazione "${auto.name}"?`)) return
  router.delete(route('superadmin.tenants.automations.destroy', [props.tenant.id, auto.id]), { preserveScroll: true })
}
function channelLabel(ch: string)   { return ch === 'email' ? 'Email' : ch === 'whatsapp' ? 'WhatsApp' : 'Email + WA' }
function channelBadgeClass(ch: string) {
  return ch === 'email' ? 'bg-blue-100 text-blue-700' : ch === 'whatsapp' ? 'bg-green-100 text-green-700' : 'bg-violet-100 text-violet-700'
}
function recipientLabel(r: string)  { return r === 'cliente' ? 'Cliente' : r === 'perito' ? 'Perito' : 'Gestore' }

// ── Module Templates ─────────────────────────────────────────────────────────

const moduleModalOpen   = ref(false)
const editingTemplate   = ref<ModuleTemplate | null>(null)
const pdfFileInputRef   = ref<HTMLInputElement | null>(null)
const pdfFile           = ref<File | null>(null)

const extraction = reactive({ loading: false, done: false, error: null as string | null })

const jsonViewMode    = ref(false)
const rawJson         = ref('')
const jsonError       = ref<string | null>(null)
const coordEditorMode = ref(false)

const moduleForm = useForm({
  name:                        '' as string,
  output_document_category_id: null as number | null,
  pdf_template_s3_key:         '' as string,
  fields_schema:               [] as FieldSchemaRow[],
  font_size:                   10 as number,
})

function openCreateModule() {
  editingTemplate.value = null
  moduleForm.reset()
  moduleForm.fields_schema = []
  pdfFile.value = null
  Object.assign(extraction, { loading: false, done: false, error: null })
  jsonViewMode.value = false
  coordEditorMode.value = false
  rawJson.value = ''
  jsonError.value = null
  moduleModalOpen.value = true
}
function openEditModule(tmpl: ModuleTemplate) {
  editingTemplate.value = tmpl
  moduleForm.name                        = tmpl.name
  moduleForm.output_document_category_id = tmpl.output_document_category_id
  moduleForm.pdf_template_s3_key         = tmpl.pdf_template_s3_key ?? ''
  moduleForm.fields_schema               = (tmpl.fields_schema ?? []).map(f => ({ ...f, _uid: uid() }))
  moduleForm.font_size                   = tmpl.font_size ?? 10
  pdfFile.value = null
  Object.assign(extraction, { loading: false, done: !!tmpl.pdf_template_s3_key, error: null })
  jsonViewMode.value = false
  coordEditorMode.value = false
  rawJson.value = ''
  jsonError.value = null
  moduleModalOpen.value = true
}
function closeModuleModal() {
  moduleModalOpen.value = false
  editingTemplate.value = null
  moduleForm.reset()
  moduleForm.fields_schema = []
  pdfFile.value = null
  Object.assign(extraction, { loading: false, done: false, error: null })
  jsonViewMode.value = false
  coordEditorMode.value = false
  jsonError.value = null
}

function onPdfFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  pdfFile.value = input.files?.[0] ?? null
  extraction.done = false
  extraction.error = null
}

async function runExtraction() {
  if (!pdfFile.value) return
  extraction.loading = true
  extraction.error   = null

  const data = new FormData()
  data.append('pdf_file', pdfFile.value)

  try {
    const resp = await http.post<{ s3_key: string; fields: FieldSchema[] }>(
      route('superadmin.tenants.module-templates.extract-fields', props.tenant.id),
      data
    )
    moduleForm.pdf_template_s3_key = resp.data.s3_key
    moduleForm.fields_schema       = resp.data.fields.map(f => ({ ...f, _uid: uid() }))
    extraction.done = true
  } catch (err: unknown) {
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    extraction.error = msg ?? 'Errore durante l\'analisi del PDF.'
  } finally {
    extraction.loading = false
  }
}

function toggleJsonMode() {
  if (!jsonViewMode.value) {
    coordEditorMode.value = false
    rawJson.value = JSON.stringify(
      moduleForm.fields_schema.map(({ _uid: _, ...f }) => f),
      null, 2
    )
    jsonError.value = null
    jsonViewMode.value = true
  } else {
    try {
      const parsed = JSON.parse(rawJson.value) as FieldSchema[]
      moduleForm.fields_schema = parsed.map(f => ({ ...f, _uid: uid() }))
      jsonError.value = null
      jsonViewMode.value = false
    } catch {
      jsonError.value = 'JSON non valido — correggi prima di tornare alla vista visuale.'
    }
  }
}

function toggleCoordMode() {
  if (!coordEditorMode.value) {
    if (jsonViewMode.value) {
      try {
        const parsed = JSON.parse(rawJson.value) as FieldSchema[]
        moduleForm.fields_schema = parsed.map(f => ({ ...f, _uid: uid() }))
      } catch { /* ignore parse error, just close JSON mode */ }
      jsonViewMode.value = false
      jsonError.value = null
    }
    coordEditorMode.value = true
  } else {
    coordEditorMode.value = false
  }
}

function addModuleField() {
  moduleForm.fields_schema.push({ _uid: uid(), name: '', label: '', type: 'text', required: false })
}
function removeModuleField(i: number) {
  moduleForm.fields_schema.splice(i, 1)
}

function saveModule() {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const stripUid = (d: { fields_schema: FieldSchemaRow[] } & Record<string, unknown>) => ({
    ...d,
    fields_schema: d.fields_schema.map(({ _uid, ...f }) => f),
  })

  if (editingTemplate.value) {
    moduleForm
      .transform(stripUid)
      .patch(
        route('superadmin.tenants.module-templates.update', [props.tenant.id, editingTemplate.value.id]),
        { onSuccess: closeModuleModal }
      )
  } else {
    moduleForm
      .transform(stripUid)
      .post(
        route('superadmin.tenants.module-templates.store', props.tenant.id),
        { onSuccess: closeModuleModal }
      )
  }
}

function deleteModule(tmpl: ModuleTemplate) {
  if (!confirm(`Eliminare il template "${tmpl.name}"?`)) return
  router.delete(
    route('superadmin.tenants.module-templates.destroy', [props.tenant.id, tmpl.id]),
    { preserveScroll: true }
  )
}
</script>

<style scoped>
.field-label { @apply block text-sm font-medium text-slate-700 mb-1; }
.field-input {
  @apply block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
         focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none
         bg-white placeholder-slate-400;
}
.list-move, .list-enter-active, .list-leave-active { transition: all 0.2s ease; }
.list-enter-from, .list-leave-to { opacity: 0; transform: translateY(-6px); }
.list-leave-active { position: absolute; }
</style>
