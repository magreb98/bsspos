<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1a1a1a; padding: 28px; }
  .top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
  .company-block .name { font-size: 17px; font-weight: bold; letter-spacing: 1px; }
  .company-block .meta { font-size: 10px; color: #555; margin-top: 4px; line-height: 1.6; }
  .doc-block { text-align: right; }
  .doc-block .title { font-size: 20px; font-weight: bold; letter-spacing: 2px; color: #111; }
  .doc-block .subtitle { font-size: 11px; color: #555; margin-top: 4px; }
  hr { border: none; border-top: 2px solid #222; margin: 16px 0; }
  .parties { display: flex; justify-content: space-between; margin-bottom: 20px; }
  .party h4 { font-size: 10px; font-weight: bold; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
  .party p { font-size: 11px; line-height: 1.7; }
  .summary { display: flex; gap: 16px; margin-bottom: 20px; }
  .summary-box { flex: 1; padding: 12px 14px; border: 1px solid #e0e0e0; border-radius: 4px; }
  .summary-box .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 4px; }
  .summary-box .value { font-size: 15px; font-weight: bold; font-variant-numeric: tabular-nums; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  thead th { background: #1a1a1a; color: #fff; padding: 7px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
  thead th.r { text-align: right; }
  thead th.c { text-align: center; }
  tbody td { padding: 7px 10px; border-bottom: 1px solid #ebebeb; }
  tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
  tbody td.c { text-align: center; }
  tbody tr:nth-child(even) td { background: #fafafa; }
  .badge-paid { display: inline-block; padding: 2px 8px; background: #e8f5e9; color: #2e7d32; border-radius: 10px; font-size: 9px; font-weight: bold; }
  .badge-pending { display: inline-block; padding: 2px 8px; background: #fff3e0; color: #e65100; border-radius: 10px; font-size: 9px; font-weight: bold; }
  .badge-overdue { display: inline-block; padding: 2px 8px; background: #ffebee; color: #c62828; border-radius: 10px; font-size: 9px; font-weight: bold; }
  .footer { text-align: center; font-size: 9px; color: #aaa; margin-top: 24px; border-top: 1px solid #eee; padding-top: 10px; }
</style>
</head>
<body>

<div class="top">
  <div class="company-block">
    @if($setting)
    <div class="name">{{ $setting->company_name }}</div>
    <div class="meta">
      @if($setting->niu) NIU : {{ $setting->niu }}<br>@endif
      @if($setting->address) {{ $setting->address }}<br>@endif
      @if($setting->phone) Tél : {{ $setting->phone }}@endif
    </div>
    @endif
  </div>
  <div class="doc-block">
    <div class="title">ÉCHÉANCIER</div>
    <div class="subtitle">
      Vente réf. {{ $schedule->sale?->number ?? '—' }}<br>
      Émis le {{ now()->format('d/m/Y') }}
    </div>
  </div>
</div>

<hr>

<div class="parties">
  <div class="party">
    <h4>Vendeur</h4>
    @if($setting) <p>{{ $setting->company_name }}</p> @endif
  </div>
  <div class="party" style="text-align:right">
    <h4>Client</h4>
    @if($schedule->sale?->customer)
    <p>
      {{ $schedule->sale->customer->name }}<br>
      @if($schedule->sale->customer->phone ?? null) {{ $schedule->sale->customer->phone }}<br>@endif
    </p>
    @else
    <p>—</p>
    @endif
  </div>
</div>

@php
  $paid = $schedule->installments->filter(fn($i) => $i->paid_at !== null)->sum('amount');
  $remaining = $schedule->total - $schedule->deposit - $paid;
@endphp

<div class="summary">
  <div class="summary-box">
    <div class="label">Total vente</div>
    <div class="value">{{ number_format($schedule->total, 0, ',', '\u{202F}') }}&nbsp;FCFA</div>
  </div>
  <div class="summary-box">
    <div class="label">Acompte versé</div>
    <div class="value">{{ number_format($schedule->deposit, 0, ',', '\u{202F}') }}&nbsp;FCFA</div>
  </div>
  <div class="summary-box">
    <div class="label">Versements réglés</div>
    <div class="value">{{ number_format($paid, 0, ',', '\u{202F}') }}&nbsp;FCFA</div>
  </div>
  <div class="summary-box" style="border-color:#1a1a1a">
    <div class="label">Reste dû</div>
    <div class="value">{{ number_format(max(0, $remaining), 0, ',', '\u{202F}') }}&nbsp;FCFA</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th class="c">#</th>
      <th>Échéance</th>
      <th class="r">Montant</th>
      <th class="c">Statut</th>
      <th class="c">Réglé le</th>
    </tr>
  </thead>
  <tbody>
    @foreach($schedule->installments->sortBy('due_on') as $i => $inst)
    @php
      $isOverdue = $inst->paid_at === null && \Carbon\Carbon::parse($inst->due_on)->isPast();
    @endphp
    <tr>
      <td class="c">{{ $i + 1 }}</td>
      <td>{{ \Carbon\Carbon::parse($inst->due_on)->format('d/m/Y') }}</td>
      <td class="r">{{ number_format($inst->amount, 0, ',', '\u{202F}') }}&nbsp;FCFA</td>
      <td class="c">
        @if($inst->paid_at !== null)
          <span class="badge-paid">RÉGLÉ</span>
        @elseif($isOverdue)
          <span class="badge-overdue">EN RETARD</span>
        @else
          <span class="badge-pending">EN ATTENTE</span>
        @endif
      </td>
      <td class="c">{{ $inst->paid_at?->format('d/m/Y') ?? '—' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="footer">
  BSS POS — Échéancier généré le {{ now()->format('d/m/Y à H:i') }}
</div>

</body>
</html>
