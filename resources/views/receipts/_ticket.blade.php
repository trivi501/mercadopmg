{{-- Contenido del ticket compartido por el PDF y la vista de impresión térmica. --}}
<div class="ticket">
    <div class="center bold">{{ $t['header']['municipality'] }}</div>
    <div class="center bold">{{ $t['header']['system'] }}</div>
    <div class="center bold">{{ $t['header']['title'] }}</div>

    @if ($t['reprint'])
        <div class="stamp">REIMPRESIÓN No. {{ $t['reprint']['number'] }}</div>
    @endif
    @if ($t['is_cancelled'])
        <div class="stamp">CANCELADO</div>
    @endif

    <div class="sep"></div>
    <table>
        <tr><td>Folio:</td><td class="right bold">{{ $t['folio'] }}</td></tr>
        <tr><td>Fecha:</td><td class="right">{{ $t['date'] }}</td></tr>
        <tr><td>Hora:</td><td class="right">{{ $t['time'] }}</td></tr>
    </table>
    <div class="sep"></div>
    <div>Contribuyente:</div>
    <div class="bold">{{ $t['taxpayer'] }}</div>
    <table>
        <tr><td>Folio contribuyente:</td><td class="right">{{ $t['merchant_folio'] }}</td></tr>
    </table>
    <div>Giro: {{ $t['business_type'] }}</div>
    @if ($t['tianguis'])<div>Tianguis: {{ $t['tianguis'] }}</div>@endif
    @if ($t['route'])<div>Ruta: {{ $t['route'] }}</div>@endif
    <div class="sep"></div>

    @foreach ($t['items'] as $item)
        <div class="item">
            <div>Concepto: <span class="bold">{{ $item['concept'] }}</span></div>
            <table>
                <tr><td>Cantidad:</td><td class="right">{{ $item['quantity'] }}</td></tr>
                <tr><td>Precio unitario:</td><td class="right">${{ number_format((float) $item['unit_price'], 2) }}</td></tr>
                <tr><td>Importe:</td><td class="right">${{ number_format((float) $item['amount'], 2) }}</td></tr>
            </table>
        </div>
    @endforeach

    <div class="sep"></div>
    <table>
        <tr class="total"><td>TOTAL:</td><td class="right">${{ number_format((float) $t['total'], 2) }} {{ $t['currency'] }}</td></tr>
        <tr><td>Forma de pago:</td><td class="right">{{ $t['payment_method'] }}</td></tr>
        <tr><td>Usuario:</td><td class="right">{{ $t['user'] }}</td></tr>
    </table>

    @if ($t['reprint'])
        <div class="sep"></div>
        <div class="bold">REIMPRESIÓN No. {{ $t['reprint']['number'] }}</div>
        <div>Recibo original: {{ $t['folio'] }}</div>
        <div>Motivo: {{ $t['reprint']['reason'] }}</div>
        <div>Reimpreso: {{ $t['reprint']['date'] }} {{ $t['reprint']['time'] }}</div>
        <div>Por: {{ $t['reprint']['user'] }}</div>
    @endif

    @if ($t['is_cancelled'] && $t['cancellation_reason'])
        <div class="sep"></div>
        <div>Motivo de cancelación: {{ $t['cancellation_reason'] }}</div>
    @endif

    <div class="sep"></div>
    <div class="center"><img class="qr" src="{{ $qr }}" alt="QR"></div>
    <div class="center small">{{ $t['footer'] }}</div>
</div>
