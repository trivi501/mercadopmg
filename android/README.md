# Cobro Padrón — App Android

App de campo para **cobro** del Padrón de Comerciantes: buscar o escanear al comerciante, cobrar boletos y anualidad, imprimir el ticket en impresora térmica Bluetooth y trabajar sin conexión.

Kotlin · Jetpack Compose (Material 3) · MVVM + Repository · Retrofit + kotlinx.serialization · Room · WorkManager · EncryptedSharedPreferences · zxing (QR).

## Abrir y compilar

1. Abra la carpeta `android/` en **Android Studio**.
2. En *Settings › Build Tools › Gradle › Gradle JDK* elija un **JDK 17 o 21**. El JDK 25 que trae Android Studio no es compatible con Gradle 8.11 del proyecto (en esta Mac: `/opt/homebrew/opt/openjdk@21`).
   Si Android Studio ofrece actualizar el Android Gradle Plugin, puede aceptarlo después de probar que todo funciona.
3. Ejecute en un emulador o teléfono con Android 8.0 (API 26) o superior.

Desde terminal:

```bash
export JAVA_HOME=/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home
./gradlew assembleDebug         # app/build/outputs/apk/debug/app-debug.apk
./gradlew testDebugUnitTest     # pruebas unitarias (cálculos, ticket, ESC/POS)
./gradlew assembleRelease       # APK de producción minificado (firme el APK antes de distribuirlo)
```

`local.properties` (ruta del SDK, `sdk.dir=~/Library/Android/sdk`) no se versiona; Android Studio lo crea solo.

## Servidor

- **Debug**: por omisión `http://10.0.2.2:8010` (el `localhost` de la Mac visto desde el emulador). Levante el backend con `php artisan serve --host=0.0.0.0 --port=8010`. En un teléfono físico use la IP de la Mac en la red local (p. ej. `http://192.168.1.50:8010`) desde *Configurar servidor* en el login o en Ajustes.
- **Release**: sólo HTTPS (`network_security_config`). Cambie `DEFAULT_SERVER_URL` en `app/build.gradle.kts`.

Sólo pueden entrar usuarios con permiso de cobro (`payments.create`: recaudador o administrador).

## Flujo de cobro

1. **Inicio** → *Escanear QR* (el QR del comerciante contiene sólo su folio) o *Buscar* por folio, nombre o teléfono.
2. **Ficha** del comerciante: datos, estado de cuenta (anualidad PAGADO/PENDIENTE, boletos, adeudos) e historial.
3. **Cobrar**: boletos (`cantidad × $40.00`) y/o anualidad pendiente (`$155.00`), forma de pago, confirmación.
4. **Recibo**: *Imprimir ticket* registra la impresión original en el servidor (sólo una vez) y lo envía a la impresora. *Reimprimir* pide motivo, queda numerada y el ticket dice **REIMPRESIÓN**; no genera cobro. *Compartir PDF*.

Si la impresora falla, *Reintentar impresión* reenvía el mismo ticket sin registrar otra impresión.

## Sin conexión

- El padrón se descarga en segundo plano (incremental con `updated_since`) para buscar sin red.
- Un cobro sin conexión se guarda en Room con su **UUID** y se imprime un **COMPROBANTE PROVISIONAL**. WorkManager lo envía a `POST /api/v1/sync` al recuperar la red; reenviar nunca duplica el cobro.
- El recibo oficial (folio `REC-`) aparece en *Historial › Sin conexión* una vez sincronizado y puede imprimirse desde ahí.
- La anualidad **no** se cobra sin conexión (hay que verificar en el servidor que no esté pagada).
- Los cobros pendientes pertenecen al usuario que los capturó y sólo se envían con su sesión.

## Impresora

- Impresoras térmicas Bluetooth clásicas (SPP) de 58 mm (32 columnas) o 80 mm (48 columnas), comandos ESC/POS.
- Vincule la impresora en los ajustes de Bluetooth de Android y selecciónela en *Ajustes › Impresora térmica*; use *Imprimir prueba*.
- Acentos y Ñ en página de códigos CP850; QR nativo de la impresora (`GS ( k`). Si su modelo no imprime el QR, el resto del ticket se imprime normalmente.

## Estructura

```
app/src/main/java/mx/gob/padron/cobro/
├── data/
│   ├── remote/      ApiService (Retrofit), DTOs, ApiClient, ApiResult
│   ├── local/       Room: comerciantes, conceptos, cola de cobros
│   ├── repository/  Auth, Catalog, Merchant, Charge, Receipt
│   └── SessionStore (token y ajustes cifrados)
├── print/           EscPos, BluetoothPrinter, TicketFormatter, TicketPrinter
├── sync/            SyncWorker, SyncScheduler (WorkManager)
├── ui/              login, home, search, merchant, charge, receipt, history, settings
└── util/Money.kt    importes con BigDecimal
```
