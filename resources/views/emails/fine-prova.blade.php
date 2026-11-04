@component('mail::message')
# La tua prova finisce il {{ $fine }}

Ciao, ti scriviamo per ricordarti che la prova gratuita di SinistroPro per **{{ $tenant->name }}** finisce il **{{ $fine }}**.

Da quel giorno parte l'abbonamento a **{{ $prezzo }} € / {{ $unita }}** (IVA inclusa), addebitato sulla carta che hai inserito. Non devi fare niente: tutto continua come adesso.

Se invece non vuoi continuare, puoi disdire prima del {{ $fine }} e non ti addebitiamo niente: in SinistroPro vai su **Abbonamento → Gestisci pagamento → Annulla abbonamento**. Un clic e hai finito. Se preferisci, rispondi a questa email e lo facciamo noi.

@component('mail::button', ['url' => route('abbonamento')])
Gestisci l'abbonamento
@endcomponent

Per qualsiasi dubbio rispondi a questa email.

Grazie,<br>
**SinistroPro**
@endcomponent
