<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1a1a1a; padding: 28px; }
  .top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
  .company-block .company-name { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
  .company-block .meta { font-size: 10px; color: #555; margin-top: 4px; line-height: 1.6; }
  .invoice-block { text-align: right; }
  .invoice-block .title { font-size: 22px; font-weight: bold; color: #111; letter-spacing: 2px; }
  .invoice-block .number { font-size: 13px; color: #444; margin-top: 4px; }
  .invoice-block .dates { font-size: 10px; color: #666; margin-top: 6px; line-height: 1.6; }
  .separator { border: none; border-top: 2px solid #222; margin: 16px 0; }
  .parties { display: flex; justify-content: space-between; margin-bottom: 20px; }
  .party h4 { font-size: 10px; font-weight: bold; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
  .party p { font-size: 11px; line-height: 1.7; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  thead th { background: #1a1a1a; color: #fff; padding: 7px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
  thead th.r { text-align: right; }
  tbody td { padding: 6px 10px; border-bottom: 1px solid #e8e8e8; }
  tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
  tbody tr:nth-child(even) td { background: #fafafa; }
  .totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 24px; }
  .totals-table { width: 260px; }
  .totals-table td { padding: 5px 10px; border: none; }
  .totals-table td.r { text-align: right; font-variant-numeric: tabular-nums; }
  .totals-table tr.sep td { border-top: 1px solid #ccc; padding-top: 8px; }
  .totals-table tr.grand td { font-weight: bold; font-size: 13px; background: #1a1a1a; color: #fff; padding: 6px 10px; }
  .outstanding { margin-bottom: 20px; padding: 12px; background: #fff8e1; border-left: 4px solid #f0a500; font-size: 11px; }
  .outstanding strong { font-size: 13px; }
  .payments-section h3 { font-size: 11px; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; color: #555; }
  .payment-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #ddd; }
  .notes { margin-top: 20px; font-size: 10px; color: #555; border-top: 1px solid #eee; padding-top: 12px; }
  .footer { text-align: center; font-size: 9px; color: #aaa; margin-top: 24px; border-top: 1px solid #eee; padding-top: 10px; }
</style>
</head>
<body>

<div class="top">
  <div class="company-block">
    @if($setting)
    <div class="company-name">{{ $setting->company_name }}</div>
    <div class="meta">
      @if($setting->niu) NIU : {{ $setting->niu }}<br>@endif
      @if($setting->rccm) RCCM : {{ $setting->rccm }}<br>@endif
      @if($setting->address) {{ $setting->address }}<br>@endif
      @if($setting->phone) Tél : {{ $setting->phone }}@endif
    </div>
    @endif
  </div>
  <div class="invoice-block">
    <div class="title">FACTURE</div>
    <div class="number">N° {{ $invoice->number }}</div>
    <div class="dates">
      Date d'émission : {{ $invoice->issue_date->format('d/m/Y') }}<br>
      @if($invoice->due_date)
      Échéance : {{ $invoice->due_date->format('d/m/Y') }}
      @endif
    </div>
  </div>
</div>

<hr class="separator">

<div class="parties">
  <div class="party">
    <h4>Émetteur</h4>
    @if($setting)
    <p>{{ $setting->company_name }}</p>
    @endif
  </div>
  <div class="party" style="text-align:right">
    <h4>Destinataire</h4>
    @if($invoice->customer)
    <p>
      {{ $invoice->customer->name }}<br>
      @if($invoice->customer->phone ?? null) {{ $invoice->customer->phone }}<br>@endif
      @if($invoice->customer->email ?? null) {{ $invoice->customer->email }}@endif
    </p>
    @endif
  </div>
</div>

@if($invoice->sale)
<div style="margin-bottom:16px;font-size:10px;color:#666;">
  Référence vente : {{ $invoice->sale->number }}
</div>
@endif

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Désignation</th>
      <th class="r">Qté</th>
      <th class="r">PU HT</th>
      <th class="r">TVA</th>
      <th class="r">Total TTC</th>
    </tr>
  </thead>
  <tbody>
    @if($invoice->sale)
      @foreach($invoice->sale->lines as $i => $line)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $line->designation }}</td>
        <td class="r">{{ $line->quantity }}</td>
        <td class="r">{{ number_format($line->unit_price?->toInt() ?? 0, 0, ',', '\u{202F}') }}</td>
        <td class="r">{{ $line->vat_rate }}&nbsp;%</td>
        <td class="r">{{ number_format($line->line_total_including_tax?->toInt() ?? 0, 0, ',', '\u{202F}') }}</td>
      </tr>
      @endforeach
    @else
      <tr><td colspan="6" style="text-align:center;color:#888;padding:12px;">Voir bon de livraison associé.</td></tr>
    @endif
  </tbody>
</table>

<div class="totals-wrap">
  <table class="totals-table">
    <tr><td>Sous-total HT</td><td class="r">{{ number_format($invoice->total_ht?->toInt() ?? 0, 0, ',', '\u{202F}') }}&nbsp;FCFA</td></tr>
    <tr><td>TVA</td><td class="r">{{ number_format($invoice->total_vat?->toInt() ?? 0, 0, ',', '\u{202F}') }}&nbsp;FCFA</td></tr>
    <tr class="sep grand"><td>TOTAL TTC</td><td class="r">{{ number_format($invoice->total_ttc?->toInt() ?? 0, 0, ',', '\u{202F}') }}&nbsp;FCFA</td></tr>
  </table>
</div>

@php $outstanding = $invoice->outstanding_amount?->toInt() ?? 0; @endphp
@if($outstanding > 0)
<div class="outstanding">
  Montant déjà réglé&nbsp;: <strong>{{ number_format($invoice->paid_amount?->toInt() ?? 0, 0, ',', '\u{202F}') }}&nbsp;FCFA</strong><br>
  <strong>Reste à payer&nbsp;: {{ number_format($outstanding, 0, ',', '\u{202F}') }}&nbsp;FCFA</strong>
  @if($invoice->due_date) — Échéance le {{ $invoice->due_date->format('d/m/Y') }} @endif
</div>
@else
<div style="padding:10px;background:#e8f5e9;border-left:4px solid #4caf50;margin-bottom:20px;font-size:11px;">
  ✓ Facture entièrement réglée.
</div>
@endif

@if($invoice->payments->count())
<div class="payments-section">
  <h3>Règlements reçus</h3>
  @foreach($invoice->payments as $p)
  <div class="payment-row">
    <span>{{ $p->payment_date->format('d/m/Y') }} — {{ strtoupper((string) $p->method) }}</span>
    <span>{{ number_format($p->amount?->toInt() ?? 0, 0, ',', '\u{202F}') }}&nbsp;FCFA</span>
  </div>
  @endforeach
</div>
@endif

@if($invoice->notes)
<div class="notes"><strong>Notes :</strong> {{ $invoice->notes }}</div>
@endif

<div class="footer">
  Document conforme au plan SYSCOHADA — BSS POS &mdash; {{ $invoice->number }}
</div>

</body>
</html>
