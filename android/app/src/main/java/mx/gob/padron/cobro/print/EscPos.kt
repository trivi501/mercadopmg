package mx.gob.padron.cobro.print

import mx.gob.padron.cobro.data.remote.TicketLine
import java.io.ByteArrayOutputStream
import java.nio.charset.Charset
import java.text.Normalizer

/**
 * Codificador ESC/POS para impresoras térmicas de 58 y 80 mm.
 *
 * Los acentos y la Ñ se envían en la página de códigos CP850 (ESC t 2), que
 * soporta la gran mayoría de impresoras genéricas. Si el dispositivo no tiene
 * ese juego de caracteres se imprimen sin acentos.
 */
object EscPos {
    private const val ESC: Byte = 0x1B
    private const val GS: Byte = 0x1D

    private val cp850: Charset? = runCatching { Charset.forName("IBM850") }.getOrNull()

    fun build(lines: List<TicketLine>, qrContent: String?, feedLines: Int = 4): ByteArray {
        val out = ByteArrayOutputStream()

        out.write(byteArrayOf(ESC, 0x40)) // inicializar
        out.write(byteArrayOf(ESC, 0x74, 2)) // página de códigos CP850

        for (line in lines) {
            out.write(byteArrayOf(ESC, 0x61, alignment(line.align)))
            out.write(byteArrayOf(ESC, 0x45, (if (line.bold) 1 else 0).toByte()))
            // "double" = doble alto (conserva el ancho de columnas).
            out.write(byteArrayOf(GS, 0x21, (if (line.size == "double") 0x01 else 0x00).toByte()))
            out.write(encode(line.text))
            out.write(0x0A)
        }

        // Restablecer estilo
        out.write(byteArrayOf(ESC, 0x45, 0))
        out.write(byteArrayOf(GS, 0x21, 0))

        if (!qrContent.isNullOrBlank()) {
            out.write(byteArrayOf(ESC, 0x61, 1))
            out.write(qr(qrContent))
            out.write(0x0A)
        }

        out.write(byteArrayOf(ESC, 0x61, 0))
        out.write(byteArrayOf(ESC, 0x64, feedLines.toByte())) // avanzar papel
        out.write(byteArrayOf(GS, 0x56, 66, 0)) // corte parcial (se ignora si no hay cortador)

        return out.toByteArray()
    }

    /** Código QR nativo de la impresora (GS ( k, modelo 2). */
    fun qr(content: String, moduleSize: Int = 6): ByteArray {
        val data = content.toByteArray(Charsets.US_ASCII)
        val storeLen = data.size + 3
        val out = ByteArrayOutputStream()

        // Modelo 2
        out.write(byteArrayOf(GS, 0x28, 0x6B, 4, 0, 0x31, 0x41, 0x32, 0))
        // Tamaño del módulo
        out.write(byteArrayOf(GS, 0x28, 0x6B, 3, 0, 0x31, 0x43, moduleSize.coerceIn(1, 16).toByte()))
        // Corrección de errores M
        out.write(byteArrayOf(GS, 0x28, 0x6B, 3, 0, 0x31, 0x45, 0x31))
        // Guardar datos
        out.write(byteArrayOf(GS, 0x28, 0x6B, (storeLen % 256).toByte(), (storeLen / 256).toByte(), 0x31, 0x50, 0x30))
        out.write(data)
        // Imprimir
        out.write(byteArrayOf(GS, 0x28, 0x6B, 3, 0, 0x31, 0x51, 0x30))

        return out.toByteArray()
    }

    fun encode(text: String): ByteArray =
        cp850?.let { text.toByteArray(it) } ?: stripAccents(text).toByteArray(Charsets.US_ASCII)

    fun stripAccents(text: String): String =
        Normalizer.normalize(text, Normalizer.Form.NFD).replace(Regex("\\p{Mn}+"), "")

    private fun alignment(align: String): Byte = when (align) {
        "center" -> 1
        "right" -> 2
        else -> 0
    }.toByte()
}
