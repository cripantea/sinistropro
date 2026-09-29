<?php

namespace App\Jobs;

use App\Mail\AutomazioneNotificaMail;
use App\Models\Automation;
use App\Models\Cliente;
use App\Models\WhatsappSession;
use App\Services\WhatsappCloudApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExecuteClienteAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly Cliente    $cliente,
        public readonly Automation $automation,
        public readonly string     $fieldName,
    ) {
        $this->onQueue('automations');
    }

    public function handle(): void
    {
        $cliente    = Cliente::with('tenant')->findOrFail($this->cliente->id);
        $automation = $this->automation;

        $phone = $cliente->telefono;
        $email = $cliente->email;
        $name  = $cliente->nome;

        $fieldValue = $cliente->custom_fields[$this->fieldName] ?? null;

        $compiled = $this->compile($automation->message_template, $cliente, $fieldValue);

        if (in_array($automation->channel, ['email', 'both'], true)) {
            $this->sendEmail($email, $name, $compiled, $cliente);
        }

        if (in_array($automation->channel, ['whatsapp', 'both'], true)) {
            $this->sendWhatsapp($phone, $compiled, $cliente);
        }

        Log::info('ExecuteClienteAutomationJob: eseguito', [
            'cliente_id'    => $cliente->id,
            'automation_id' => $automation->id,
            'field'         => $this->fieldName,
        ]);
    }

    private function compile(string $template, Cliente $cliente, ?string $fieldValue): string
    {
        $replacements = [
            '{nome_cliente}'   => $cliente->nome,
            '{nome_tenant}'    => $cliente->tenant?->name ?? '',
            '{data_scadenza}'  => $fieldValue ? \Carbon\Carbon::parse($fieldValue)->format('d/m/Y') : '',
            '{campo_data}'     => $fieldValue ? \Carbon\Carbon::parse($fieldValue)->format('d/m/Y') : '',
        ];

        foreach ($cliente->custom_fields ?? [] as $key => $value) {
            $replacements["{cliente.{$key}}"] = (string) $value;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function sendEmail(?string $email, ?string $name, string $compiled, Cliente $cliente): void
    {
        if (! $email) {
            Log::warning('ExecuteClienteAutomationJob: email mancante', ['cliente_id' => $cliente->id]);
            return;
        }

        Mail::to($email)->send(new AutomazioneNotificaMail(
            emailSubject:  "Promemoria — {$cliente->nome}",
            compiledBody:  $compiled,
            tenantName:    $cliente->tenant?->name ?? '',
            documentLinks: [],
        ));
    }

    private function sendWhatsapp(?string $phone, string $compiled, Cliente $cliente): void
    {
        if (! $phone) {
            Log::warning('ExecuteClienteAutomationJob: telefono mancante', ['cliente_id' => $cliente->id]);
            return;
        }

        $session = WhatsappSession::where('tenant_id', $cliente->tenant_id)
            ->where('status', 'connected')
            ->first();

        if (! $session) {
            Log::warning('ExecuteClienteAutomationJob: nessuna sessione WA connessa', [
                'tenant_id' => $cliente->tenant_id,
            ]);
            return;
        }

        try {
            $to = preg_replace('/[^\d+]/', '', $phone);
            app(WhatsappCloudApiClient::class)->sendText($session->phone_number_id, $to, $compiled);
        } catch (\Throwable $e) {
            Log::error('ExecuteClienteAutomationJob: WA errore', [
                'cliente_id' => $cliente->id,
                'errore'     => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteClienteAutomationJob: job fallito', [
            'cliente_id'    => $this->cliente->id,
            'automation_id' => $this->automation->id,
            'errore'        => $exception->getMessage(),
        ]);
    }
}
