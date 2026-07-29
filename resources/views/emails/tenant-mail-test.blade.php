@component('mail::message')
# Configurazione email riuscita ✓

Se stai leggendo questa email, la configurazione SMTP di **{{ $tenant->name }}** funziona correttamente.

Da questo momento le comunicazioni automatiche di questo tenant (avvisi scadenza, cambi di stato, automazioni) verranno inviate tramite questo server.

Grazie,<br>
**{{ config('app.name') }}**
@endcomponent
