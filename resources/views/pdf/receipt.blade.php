<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $t['folio'] }}</title>
    <style>
        @page { margin: 4mm; }
        body { font-family: DejaVu Sans Mono, monospace; font-size: 9px; color: #000; margin: 0; }
        .ticket { width: 100%; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0; vertical-align: top; }
        .center { text-align: center; }
        .right { text-align: right; white-space: nowrap; }
        .bold { font-weight: bold; }
        .small { font-size: 8px; }
        .sep { border-top: 1px dashed #000; margin: 4px 0; }
        .item { margin-bottom: 3px; }
        .total td { font-weight: bold; font-size: 11px; }
        .stamp { text-align: center; font-weight: bold; font-size: 14px; border: 2px solid #000; margin: 4px 0; padding: 2px; }
        .qr { width: 110px; height: 110px; margin-top: 4px; }
    </style>
</head>
<body>
    @include('receipts._ticket')
</body>
</html>
