package mx.gob.padron.cobro

import mx.gob.padron.cobro.data.local.MerchantEntity
import mx.gob.padron.cobro.print.TicketFormatter
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDateTime

class TicketFormatterTest {

    private val merchant = MerchantEntity(
        id = 1, uuid = "u", folio = "COM-000001", taxpayerName = "Juan Pérez López", tradeName = null,
        businessType = "Ropa", businessDescription = null, phone = null, location = null, tianguisId = 1,
        tianguis = "Tianguis Municipal", route = "Ruta 01", meters = "3.00", commerceTypeLabel = "Tianguis",
        status = "active", statusLabel = "Activo", searchText = "",
    )

    private fun ticket(width: Int) = TicketFormatter.provisional(
        municipality = "Municipio",
        footer = "Conserve este recibo.",
        operationUuid = "0fb57a95-691f-4113-8310-281776737f1c",
        merchant = merchant,
        lines = listOf(TicketFormatter.Line("Boleto Tianguista", 5, "40.00", "200.00")),
        total = "200.00",
        paymentMethod = "cash",
        user = "Recaudador",
        paperWidth = width,
        now = LocalDateTime.of(2026, 9, 30, 13, 25),
    )

    @Test
    fun `ningun renglon excede el ancho del papel`() {
        listOf(58 to 32, 80 to 48).forEach { (width, cols) ->
            ticket(width).lines.forEach { line ->
                assertTrue("'${line.text}' excede $cols columnas", line.text.length <= cols)
            }
        }
    }

    @Test
    fun `el comprobante provisional lo indica y contiene el total`() {
        val t = ticket(58)
        val text = t.lines.joinToString("\n") { it.text }

        assertEquals("PENDIENTE", t.folio)
        assertTrue(text.contains("COMPROBANTE PROVISIONAL"))
        assertTrue(text.contains("COM-000001"))
        assertTrue(text.contains("$200.00 MXN"))
        assertTrue(text.contains("30/09/2026"))
    }

    @Test
    fun `pares etiqueta valor se justifican`() {
        assertEquals("Folio:" + " ".repeat(14) + "REC-00000001", TicketFormatter.pair("Folio:", "REC-00000001", 32))
    }
}
