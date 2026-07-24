@component('mail::message')
# Aggiornamento sul suo sinistro

Gentile Cliente,

la informiamo che il **Sinistro #{{ $pratica->id }}** è stato aggiornato allo stato:

**{{ $nuovoStato->name }}**

@component('mail::button', ['url' => $urlPratica, 'color' => 'primary'])
Visualizza sinistro
@endcomponent

Per qualsiasi domanda può rispondere a questa email o contattarci direttamente.

Cordiali saluti,
**{{ $pratica->tenant->name }}**
@endcomponent
