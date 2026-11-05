package mx.gob.padron.cobro.print

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Color
import mx.gob.padron.cobro.R

/**
 * Escudo del Ayuntamiento para el encabezado del ticket térmico, ya convertido
 * a puntos de impresora (ESC/POS raster). Se calcula una vez por ancho de papel.
 */
class TicketLogo(private val context: Context) {
    private val cache = mutableMapOf<Int, ByteArray?>()

    /** @return bytes ESC/POS del logo, o null si no se pudo cargar la imagen. */
    fun forPaper(paperWidth: Int): ByteArray? = synchronized(cache) {
        cache.getOrPut(paperWidth) { runCatching { encode(heightDots(paperWidth)) }.getOrNull() }
    }

    private fun encode(height: Int): ByteArray {
        val source = BitmapFactory.decodeResource(context.resources, R.drawable.ticket_logo)
        val width = source.width * height / source.height
        val bitmap = Bitmap.createScaledBitmap(source, width, height, true)
        val pixels = IntArray(width * height)
        bitmap.getPixels(pixels, 0, width, 0, 0, width, height)

        return EscPos.raster(width, height) { x, y ->
            val p = pixels[y * width + x]
            val luminance = (Color.red(p) * 299 + Color.green(p) * 587 + Color.blue(p) * 114) / 1000
            Color.alpha(p) > 128 && luminance < 128
        }
    }

    companion object {
        /** 58 mm ≈ 384 puntos de ancho; 80 mm ≈ 576. El escudo ocupa ~3 y ~3.5 cm de alto. */
        fun heightDots(paperWidth: Int): Int = if (paperWidth == 80) 240 else 192
    }
}
