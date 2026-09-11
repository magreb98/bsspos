<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1a1a1a; padding: 24px; }
  .header { text-align: center; border-bottom: 2px solid #222; padding-bottom: 12px; margin-bottom: 12px; }
  .company-name { font-size: 17px; font-weight: bold; letter-spacing: 1px; }
  .company-meta { font-size: 10px; color: #555; margin-top: 4px; }
  .doc-title { font-size: 14px; font-weight: bold; margin: 12px 0 4px; }
  .doc-meta { font-size: 10px; color: #444; margin-bottom: 12px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  thead th { background: #222; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
  thead th.r { text-align: right; }
  tbody td { padding: 5px 8px; border-bottom: 1px solid #e5e5e5; }
  tbody td.r { text-align: right; }
  tbody tr:nth-child(even) td { background: #f9f9f9; }
  .totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 16px; }
  .totals-table { width: 240px; }
  .totals-table td { padding: 4px 8px; border: none; }
  .totals-table td.r { text-align: right; font-variant-numeric: tabular-nums; }
  .totals-table tr.grand td { font-weight: bold; font-size: 12px; border-top: 2px solid #222; padding-top: 6px; }
  .payments-section { margin-bottom: 16px; }
  .payments-section h3 { font-size: 11px; font-weight: bold; margin-bottom: 6px; }
  .payment-row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #ddd; }
  .footer { text-align: center; font-size: 9px; color: #888; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>

@if($setting)
<div class="header">
  <div class="company-name">{{ $setting->company_name }}</div>
  <div class="company-meta">
    @if($setting->niu) NIU&nbsp;: {{ $setting->niu }} &nbsp;|&nbsp; @endif
    @if($setting->rccm) RCCM&nbsp;: {{ $setting->rccm }} &nbsp;|&nbsp; @endif
    @if($setting->address) {{ $setting->address }} @endif
    @if($setting->phone) &nbsp;|&nbsp; Tél&nbsp;: {{ $setting->phone }} @endif
  </div>
</div>
@endif

<div class="doc-title">REÇU DE VENTE N°&nbsp;{{ $sale->number }}</div>
<div class="doc-meta">
  Date&nbsp;: {{ ($sale->confirmed_at ?? $sale->created_at)->format('d/m/Y H:i') }}
  @if($sale->customer)
    &nbsp;&nbsp;|&nbsp;&nbsp; Client&nbsp;: {{ $sale->customer->name }}
  @endif
</div>

<table>
  <thead>
    <tr>
      <th>Désignation</th>
      <th class="r">Qté</th>
      <th class="r">PU HT</th>
      <th class="r">Remise</th>
      <th class="r">TVA</th>
      <th class="r">Total TTC</th>
    </tr>
  </thead>
  <tbody>
    @foreach($sale->lines as $line)
    <tr>
      <td>{{ $line->designation }}</td>
      <td class="r">{{ $line->quantity }}</td>
      <td class="r">{{ number_format($line->unit_price?->toInt() ?? 0, 0, ',', '\u{202F}') }}</td>
      <td class="r">{{ ($line->discount_amount?->toInt() ?? 0) > 0 ? number_format($line->discount_amount->toInt(), 0, ',', '\u{202F}') : '—' }}</td>
      <td class="r">{{ $line->vat_rate }}&nbsp;%</td>
      <td class="r">{{ number_format($line->line_total_including_tax?->toInt() ?? 0, 0, ',', '\u{202F}') }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="totals-wrap">
  <table class="totals-table">
    <tr><td>Total HT</td><td class="r">{{ number_format($sale->total_excluding_tax?->toInt() ?? 0, 0, ',', '\u{202F}') }}&nbsp;FCFA</td></tr>
    <tr><td>TVA</td><td class="r">{{ number_format($sale->total_tax?->toInt() ?? 0, 0, ',', '\u{202F}') }}&nbsp;FCFA</td></tr>
    <tr class="grand"><td>TOTAL TTC</td><td class="r">{{ number_format($sale->total_including_tax?->toInt() ?? 0, 0, ',', '\u{202F}') }}&nbsp;FCFA</td></tr>
  </table>
</div>

@if($sale->payments->count())
<div class="payments-section">
  <h3>Paiements</h3>
  @foreach($sale->payments as $p)
  <div class="payment-row">
    <span>{{ strtoupper($p->method instanceof \BackedEnum ? $p->method->value : (string) $p->method) }}
      @if(($p->currency_code ?? 'XAF') !== 'XAF')
        &nbsp;({{ $p->amount_original }}&nbsp;{{ $p->currency_code }})
      @endif
    </span>
    <span>{{ number_format($p->amount?->toInt() ?? 0, 0, ',', '\u{202F}') }}&nbsp;FCFA</span>
  </div>
  @endforeach
</div>
@endif

<div class="footer">Merci de votre confiance — Document généré par BSS POS</div>

</body>
</html>
