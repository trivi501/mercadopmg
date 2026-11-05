package mx.gob.padron.cobro

import mx.gob.padron.cobro.data.remote.TicketLine
import mx.gob.padron.cobro.print.EscPos
import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class EscPosTest {

    @Test
    fun `inicia la impresora y selecciona CP850`() {
        val bytes = EscPos.build(listOf(TicketLine("HOLA")), qrContent = null)
        assertArrayEquals(byteArrayOf(0x1B, 0x40, 0x1B, 0x74, 2), bytes.copyOfRange(0, 5))
    }

    @Test
    fun `codifica acentos en CP850`() {
        // En CP850: é = 0x82, ñ = 0xA4
        assertArrayEquals(byteArrayOf(0x82.toByte(), 0xA4.toByte()), EscPos.encode("éñ"))
    }

    @Test
    fun `codifica la imagen como mapa de bits GS v 0`() {
        // 10 x 2 puntos: primera fila llena, segunda sólo el primer y el último punto.
        val raster = EscPos.raster(10, 2) { x, y -> y == 0 || x == 0 || x == 9 }
        val header = byteArrayOf(0x1D, 0x76, 0x30, 0, 2, 0, 2, 0) // 2 bytes por renglón, 2 renglones
        assertArrayEquals(header, raster.copyOfRange(0, 8))
        assertArrayEquals(
            byteArrayOf(0xFF.toByte(), 0xC0.toByte(), 0x80.toByte(), 0x40),
            raster.copyOfRange(8, raster.size),
        )
    }

    @Test
    fun `el logo se imprime centrado antes del texto`() {
        val logo = EscPos.raster(8, 1) { _, _ -> true }
        val bytes = EscPos.build(listOf(TicketLine("HOLA")), qrContent = null, logo = logo)
        val start = 5 + 3 // inicializar + CP850, luego ESC a 1
        assertArrayEquals(byteArrayOf(0x1B, 0x61, 1), bytes.copyOfRange(5, 8))
        assertArrayEquals(logo, bytes.copyOfRange(start, start + logo.size))
    }

    @Test
    fun `el qr declara la longitud de los datos`() {
        val content = "https://x/verificar/abc"
        val qr = EscPos.qr(content)
        val store = byteArrayOf(0x1D, 0x28, 0x6B)
        // Cuarto comando (guardar datos): pL = longitud + 3
        val index = (0 until qr.size - 3).filter { i ->
            qr[i] == store[0] && qr[i + 1] == store[1] && qr[i + 2] == store[2]
        }[3]
        assertEquals((content.length + 3).toByte(), qr[index + 3])
        assertTrue(String(qr, Charsets.US_ASCII).contains(content))
    }
}
