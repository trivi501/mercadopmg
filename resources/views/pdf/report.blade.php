<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; }
        header { border-bottom: 2px solid #111; margin-bottom: 8px; padding-bottom: 4px; }
        h1 { font-size: 14px; margin: 0; }
        .meta { color: #555; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eee; text-align: left; padding: 4px; border-bottom: 1px solid #999; }
        td { padding: 3px 4px; border-bottom: 1px solid #ddd; vertical-align: top; }
        tr:nth-child(even) td { background: #fafafa; }
        .summary { margin-top: 10px; width: auto; }
        .summary td { border: none; padding: 2px 8px 2px 0; font-size: 9px; }
        .summary td:first-child { font-weight: bold; }
        .warn { color: #a00; margin-top: 6px; }
    </style>
</head>
<body>
    <header>
        <div class="meta">{{ \Illuminate\Support\Str::upper((string) \App\Models\Setting::get('municipality_name', 'Municipio')) }} · PADRÓN DE COMERCIANTES</div>
        <h1>{{ $title }}</h1>
        <div class="meta">Generado el {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()?->name }}</div>
    </header>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach (array_keys($columns) as $key)
                        <td>{{ $row[$key] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">Sin registros para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($truncated)
        <p class="warn">El PDF muestra los primeros {{ \App\Reports\ReportExporter::PDF_MAX_ROWS }} registros. Exporte a Excel o CSV para obtener el reporte completo.</p>
    @endif

    @if ($summary)
        <table class="summary">
            @foreach ($summary as $label => $value)
                <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
