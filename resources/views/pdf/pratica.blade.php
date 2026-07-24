<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Sinistro #{{ $pratica->id }} — {{ $pratica->tenant->name }}</title>
<style>
  /* ── Reset & Base ─────────────────────────────────────────── */
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 10pt;
    color: #1a1a2e;
    background: #fff;
    line-height: 1.5;
  }

  /* ── Layout ──────────────────────────────────────────────── */
  .page-wrap {
    padding: 12mm 14mm 10mm 14mm;
  }

  /* ── Header documento ────────────────────────────────────── */
  .doc-header {
    border-bottom: 3px solid #1a1a2e;
    padding-bottom: 8px;
    margin-bottom: 14px;
    display: flex; /* DomPDF non supporta flex, usiamo table trick */
  }

  .doc-header-table {
    width: 100%;
    border-collapse: collapse;
  }

  .doc-header-table td { vertical-align: middle; }

  .doc-title {
    font-size: 18pt;
    font-weight: bold;
    color: #1a1a2e;
    letter-spacing: -0.5px;
  }

  .doc-subtitle {
    font-size: 9pt;
    color: #6b7280;
    margin-top: 2px;
  }

  .doc-meta {
    text-align: right;
    font-size: 8pt;
    color: #6b7280;
  }

  /* ── Sezioni ────────────────────────────────────────────── */
  .section {
    margin-bottom: 16px;
    page-break-inside: avoid;
  }

  .section-title {
    font-size: 11pt;
    font-weight: bold;
    color: #fff;
    background-color: #1a1a2e;
    padding: 4px 10px;
    margin-bottom: 10px;
    letter-spacing: 0.5px;
  }

  /* ── Griglia dati principali ────────────────────────────── */
  .data-grid {
    width: 100%;
    border-collapse: collapse;
  }

  .data-grid td {
    padding: 5px 8px;
    vertical-align: top;
    border: 1px solid #e5e7eb;
    width: 50%;
  }

  .data-grid td.label {
    font-weight: bold;
    background-color: #f9fafb;
    color: #374151;
    width: 30%;
    white-space: nowrap;
  }

  /* ── Badge stato ────────────────────────────────────────── */
  .status-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 9pt;
    font-weight: bold;
    border: 1px solid currentColor;
  }

  /* ── Custom fields ──────────────────────────────────────── */
  .custom-table {
    width: 100%;
    border-collapse: collapse;
  }

  .custom-table th {
    background: #f3f4f6;
    padding: 5px 10px;
    text-align: left;
    font-size: 9pt;
    border: 1px solid #d1d5db;
    color: #374151;
  }

  .custom-table td {
    padding: 5px 10px;
    border: 1px solid #e5e7eb;
    font-size: 9pt;
    vertical-align: top;
  }

  .custom-table tr:nth-child(even) td {
    background-color: #f9fafb;
  }

  /* ── Timeline note ──────────────────────────────────────── */
  .nota-item {
    border-left: 3px solid #4f46e5;
    padding: 6px 10px;
    margin-bottom: 8px;
    background: #fafafa;
    page-break-inside: avoid;
  }

  .nota-header {
    font-size: 8pt;
    color: #6b7280;
    margin-bottom: 3px;
  }

  .nota-header strong {
    color: #1a1a2e;
    font-size: 9pt;
  }

  .nota-body {
    font-size: 9.5pt;
    color: #111827;
    white-space: pre-wrap;
    word-break: break-word;
  }

  /* ── Allegati ───────────────────────────────────────────── */
  .allegati-table {
    width: 100%;
    border-collapse: collapse;
  }

  .allegati-table th {
    background: #f3f4f6;
    padding: 5px 10px;
    text-align: left;
    font-size: 9pt;
    border: 1px solid #d1d5db;
    color: #374151;
  }

  .allegati-table td {
    padding: 5px 10px;
    border: 1px solid #e5e7eb;
    font-size: 9pt;
  }

  .allegati-table tr:nth-child(even) td {
    background: #f9fafb;
  }

  /* ── Footer pagina ──────────────────────────────────────── */
  .page-footer {
    position: fixed;
    bottom: 8mm;
    left: 14mm;
    right: 14mm;
    border-top: 1px solid #d1d5db;
    padding-top: 4px;
    font-size: 7.5pt;
    color: #9ca3af;
  }

  .page-footer table { width: 100%; border-collapse: collapse; }

  .empty-state {
    font-size: 9pt;
    color: #9ca3af;
    font-style: italic;
    padding: 4px 0;
  }
</style>
</head>
<body>
<div class="page-wrap">

  {{-- ════════════════════════════════════
       HEADER DOCUMENTO
  ════════════════════════════════════ --}}
  <div class="section" style="margin-bottom: 18px;">
    <table class="doc-header-table">
      <tr>
        <td>
          <div class="doc-title">Sinistro #{{ $pratica->id }}</div>
          <div class="doc-subtitle">{{ $pratica->tenant->name }} &mdash; Pacchetto documentale completo</div>
        </td>
        <td class="doc-meta">
          Generato il {{ now()->format('d/m/Y') }} alle {{ now()->format('H:i') }}<br>
          da {{ auth()->user()->name }}
        </td>
      </tr>
    </table>
  </div>

  {{-- ════════════════════════════════════
       SEZIONE 1 — DATI PRINCIPALI
  ════════════════════════════════════ --}}
  <div class="section">
    <div class="section-title">&#9312; Dati Principali</div>
    <table class="data-grid">
      <tr>
        <td class="label">ID Sinistro</td>
        <td>#{{ $pratica->id }}</td>
        <td class="label">Stato</td>
        <td>
          @if($pratica->currentStatus)
            <span class="status-badge" style="color: {{ $pratica->currentStatus->color }}; border-color: {{ $pratica->currentStatus->color }};">
              {{ $pratica->currentStatus->name }}
            </span>
          @else
            <span style="color: #9ca3af;">Non assegnato</span>
          @endif
        </td>
      </tr>
      <tr>
        <td class="label">Utente Creatore</td>
        <td>{{ $pratica->utenteCreatore->name }}</td>
        <td class="label">Email Creatore</td>
        <td>{{ $pratica->utenteCreatore->email }}</td>
      </tr>
      <tr>
        <td class="label">Data Apertura</td>
        <td>{{ $pratica->created_at->format('d/m/Y') }}</td>
        <td class="label">Prossimo Avviso</td>
        <td>
          @if($pratica->data_prossimo_avviso)
            {{ $pratica->data_prossimo_avviso->format('d/m/Y') }}
          @else
            <span style="color: #9ca3af;">—</span>
          @endif
        </td>
      </tr>
      <tr>
        <td class="label">Tenant</td>
        <td>{{ $pratica->tenant->name }}</td>
        <td class="label">Ultima Modifica</td>
        <td>{{ $pratica->updated_at->format('d/m/Y H:i') }}</td>
      </tr>
    </table>
  </div>

  {{-- ════════════════════════════════════
       SEZIONE 2 — CAMPI PERSONALIZZATI
  ════════════════════════════════════ --}}
  <div class="section">
    <div class="section-title">&#9313; Dati Personalizzati del Tenant</div>

    @if($pratica->custom_fields && count($pratica->custom_fields) > 0)
      <table class="custom-table">
        <thead>
          <tr>
            <th style="width: 35%;">Campo</th>
            <th>Valore</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pratica->custom_fields as $chiave => $valore)
            <tr>
              <td style="font-weight: bold; color: #374151;">
                {{ ucwords(str_replace(['_', '-'], ' ', $chiave)) }}
              </td>
              <td>
                @if(is_array($valore))
                  {{ implode(', ', $valore) }}
                @elseif(is_bool($valore))
                  {{ $valore ? 'Sì' : 'No' }}
                @else
                  {{ $valore !== null && $valore !== '' ? $valore : '—' }}
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="empty-state">Nessun campo personalizzato definito per questo sinistro.</p>
    @endif
  </div>

  {{-- ════════════════════════════════════
       SEZIONE 3 — TIMELINE NOTE
  ════════════════════════════════════ --}}
  <div class="section">
    <div class="section-title">&#9314; Timeline Commenti ({{ $pratica->note->count() }})</div>

    @forelse($pratica->note as $nota)
      <div class="nota-item">
        <div class="nota-header">
          <strong>{{ $nota->user->name }}</strong>
          &nbsp;&mdash;&nbsp;{{ $nota->created_at->format('d/m/Y') }} alle {{ $nota->created_at->format('H:i') }}
          @if($nota->created_at->ne($nota->updated_at))
            <span style="color: #d97706;">(modificata)</span>
          @endif
        </div>
        <div class="nota-body">{{ $nota->nota }}</div>
      </div>
    @empty
      <p class="empty-state">Nessun commento presente per questo sinistro.</p>
    @endforelse
  </div>

  {{-- ════════════════════════════════════
       SEZIONE 4 — ALLEGATI
  ════════════════════════════════════ --}}
  <div class="section">
    <div class="section-title">&#9315; Documenti Allegati ({{ $pratica->allegati->count() }})</div>

    @if($pratica->allegati->isNotEmpty())
      <table class="allegati-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nome File</th>
            <th>Caricato il</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pratica->allegati as $i => $allegato)
            <tr>
              <td style="width: 30px; color: #9ca3af; text-align: center;">{{ $i + 1 }}</td>
              <td style="word-break: break-all;">{{ $allegato->nome_file }}</td>
              <td style="width: 90px; white-space: nowrap;">
                {{ $allegato->created_at->format('d/m/Y') }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="empty-state">Nessun allegato caricato per questo sinistro.</p>
    @endif
  </div>

</div>{{-- /page-wrap --}}

{{-- ════════════════════════════════════
     FOOTER FISSO SU OGNI PAGINA
════════════════════════════════════ --}}
<div class="page-footer">
  <table>
    <tr>
      <td>{{ $pratica->tenant->name }} &mdash; Documento riservato</td>
      <td style="text-align: center; color: #d1d5db;">{{ now()->format('d/m/Y') }}</td>
      <td style="text-align: right;">Sinistro #{{ $pratica->id }}</td>
    </tr>
  </table>
</div>

</body>
</html>
