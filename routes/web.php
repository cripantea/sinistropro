<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AutomationPreviewController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ContattoController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\ImportClientiController;
use App\Http\Controllers\ListaValoriController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\TenantContextController;
use App\Http\Controllers\IspezioneController;
use App\Http\Controllers\PdfExportController;
use App\Http\Controllers\PraticaController;
use App\Http\Controllers\PraticaNotaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WhatsappConversationController;
use App\Http\Controllers\WhatsappEmbeddedSignupController;
use App\Http\Controllers\WhatsappSessionController;
use App\Http\Controllers\Superadmin\AutomationController;
use App\Http\Controllers\Superadmin\DocumentCategoryController;
use App\Http\Controllers\Superadmin\FieldDictionaryController;
use App\Http\Controllers\Superadmin\ModuleTemplateController;
use App\Http\Controllers\Superadmin\SuperadminController;
use App\Http\Controllers\Superadmin\TenantController;
use App\Http\Controllers\Superadmin\TenantMailSettingsController;
use App\Http\Controllers\Superadmin\TenantWhatsappController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

// --- Pratiche ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/pratiche',              [PraticaController::class, 'index'])->name('pratiche.index');
    Route::get('/pratiche/create',       [PraticaController::class, 'create'])->name('pratiche.create');
    Route::get('/pratiche/kanban',       [PraticaController::class, 'kanban'])->name('pratiche.kanban');
    Route::post('/pratiche',             [PraticaController::class, 'store'])->name('pratiche.store');
    Route::get('/pratiche/{pratica}',    [PraticaController::class, 'show'])->name('pratiche.show');
    Route::get('/pratiche/{pratica}/edit',   [PraticaController::class, 'edit'])->name('pratiche.edit');
    Route::put('/pratiche/{pratica}',        [PraticaController::class, 'update'])->name('pratiche.update');
    Route::patch('/pratiche/{pratica}/status', [PraticaController::class, 'updateStatus'])->name('pratiche.update-status');
    Route::delete('/pratiche/{pratica}', [PraticaController::class, 'destroy'])->name('pratiche.destroy');

    // Anteprima automazioni "richiede conferma" prima di confermare cambio stato/data
    Route::post('/pratiche/{pratica}/automations/preview', [AutomationPreviewController::class, 'preview'])->name('pratiche.automations.preview');

    // Clienti — gestione completa + creazione rapida da modale (store mantiene JSON response)
    Route::get('/clienti',                  [ClienteController::class, 'index'])->name('clienti.index');
    Route::get('/clienti/create',           [ClienteController::class, 'create'])->name('clienti.create');
    Route::post('/clienti',                 [ClienteController::class, 'store'])->name('clienti.store');
    Route::get('/clienti/import',           [ImportClientiController::class, 'create'])->name('clienti.import');
    Route::post('/clienti/import/preview',  [ImportClientiController::class, 'preview'])->name('clienti.import.preview');
    Route::post('/clienti/import/execute',  [ImportClientiController::class, 'execute'])->name('clienti.import.execute');
    Route::get('/clienti/{cliente}',        [ClienteController::class, 'show'])->name('clienti.show');
    Route::get('/clienti/{cliente}/edit',   [ClienteController::class, 'edit'])->name('clienti.edit');
    Route::put('/clienti/{cliente}',        [ClienteController::class, 'update'])->name('clienti.update');
    Route::delete('/clienti/{cliente}',     [ClienteController::class, 'destroy'])->name('clienti.destroy');

    // Note della pratica
    Route::post('/pratiche/{pratica}/note', [PraticaNotaController::class, 'store'])->name('pratiche.note.store');
    Route::delete('/pratiche/{pratica}/note/{nota}', [PraticaNotaController::class, 'destroy'])->name('pratiche.note.destroy');

    // Allegati: upload, download presigned URL, cancellazione
    Route::post('/pratiche/{pratica}/allegati', [\App\Http\Controllers\AllegatoController::class, 'store'])->name('allegati.store');
    Route::get('/allegati/{allegato}/download',  [\App\Http\Controllers\AllegatoController::class, 'download'])->name('allegati.download');
    Route::delete('/allegati/{allegato}',         [\App\Http\Controllers\AllegatoController::class, 'destroy'])->name('allegati.web.destroy');

    // Esportazione PDF "Il Pacchetto"
    Route::get('/pratiche/{pratica}/export-pdf', PdfExportController::class)->name('pratiche.export-pdf');

    // Ispezioni (sopralluoghi) — crea/aggiorna ispezione + aggiorna stato pratica
    Route::post('/pratiche/{pratica}/ispezioni', [IspezioneController::class, 'store'])->name('ispezioni.store');

    // Chat WhatsApp filtrata sul numero della pratica
    Route::get('/pratiche/{pratica}/whatsapp', [WhatsappConversationController::class, 'forPratica'])->name('pratiche.whatsapp');

    // Moduli dinamici — compila + genera PDF
    Route::post('/pratiche/{pratica}/modules', [\App\Http\Controllers\PraticaModuleController::class, 'store'])->name('pratica-modules.store');

    // Liste valori personalizzate (feature: lista_personalizzate)
    Route::get('/liste',                        [ListaValoriController::class, 'index'])->name('liste.index');
    Route::post('/liste',                       [ListaValoriController::class, 'store'])->name('liste.store');
    Route::put('/liste/{listaValori}',          [ListaValoriController::class, 'update'])->name('liste.update');
    Route::delete('/liste/{listaValori}',       [ListaValoriController::class, 'destroy'])->name('liste.destroy');
    Route::get('/liste/{slug}/items',           [ListaValoriController::class, 'show'])->name('liste.items');
});

// --- Periti e carrozzerie (anagrafica, senza account utente) + registro email inviate ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/contatti', fn () => redirect()->route('periti.index'))->name('contatti.index');
    Route::get('/periti',      [ContattoController::class, 'index'])->defaults('tipo', 'perito')->name('periti.index');
    Route::get('/carrozzerie', [ContattoController::class, 'index'])->defaults('tipo', 'carrozzeria')->name('carrozzerie.index');
    Route::post('/contatti',             [ContattoController::class, 'store'])->name('contatti.store');
    Route::put('/contatti/{contatto}',   [ContattoController::class, 'update'])->name('contatti.update');
    Route::delete('/contatti/{contatto}', [ContattoController::class, 'destroy'])->name('contatti.destroy');

    Route::get('/email-log', [EmailLogController::class, 'index'])->name('email-log.index');
});

// --- Team (solo tenant-admin) ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/team',                              [TeamController::class, 'index'])->name('team.index');
    Route::post('/team',                             [TeamController::class, 'store'])->name('team.store');
    Route::patch('/team/{member}',                   [TeamController::class, 'update'])->name('team.update');
    Route::patch('/team/{member}/toggle-active',     [TeamController::class, 'toggleActive'])->name('team.toggle-active');
});

// --- WhatsApp ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/whatsapp', [WhatsappSessionController::class, 'index'])->name('whatsapp.index');
    Route::get('/whatsapp/media/{message}', \App\Http\Controllers\WhatsappMediaController::class)
        ->middleware('throttle:120,1')->name('whatsapp.media');
    Route::post('/whatsapp/embedded-signup', [WhatsappEmbeddedSignupController::class, 'sync'])->name('whatsapp.embedded-signup.sync');

    Route::get('/whatsapp/conversations', [WhatsappConversationController::class, 'index'])->name('whatsapp.conversations.index');
    Route::get('/whatsapp/conversations/{conversation}/messages', [WhatsappConversationController::class, 'messages'])->name('whatsapp.conversations.messages');
    Route::post('/whatsapp/conversations/{conversation}/messages', [WhatsappConversationController::class, 'store'])->name('whatsapp.conversations.store');
});

// --- Email ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/email', [EmailController::class, 'index'])->name('email.index');
    Route::get('/email/threads', [EmailController::class, 'threads'])->name('email.threads.index');
    Route::post('/email/sync', [EmailController::class, 'sync'])->name('email.sync');
    Route::get('/email/threads/{thread}/messages', [EmailController::class, 'messages'])->name('email.threads.messages');
    Route::post('/email/threads/{thread}/reply', [EmailController::class, 'reply'])->name('email.threads.reply');
    Route::post('/email/compose', [EmailController::class, 'compose'])->name('email.compose');
    Route::get('/email/attachments/{attachment}/download', [EmailController::class, 'downloadAttachment'])->name('email.attachments.download');
});

// --- Audit Log (solo tenant-admin) ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

// --- Impersonazione: uscita (qualsiasi utente autenticato con sessione attiva) ---
Route::middleware('auth')->group(function () {
    Route::post('/impersonate/leave', [ImpersonateController::class, 'leave'])
        ->name('impersonate.leave');
});

// --- Pannello Superadmin (dashboard, utenti, tenant CRUD, impersonazione) ---
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/',            [SuperadminController::class, 'dashboard'])->name('dashboard');
    Route::get('/audit-logs', [SuperadminController::class, 'auditLogs'])->name('audit-logs');
    Route::get('/users',      [SuperadminController::class, 'users'])->name('users');
    Route::post('/users',    [SuperadminController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/toggle-active', [SuperadminController::class, 'toggleActive'])->name('users.toggle-active');
    Route::patch('/users/{user}', [SuperadminController::class, 'update'])->name('users.update');

    // Impersonazione
    Route::post('/impersonate/{user}',            [ImpersonateController::class, 'start'])->name('impersonate.start');
    Route::post('/tenants/{tenant}/enter',         [ImpersonateController::class, 'enterTenant'])->name('impersonate.enter-tenant');

    // Tenant context (superadmin naviga da sé senza impersonare)
    Route::post('/tenants/{tenant}/context',       [TenantContextController::class, 'set'])->name('tenants.context.set');
    Route::post('/tenant-context/clear',           [TenantContextController::class, 'clear'])->name('tenant-context.clear');
    Route::get('/tenants/list-json',               [TenantController::class, 'listJson'])->name('tenants.list-json');

    // Categorie documenti (globali)
    Route::get('/document-categories',                        [DocumentCategoryController::class, 'index'])->name('document-categories.index');
    Route::post('/document-categories',                       [DocumentCategoryController::class, 'store'])->name('document-categories.store');
    Route::patch('/document-categories/{documentCategory}',   [DocumentCategoryController::class, 'update'])->name('document-categories.update');
    Route::delete('/document-categories/{documentCategory}',  [DocumentCategoryController::class, 'destroy'])->name('document-categories.destroy');

    // Configurazione categorie per tenant
    Route::post('/tenants/{tenant}/document-categories', [TenantController::class, 'syncDocumentCategories'])->name('tenants.document-categories.sync');

    // Configurazione email per tenant
    Route::post('/tenants/{tenant}/mail-settings', [TenantMailSettingsController::class, 'update'])->name('tenants.mail-settings.update');
    Route::post('/tenants/{tenant}/mail-settings/test', [TenantMailSettingsController::class, 'test'])->name('tenants.mail-settings.test');
    Route::post('/tenants/{tenant}/mail-settings/test-imap', [TenantMailSettingsController::class, 'testImap'])->name('tenants.mail-settings.test-imap');

    // WhatsApp: pannello di sola lettura per supporto
    Route::post('/tenants/{tenant}/whatsapp/disconnect', [TenantWhatsappController::class, 'disconnect'])->name('tenants.whatsapp.disconnect');

    // Template Moduli PDF — static segments MUST precede {moduleTemplate} wildcard
    Route::get('/module-templates/preview', [ModuleTemplateController::class, 'previewPage'])->name('tenants.module-templates.preview');
    Route::post('/tenants/{tenant}/module-templates/extract-fields', [ModuleTemplateController::class, 'extractFields'])->name('tenants.module-templates.extract-fields');
    Route::post('/tenants/{tenant}/module-templates', [ModuleTemplateController::class, 'store'])->name('tenants.module-templates.store');
    Route::patch('/tenants/{tenant}/module-templates/{moduleTemplate}', [ModuleTemplateController::class, 'update'])->name('tenants.module-templates.update');
    Route::delete('/tenants/{tenant}/module-templates/{moduleTemplate}', [ModuleTemplateController::class, 'destroy'])->name('tenants.module-templates.destroy');

    // Automazioni tenant
    Route::post('/tenants/{tenant}/automations', [AutomationController::class, 'store'])->name('tenants.automations.store');
    Route::patch('/tenants/{tenant}/automations/{automation}', [AutomationController::class, 'update'])->name('tenants.automations.update');
    Route::delete('/tenants/{tenant}/automations/{automation}', [AutomationController::class, 'destroy'])->name('tenants.automations.destroy');

    // Dizionario campi condivisi tra i moduli PDF di un tenant
    Route::post('/tenants/{tenant}/field-dictionary', [FieldDictionaryController::class, 'store'])->name('tenants.field-dictionary.store');
    Route::post('/tenants/{tenant}/field-dictionary/bulk', [FieldDictionaryController::class, 'storeBulk'])->name('tenants.field-dictionary.bulk-store');
    Route::patch('/tenants/{tenant}/field-dictionary/{fieldDictionaryEntry}', [FieldDictionaryController::class, 'update'])->name('tenants.field-dictionary.update');
    Route::delete('/tenants/{tenant}/field-dictionary/{fieldDictionaryEntry}', [FieldDictionaryController::class, 'destroy'])->name('tenants.field-dictionary.destroy');

    // Tenant CRUD
    Route::resource('tenants', TenantController::class)->names([
        'index'   => 'tenants.index',
        'create'  => 'tenants.create',
        'store'   => 'tenants.store',
        'edit'    => 'tenants.edit',
        'update'  => 'tenants.update',
        'destroy' => 'tenants.destroy',
    ]);
});

// --- Profilo ---
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
