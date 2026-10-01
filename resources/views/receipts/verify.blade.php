<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Verificación de recibo</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f4f5; color: #18181b; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 16px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; max-width: 380px; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        h1 { font-size: 18px; margin: 0 0 4px; }
        p { margin: 0 0 16px; color: #52525b; font-size: 14px; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; margin: 0; font-size: 14px; }
        dt { color: #71717a; }
        dd { margin: 0; font-weight: 600; text-align: right; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 13px; font-weight: 600; }
        .ok { background: #dcfce7; color: #166534; }
        .bad { background: #fee2e2; color: #991b1b; }
        @media (prefers-color-scheme: dark) {
            body { background: #09090b; color: #fafafa; }
            .card { background: #18181b; }
            p, dt { color: #a1a1aa; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ \Illuminate\Support\Str::upper((string) \App\Models\Setting::get('municipality_name', 'Municipio')) }}</h1>
        <p>Verificación de recibo de pago</p>
        <dl>
            <dt>Folio</dt><dd>{{ $receipt->folio }}</dd>
            <dt>Fecha</dt><dd>{{ $receipt->issued_at->format('d/m/Y H:i') }}</dd>
            <dt>Contribuyente</dt><dd>{{ $receipt->merchant->folio }}</dd>
            <dt>Total</dt><dd>${{ number_format((float) $receipt->total, 2) }} MXN</dd>
            <dt>Estado</dt>
            <dd>
                <span class="badge {{ $receipt->isCancelled() ? 'bad' : 'ok' }}">
                    {{ $receipt->isCancelled() ? 'CANCELADO' : 'VÁLIDO' }}
                </span>
            </dd>
        </dl>
    </div>
</body>
</html>
