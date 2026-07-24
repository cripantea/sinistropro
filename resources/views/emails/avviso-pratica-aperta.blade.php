@component('mail::message')
# Sinistro #{{ $pratica->id }} — Avviso Scadenza

Gentile **{{ $destinatario->name }}**,

ti informiamo che il seguente sinistro è ancora **aperto** e richiede attenzione.

---

@component('mail::panel')
| Campo | Valore |
|---|---|
| **ID Sinistro** | #{{ $pratica->id }} |
| **Tenant** | {{ $pratica->tenant->name }} |
| **Stato attuale** | {{ $pratica->currentStatus?->name ?? '—' }} |
| **Creato da** | {{ $pratica->utenteCreatore->name }} |
| **Prossimo avviso** | {{ $nuovaDataAvviso->format('d/m/Y') }} |
@endcomponent

@if($pratica->custom_fields)
**Dati aggiuntivi:**
@foreach($pratica->custom_fields as $chiave => $valore)
- **{{ ucfirst(str_replace('_', ' ', $chiave)) }}:** {{ $valore }}
@endforeach
@endif

@component('mail::button', ['url' => $urlPratica, 'color' => 'primary'])
Apri il Sinistro
@endcomponent

Il prossimo promemoria automatico è stato impostato al **{{ $nuovaDataAvviso->format('d/m/Y') }}**.

Grazie,<br>
**{{ config('app.name') }}**

@component('mail::subcopy')
Hai ricevuto questa email perché sei {{ $destinatario->isTenantAdmin() ? 'amministratore' : 'il creatore' }} del sinistro. Se non desideri ricevere questi avvisi, contatta il tuo amministratore.
@endcomponent
@endcomponent
