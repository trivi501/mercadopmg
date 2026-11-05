<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Corte {{ $r['folio'] }}</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 11px; margin: 14px 0 4px; border-bottom: 1px solid #999; padding-bottom: 2px; }
        .meta { color: #555; font-size: 8.5px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eee; text-align: left; padding: 4px; border-bottom: 1px solid #999; }
        td { padding: 3px 4px; border-bottom: 1px solid #e3e3e3; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .brand td { border: none; padding: 0; vertical-align: middle; }
        .brand .logo { width: 56px; padding-right: 10px; }
        .brand .logo img { width: 56px; }
        .grid td { border: none; padding: 2px 8px 2px 0; }
        .grid td.label { color: #555; width: 120px; }
        .totals td { font-size: 10px; }
        .totals tr.strong td { font-weight: bold; font-size: 11px; border-top: 1px solid #999; }
        .over { color: #166534; }
        .short { color: #a00; }
        .cancelled td { color: #999; text-decoration: line-through; }
        .stamp { display: inline-block; border: 2px solid #a00; color: #a00; padding: 2px 8px; font-weight: bold; }
        .signatures { margin-top: 50px; }
        .signatures td { border: none; text-align: center; width: 50%; padding: 0 20px; }
        .signatures .line { border-top: 1px solid #111; padding-top: 4px; }
    </style>
</head>
<body>
    @php($s = $r['summary'])
    <table class="brand">
        <tr>
            <td class="logo"><img src="{{ \App\Support\Logo::dataUri() }}" alt="Ayuntamiento de Guadalupe"></td>
            <td>
                <div class="meta">{{ $r['municipality'] }} · PADRÓN DE COMERCIANTES</div>
                <h1>{{ $r['title'] }} {{ $r['folio'] }}</h1>
                <div class="meta">Impreso el {{ $r['printed_at'] }}</div>
            </td>
            @if ($r['is_open'])
                <td class="num"><span class="stamp">CORTE ABIERTO</span></td>
            @endif
        </tr>
    </table>

    <h2>Datos del corte</h2>
    <table class="grid">
        <tr><td class="label">Recaudador</td><td>{{ $r['user'] }}</td><td class="label">Recibos válidos</td><td>{{ $s['receipts'] }}</td></tr>
        <tr><td class="label">Inicio de cobro</td><td>{{ $r['opened_at'] }}</td><td class="label">Folios</td><td>{{ $s['first_folio'] ? $s['first_folio'].' a '.$s['last_folio'] : '—' }}</td></tr>
        <tr><td class="label">Corte final</td><td>{{ $r['closed_at'] ?? 'Abierto' }}</td><td class="label">Cerró</td><td>{{ $r['closed_by'] ?? '—' }}</td></tr>
    </table>

    <h2>Cobrado por concepto</h2>
    <table>
        <thead><tr><th>Concepto</th><th class="num">Cantidad</th><th class="num">Importe</th></tr></thead>
        <tbody>
            @forelse ($s['by_concept'] as $c)
                <tr><td>{{ $c['code'] }} · {{ $c['name'] }}</td><td class="num">{{ $c['quantity'] }}</td><td class="num">${{ number_format((float) $c['amount'], 2) }}</td></tr>
            @empty
                <tr><td colspan="3">Sin cobros en este corte.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Por forma de pago</h2>
    <table>
        <thead><tr><th>Forma de pago</th><th class="num">Recibos</th><th class="num">Importe</th></tr></thead>
        <tbody>
            @forelse ($s['by_method'] as $m)
                <tr><td>{{ $m['label'] }}</td><td class="num">{{ $m['count'] }}</td><td class="num">${{ number_format((float) $m['amount'], 2) }}</td></tr>
            @empty
                <tr><td colspan="3">—</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Arqueo de efectivo</h2>
    <table class="totals" style="width: 55%">
        <tr><td>Total cobrado</td><td class="num">${{ number_format((float) $s['total_collected'], 2) }}</td></tr>
        @if ($s['cancelled'] > 0)
            <tr><td>Cancelados ({{ $s['cancelled'] }}, no suman)</td><td class="num">${{ number_format((float) $s['cancelled_amount'], 2) }}</td></tr>
        @endif
        <tr><td>Fondo inicial</td><td class="num">${{ number_format((float) $s['opening_amount'], 2) }}</td></tr>
        <tr><td>Cobros en efectivo</td><td class="num">${{ number_format((float) $s['cash_collected'], 2) }}</td></tr>
        <tr class="strong"><td>Efectivo esperado</td><td class="num">${{ number_format((float) $s['expected_cash'], 2) }}</td></tr>
        @unless ($r['is_open'])
            @php($diff = (float) $r['difference'])
            <tr class="strong"><td>Efectivo contado</td><td class="num">${{ number_format((float) $r['counted_cash'], 2) }}</td></tr>
            <tr class="strong {{ $diff > 0 ? 'over' : ($diff < 0 ? 'short' : '') }}">
                <td>{{ $diff > 0 ? 'Sobrante' : ($diff < 0 ? 'Faltante' : 'Diferencia') }}</td>
                <td class="num">${{ number_format(abs($diff), 2) }}</td>
            </tr>
        @endunless
    </table>
    @if ($r['opening_notes'])<p><strong>Notas de inicio:</strong> {{ $r['opening_notes'] }}</p>@endif
    @if ($r['closing_notes'])<p><strong>Notas del corte:</strong> {{ $r['closing_notes'] }}</p>@endif

    <h2>Recibos del corte</h2>
    <table>
        <thead><tr><th>Folio</th><th>Hora</th><th>Contribuyente</th><th>Forma de pago</th><th class="num">Importe</th></tr></thead>
        <tbody>
            @forelse ($receipts as $receipt)
                <tr class="{{ $receipt->isCancelled() ? 'cancelled' : '' }}">
                    <td>{{ $receipt->folio }}</td>
                    <td>{{ $receipt->issued_at->format('d/m H:i') }}</td>
                    <td>{{ $receipt->merchant->folio }} · {{ $receipt->merchant->taxpayer_name }}</td>
                    <td>{{ $receipt->payment_method->label() }}{{ $receipt->isCancelled() ? ' (cancelado)' : '' }}</td>
                    <td class="num">${{ number_format((float) $receipt->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin recibos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td><div class="line">{{ $r['user'] }}<br>Recaudador</div></td>
            <td><div class="line">Recibe<br>Tesorería Municipal</div></td>
        </tr>
    </table>
</body>
</html>
