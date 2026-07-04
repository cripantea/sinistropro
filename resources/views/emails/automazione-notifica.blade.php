@component('mail::message')

{!! nl2br(e($compiledBody)) !!}

---
*Messaggio inviato automaticamente da **{{ $tenantName }}**.*

@endcomponent
