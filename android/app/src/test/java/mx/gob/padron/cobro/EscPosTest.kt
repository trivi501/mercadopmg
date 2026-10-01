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
