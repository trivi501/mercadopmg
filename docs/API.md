# API REST v1

Base: `https://<servidor>/api/v1` · JSON · Autenticación: `Authorization: Bearer <token>` (Laravel Sanctum).

Encabezados recomendados en todas las peticiones:

```
Accept: application/json
X-Device-Name: Terminal 01      # queda en la auditoría y en el recibo
```

## Formato de errores

Todas las respuestas de error tienen la misma forma:

```json
{
    "message": "Texto para mostrar al usuario",
    "code": "annuality_already_paid",
    "errors": { "items": ["..."] }
}
```

| HTTP | `code`                   | Significado                                         |
| ---- | ------------------------ | --------------------------------------------------- |
| 401  | `unauthenticated`        | Token ausente, vencido o revocado → volver al login |
| 403  | `forbidden`              | El rol no tiene permiso                             |
| 403  | `user_inactive`          | Usuario desactivado; sus tokens se revocaron        |
| 404  | `not_found`              | Recurso inexistente (p. ej. folio QR desconocido)   |
| 422  | `validation_error`       | Datos inválidos; ver `errors` por campo             |
| 422  | `annuality_already_paid` | La anualidad del ejercicio ya está pagada           |
| 422  | `annuality_duplicated`   | La misma anualidad aparece dos veces en el cobro    |
| 422  | `merchant_inactive`      | El comerciante no está activo                       |
| 422  | `concept_unavailable`    | Concepto inexistente o desactivado                  |
| 422  | `already_printed`        | El ticket ya se imprimió; usar reimpresión          |
| 422  | `receipt_cancelled`      | El recibo está cancelado                            |
| 429  | `too_many_requests`      | Límite de peticiones; respetar `Retry-After`        |

Límites: login 5/min por usuario+IP; API 120/min por usuario; sincronización 30/min.

## Autenticación

### `POST /login`

```json
{
    "username": "recaudador1",
    "password": "••••••",
    "device_name": "Terminal 01"
}
```

`username` acepta usuario o correo. Respuesta:

```json
{
    "data": {
        "token": "12|abc…",
        "token_type": "Bearer",
        "expires_at": "2026-10-30T15:57:00-06:00",
        "user": {
            "id": 2,
            "name": "Recaudador Demo",
            "username": "recaudador",
            "role": "collector",
            "role_label": "Recaudador",
            "permissions": [
                "dashboard.view",
                "merchants.view",
                "payments.create",
                "…"
            ]
        }
    }
}
```

El token vence a los 30 días. Guárdelo cifrado (EncryptedSharedPreferences / DataStore + Keystore). Use `permissions` sólo para mostrar u ocultar opciones; el servidor valida siempre.

### `POST /logout` · `GET /me`

`logout` revoca el token actual. `me` devuelve el usuario con sus permisos.

## Catálogos

| Método | Ruta        | Descripción                                                                                                                             |
| ------ | ----------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| GET    | `/catalogs` | Todo en una llamada: `business_types`, `tianguis`, `routes`, `concepts`, `commerce_types`, `payment_methods`, `settings`, `server_time` |
| GET    | `/giros`    | Giros activos                                                                                                                           |
| GET    | `/tianguis` | Tianguis activos                                                                                                                        |
| GET    | `/routes`   | Rutas activas                                                                                                                           |
| GET    | `/concepts` | Conceptos de cobro activos (`type`: `ticket` · `annuality` · `other`, `amount` como texto decimal `"40.00"`)                            |

Los importes siempre viajan como **texto decimal** (`"155.00"`); en Kotlin conviértalos a `BigDecimal`, nunca a `Double`.

## Comerciantes

### `GET /merchants`

Parámetros (todos opcionales):

| Parámetro                                               | Uso                                                                                                        |
| ------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `search`                                                | folio, nombre, nombre comercial o teléfono                                                                 |
| `folio`                                                 | coincidencia exacta                                                                                        |
| `business_type_id`, `tianguis_id`, `route_id`, `status` | filtros                                                                                                    |
| `lat`, `lng`, `radius`                                  | comerciantes cercanos (radio en km, por omisión 0.5, máx. 20); agrega `distance_km` y ordena por distancia |
| `updated_since`                                         | ISO-8601; sólo cambios posteriores (sincronización incremental del padrón)                                 |
| `per_page`                                              | 1–500 (por omisión 50)                                                                                     |

Respuesta paginada estándar de Laravel (`data`, `links`, `meta`).

### `GET /merchants/folio/{folio}`

Búsqueda por el contenido del QR del comerciante (el QR contiene sólo el folio, p. ej. `COM-000001`). 404 si no existe.

### `GET /merchants/{id}`

Incluye `measurements` (histórico de metros).

### `POST /merchants` · `PUT /merchants/{id}`

```json
{
    "uuid": "c6f1…", // generado en el dispositivo; hace idempotente el alta
    "taxpayer_name": "Juan Pérez López",
    "trade_name": null,
    "business_type_id": 1,
    "business_description": "Venta de ropa",
    "phone": "5512345678",
    "commerce_type": "tianguis", // tianguis | route | plaza | public_road
    "tianguis_id": 1, // obligatorio si commerce_type = tianguis
    "route_id": null, // obligatorio si commerce_type = route
    "meters": 3,
    "meters_reason": "Ampliación", // sólo en PUT, al cambiar metros
    "location": "Av. Juárez frente al kiosco",
    "location_reference": "Puesto 14",
    "latitude": 19.4326077,
    "longitude": -99.133208,
    "notes": null
}
```

`POST` responde **201** si se creó y **200** si el `uuid` ya existía (devuelve el mismo comerciante con su folio). El folio `COM-######` lo asigna el servidor.

### `GET /merchants/{id}/account`

Estado de cuenta (`?fiscal_year=2026` opcional):

```json
{
    "data": {
        "fiscal_year": 2026,
        "merchant": {
            "folio": "COM-000001",
            "taxpayer_name": "Juan Pérez López",
            "business_type": "Ropa",
            "location": "Tianguis Municipal",
            "tianguis": "Tianguis Municipal",
            "route": null,
            "meters": "3.00"
        },
        "annuality": {
            "concept": "Plaza Uso de Suelo Vía Pública",
            "amount": "155.00",
            "fiscal_year": 2026,
            "status": "paid",
            "status_label": "PAGADO",
            "receipt_folio": "REC-00000012"
        },
        "annualities": [
            {
                "fiscal_year": 2026,
                "amount": "155.00",
                "status": "paid",
                "receipt_folio": "REC-00000012"
            }
        ],
        "tickets": { "quantity": 10, "unit_price": "40.00", "total": "400.00" },
        "other_total": "0.00",
        "total_paid": "555.00",
        "debt": { "count": 0, "total": "0.00", "years": [] }
    }
}
```

### `GET /merchants/{id}/payments` · `GET /merchants/{id}/receipts`

Historial paginado de pagos y de recibos (con sus reimpresiones).

## Cobros

### `POST /payments`

```json
{
    "uuid": "0fb57a95-691f-4113-8310-281776737f1c", // OBLIGATORIO en la app: uno por operación
    "merchant_id": 5,
    "payment_method": "cash", // cash | transfer | card | other
    "reference": null,
    "issued_at": "2026-09-30T13:25:00-06:00", // opcional: hora real del cobro sin conexión
    "paper_width": 58, // 58 | 80 para las líneas del ticket
    "items": [
        { "concept_id": 2, "quantity": 5 }, // boletos: 5 × $40 = $200
        { "concept_id": 1, "fiscal_year": 2026 } // anualidad: $155
    ]
}
```

- **201** → cobro nuevo · **200** con `"duplicate": true` → el `uuid` ya se había registrado; se devuelve el recibo original y **no se cobra otra vez**.
- La anualidad no puede cobrarse dos veces para el mismo ejercicio (`annuality_already_paid`).

Respuesta (recortada):

```json
{
    "data": {
        "receipt": {
            "id": 393,
            "uuid": "0fb57a95-…",
            "folio": "REC-00000393",
            "total": "200.00",
            "status": "issued",
            "reprint_count": 0,
            "items": [
                {
                    "concept_code": "BOL-001",
                    "description": "Boleto Tianguista",
                    "quantity": 5,
                    "unit_price": "40.00",
                    "amount": "200.00"
                }
            ]
        },
        "ticket": {
            "header": {
                "municipality": "MUNICIPIO",
                "system": "PADRÓN DE COMERCIANTES",
                "title": "RECIBO DE PAGO"
            },
            "folio": "REC-00000393",
            "date": "30/09/2026",
            "time": "15:57",
            "taxpayer": "JUAN PÉREZ LÓPEZ",
            "merchant_folio": "COM-000005",
            "business_type": "ALIMENTOS",
            "tianguis": null,
            "route": "RUTA 02",
            "items": [
                {
                    "concept": "BOLETO TIANGUISTA",
                    "quantity": 5,
                    "unit_price": "40.00",
                    "amount": "200.00"
                }
            ],
            "total": "200.00",
            "currency": "MXN",
            "payment_method": "EFECTIVO",
            "user": "RECAUDADOR DEMO",
            "reprint": null,
            "qr_content": "https://…/verificar/0fb57a95-…",
            "paper_width": 58,
            "lines": [
                {
                    "text": "MUNICIPIO",
                    "align": "center",
                    "bold": true,
                    "size": "normal"
                },
                {
                    "text": "Folio:              REC-00000393",
                    "align": "left",
                    "bold": false,
                    "size": "normal"
                }
            ],
            "pdf_url": "https://…/api/v1/receipts/393/pdf"
        }
    },
    "duplicate": false,
    "message": "Cobro registrado."
}
```

`ticket.lines` ya viene ajustado a 32 columnas (58 mm) o 48 (80 mm) para impresoras térmicas ESC/POS: imprima cada línea con su alineación y negrita (`size: "double"` = doble alto/ancho) y al final el QR con `qr_content`.

### `GET /payments/{id}`

Detalle de un pago (renglón de concepto).

## Recibos, impresión y reimpresión

| Método | Ruta                     | Descripción                                                                                                                                                             |
| ------ | ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| GET    | `/receipts/{id}`         | Recibo + ticket (`?paper_width=80`)                                                                                                                                     |
| GET    | `/receipts/{id}/pdf`     | Ticket en PDF (`?reprint={id}` para la versión con leyenda)                                                                                                             |
| POST   | `/receipts/{id}/print`   | **Impresión original**, una sola vez. Devuelve el ticket. Si ya se imprimió → `422 already_printed`                                                                     |
| POST   | `/receipts/{id}/reprint` | `{ "reason": "Ticket dañado" }` → **201** con el ticket que incluye `reprint: { number, reason, date, time, user }` y la leyenda `*** REIMPRESIÓN ***`. No genera cobro |
| POST   | `/receipts/{id}/cancel`  | `{ "reason": "…" }` (mín. 10 caracteres). Sólo administradores                                                                                                          |

Flujo sugerido en la app: `POST /payments` → imprimir `ticket.lines` → `POST /receipts/{id}/print`. Si la impresora falla después de marcar la impresión, pedir motivo y usar `/reprint`.

## Sincronización sin conexión

La app guarda en Room una cola de operaciones; cada una con su **UUID generado en el dispositivo**. Al recuperar la conexión envía la cola en lotes (máx. 100):

### `POST /sync`

```json
{
    "operations": [
        {
            "uuid": "9b1d…",
            "type": "merchant.create",
            "payload": {
                "taxpayer_name": "María López",
                "business_type_id": 3,
                "commerce_type": "public_road",
                "meters": 2
            }
        },
        {
            "uuid": "4e2a…",
            "type": "payment.create",
            "payload": {
                "merchant_uuid": "9b1d…", // comerciante dado de alta sin conexión (o merchant_id)
                "payment_method": "cash",
                "issued_at": "2026-09-30T09:12:00-06:00",
                "items": [{ "concept_id": 2, "quantity": 3 }]
            }
        }
    ]
}
```

Respuesta — un resultado por operación, en el mismo orden:

```json
{
    "data": [
        {
            "uuid": "9b1d…",
            "type": "merchant.create",
            "status": "created",
            "id": 61,
            "folio": "COM-000061"
        },
        {
            "uuid": "4e2a…",
            "type": "payment.create",
            "status": "created",
            "id": 394,
            "folio": "REC-00000394",
            "total": "120.00"
        }
    ],
    "server_time": "2026-09-30T16:02:11-06:00"
}
```

| `status`    | Acción en la app                                                                     |
| ----------- | ------------------------------------------------------------------------------------ |
| `created`   | Marcar sincronizada; guardar `id`/`folio` del servidor                               |
| `duplicate` | Ya estaba aplicada (reenvío); marcar sincronizada                                    |
| `rejected`  | No reintentar; mostrar `message`/`code` al usuario (p. ej. `annuality_already_paid`) |

Reenviar un lote completo es seguro: el servidor nunca aplica dos veces el mismo UUID. Las operaciones se procesan en orden, así que un alta de comerciante debe ir antes que sus cobros.

Para refrescar datos locales: `GET /catalogs` y `GET /merchants?updated_since=<server_time anterior>&per_page=500`.

## Dashboard y reportes

- `GET /dashboard` — indicadores (`?mine=1` limita a los cobros del usuario): `revenue_today`, `charges_today`, `tickets_today`, `annualities_paid_today`, `merchants_total`, …
- `GET /reports?type=revenue|tickets|annualities|merchants|reprints` — mismos filtros que el panel web (`date_from`, `date_to`, `user_id`, `tianguis_id`, `route_id`, `concept_id`, `fiscal_year`, `status`). Requiere permiso `reports.view`.
