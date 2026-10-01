package mx.gob.padron.cobro.print

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.withContext
import java.io.IOException
import java.util.UUID

/**
 * Impresora térmica Bluetooth clásica (perfil SPP). La impresora debe estar
 * vinculada previamente en los ajustes de Bluetooth de Android.
 */
class BluetoothPrinter(private val context: Context) {

    data class Device(val name: String, val address: String)

    private val adapter get() = context.getSystemService(BluetoothManager::class.java)?.adapter

    fun hasPermission(): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.S ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.BLUETOOTH_CONNECT) == PackageManager.PERMISSION_GRANTED

    fun isEnabled(): Boolean = adapter?.isEnabled == true

    @SuppressLint("MissingPermission")
    fun pairedDevices(): List<Device> {
        if (!hasPermission()) return emptyList()
        return adapter?.bondedDevices.orEmpty()
            .map { Device(it.name ?: it.address, it.address) }
            .sortedBy { it.name }
    }

    /** Envía los bytes ESC/POS. Lanza [PrinterException] con un mensaje para el usuario. */
    @SuppressLint("MissingPermission")
    suspend fun print(address: String, bytes: ByteArray) = withContext(Dispatchers.IO) {
        if (!hasPermission()) throw PrinterException("Falta el permiso de Bluetooth.")
        val btAdapter = adapter ?: throw PrinterException("Este dispositivo no tiene Bluetooth.")
        if (!btAdapter.isEnabled) throw PrinterException("Active el Bluetooth.")

        val device = runCatching { btAdapter.getRemoteDevice(address) }.getOrNull()
            ?: throw PrinterException("Impresora no válida. Selecciónela de nuevo en Ajustes.")

        val socket = try {
            device.createRfcommSocketToServiceRecord(SPP_UUID)
        } catch (e: IOException) {
            throw PrinterException("No se pudo abrir la conexión con la impresora.", e)
        }

        try {
            socket.connect()
            val stream = socket.outputStream
            // Envío por bloques: algunas impresoras pierden datos con búferes grandes.
            var offset = 0
            while (offset < bytes.size) {
                val length = minOf(CHUNK, bytes.size - offset)
                stream.write(bytes, offset, length)
                stream.flush()
                offset += length
                delay(15)
            }
            // Da tiempo a que la impresora termine antes de cerrar el socket.
            delay(400)
        } catch (e: IOException) {
            throw PrinterException("No se pudo imprimir. Verifique que la impresora esté encendida y cerca.", e)
        } finally {
            runCatching { socket.close() }
        }
    }

    class PrinterException(message: String, cause: Throwable? = null) : Exception(message, cause)

    private companion object {
        val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
        const val CHUNK = 512
    }
}
