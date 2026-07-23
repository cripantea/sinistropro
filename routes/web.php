<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\IspezioneController;
use App\Http\Controllers\PdfExportController;
use App\Http\Controllers\PraticaController;
use App\Http\Controllers\PraticaNotaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WhatsappConversationController;
use App\Http\Controllers\WhatsappSessionController;
use App\Http\Controllers\Superadmin\AutomationController;
use App\Http\Controllers\Superadmin\DocumentCategoryController;
use App\Http\Controllers\Superadmin\ModuleTemplateController;
use App\Http\Controllers\Superadmin\SuperadminController;
use App\Http\Controllers\Superadmin\TenantController;
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

    // Clienti — creazione rapida da modale nel form pratica
    Route::post('/clienti', [ClienteController::class, 'store'])->name('clienti.store');

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

    Route::get('/whatsapp/conversations', [WhatsappConversationController::class, 'index'])->name('whatsapp.conversations.index');
    Route::get('/whatsapp/conversations/{conversation}/messages', [WhatsappConversationController::class, 'messages'])->name('whatsapp.conversations.messages');
    Route::post('/whatsapp/conversations/{conversation}/messages', [WhatsappConversationController::class, 'store'])->name('whatsapp.conversations.store');
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
    Route::get('/',          [SuperadminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users',     [SuperadminController::class, 'users'])->name('users');
    Route::post('/users',    [SuperadminController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/toggle-active', [SuperadminController::class, 'toggleActive'])->name('users.toggle-active');
    Route::patch('/users/{user}', [SuperadminController::class, 'update'])->name('users.update');

    // Impersonazione
    Route::post('/impersonate/{user}', [ImpersonateController::class, 'start'])->name('impersonate.start');

    // Categorie documenti (globali)
    Route::get('/document-categories',                        [DocumentCategoryController::class, 'index'])->name('document-categories.index');
    Route::post('/document-categories',                       [DocumentCategoryController::class, 'store'])->name('document-categories.store');
    Route::patch('/document-categories/{documentCategory}',   [DocumentCategoryController::class, 'update'])->name('document-categories.update');
    Route::delete('/document-categories/{documentCategory}',  [DocumentCategoryController::class, 'destroy'])->name('document-categories.destroy');

    // Configurazione categorie per tenant
    Route::post('/tenants/{tenant}/document-categories', [TenantController::class, 'syncDocumentCategories'])->name('tenants.document-categories.sync');

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
