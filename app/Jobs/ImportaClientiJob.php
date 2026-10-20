<?php

namespace App\Jobs;

use App\Models\Importazione;
use App\Models\Tenant;
use App\Services\ImportClienti\ClientiImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ImportaClientiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Un import a metà non si ripete: i contatori e i dati sarebbero già in parte scritti.
    public int $tries = 1;
    public int $timeout = 170;

    public function __construct(public readonly int $importazioneId) {}

    public function handle(ClientiImporter $importer): void
    {
        ini_set('memory_limit', '512M');

        $imp = Importazione::acrossAllTenants()->findOrFail($this->importazioneId);
        if ($imp->stato !== 'in_coda') {
            return;
        }
        $imp->update(['stato' => 'in_corso']);

        // Mai "completata con 0 righe" se i dati non si leggono: deve risultare interrotta.
        $rows = $imp->leggiRighe();
        if ($rows === null || $rows === []) {
            throw new \RuntimeException('Le righe del file non sono disponibili: ricarica il file e riprova.');
        }
        $schema = Tenant::find($imp->tenant_id)?->getClienteCustomFieldsSchema() ?? [];

        $result = $importer->process(
            $imp->tenant_id, $schema, $rows, $imp->mapping ?? [], $imp->opzioni ?? [], dryRun: false,
            progress: function (int $done, int $total) use ($imp): void {
                $imp->update(['riepilogo' => ['elaborati' => $done, 'totale' => $total]]);
            },
        );

        $imp->update([
            'stato'         => 'completata',
            'riepilogo'     => $result['riepilogo'],
            'avvisi'        => $result['avvisi'],
            'completata_at' => now(),
        ]);

        // I dati personali del file non restano salvati oltre il necessario.
        $imp->cancellaRighe();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Import clienti fallito', ['importazione_id' => $this->importazioneId, 'error' => $e->getMessage()]);

        $imp = Importazione::acrossAllTenants()->find($this->importazioneId);
        $imp?->update(['stato' => 'fallita', 'errore' => mb_substr($e->getMessage(), 0, 1000)]);
        $imp?->cancellaRighe();
    }
}
