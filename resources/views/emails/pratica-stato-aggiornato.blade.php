@component('mail::message')
# Aggiornamento sulla sua pratica

Gentile Cliente,

la informiamo che la **Pratica #{{ $pratica->id }}** è stata aggiornata allo stato:

**{{ $nuovoStato->name }}**

@component('mail::button', ['url' => $urlPratica, 'color' => 'primary'])
Visualizza pratica
@endcomponent

Per qualsiasi domanda può rispondere a questa email o contattarci direttamente.

Cordiali saluti,
**{{ $pratica->tenant->name }}**
@endcomponent
