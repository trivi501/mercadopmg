<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Corte {{ $session->folio }}</title>
    <style>
        @page { size: {{ $width }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e5e5; font-family: 'Courier New', monospace; color: #000; }
        /* 32 columnas en 58 mm y 48 en 80 mm, de orilla a orilla (Courier ≈ 0.6 em por carácter). */
        .ticket { width: {{ $width }}mm; margin: 12px auto; padding: 3mm; background: #fff; font-size: {{ $width == 58 ? '10.1px' : '9.6px' }}; line-height: 1.3; }
        .line { white-space: pre; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .double { font-size: 1.4em; }
        .logo { display: block; margin: 0 auto 4px; height: {{ $width == 58 ? '18mm' : '22mm' }}; }
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
        <a href="?width=58">58 mm</a>
        <a href="?width=80">80 mm</a>
    </div>
    <div class="ticket">
        <img class="logo" src="{{ \App\Support\Logo::dataUri(\App\Support\Logo::SHIELD_BLACK) }}" alt="Ayuntamiento de Guadalupe">
        @foreach ($lines as $line)
            <div class="line {{ $line['align'] }} {{ $line['bold'] ? 'bold' : '' }} {{ $line['size'] === 'double' ? 'double' : '' }}">{{ $line['text'] === '' ? ' ' : $line['text'] }}</div>
        @endforeach
    </div>
    @if ($autoprint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
