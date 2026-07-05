@component('mail::message')

{!! nl2br(e($compiledBody)) !!}

@if(!empty($documentLinks))

---

**Documenti allegati**

<table width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach($documentLinks as $doc)
<tr>
  <td style="padding:5px 0;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;border-radius:8px;">
      <tr>
        <td style="padding:13px 16px;font-family:Arial,sans-serif;font-size:14px;color:#374151;">
          &#128196;&nbsp;&nbsp;{{ $doc['nome_file'] }}
        </td>
        <td style="padding:13px 16px;text-align:right;white-space:nowrap;">
          <a href="{{ $doc['url'] }}"
             style="background:#1d4ed8;color:#ffffff;text-decoration:none;padding:8px 18px;border-radius:6px;font-family:Arial,sans-serif;font-size:13px;font-weight:600;display:inline-block;">
            Scarica &darr;
          </a>
        </td>
      </tr>
    </table>
  </td>
</tr>
@endforeach
</table>

@endif

---
*Messaggio inviato automaticamente da **{{ $tenantName }}**.*

@endcomponent
