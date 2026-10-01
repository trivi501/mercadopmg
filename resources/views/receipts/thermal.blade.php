<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $t['folio'] }}</title>
    <style>
        @page { size: {{ $width }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e5e5; font-family: 'Courier New', monospace; color: #000; }
        .ticket { width: {{ $width }}mm; margin: 12px auto; padding: 3mm; background: #fff; font-size: {{ $width == 58 ? '11px' : '13px' }}; line-height: 1.25; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0; vertical-align: top; }
        .center { text-align: center; }
        .right { text-align: right; white-space: nowrap; }
        .bold { font-weight: bold; }
        .small { font-size: .85em; }
        .sep { border-top: 1px dashed #000; margin: 4px 0; }
        .item { margin-bottom: 3px; }
        .total td { font-weight: bold; font-size: 1.2em; }
        .stamp { text-align: center; font-weight: bold; font-size: 1.4em; border: 2px solid #000; margin: 4px 0; padding: 2px; }
        .qr { width: {{ $width == 58 ? '30mm' : '38mm' }}; height: auto; margin-top: 4px; image-rendering: pixelated; }
        .toolbar { text-align: center; padding: 12px; font-family: system-ui, sans-serif; }
        .toolbar button, .toolbar a { font: inherit; padding: 6px 14px; margin: 0 4px; border: 1px solid #333; background: #fff; border-radius: 6px; cursor: pointer; color: #000; text-decoration: none; }
        @media print {
            body { background: #fff; }
            .ticket { margin: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="?width=58{{ $reprintQuery }}">58 mm</a>
        <a href="?width=80{{ $reprintQuery }}">80 mm</a>
    </div>
    @include('receipts._ticket')
    @if ($autoprint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
