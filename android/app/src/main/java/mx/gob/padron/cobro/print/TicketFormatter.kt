package mx.gob.padron.cobro.print

import mx.gob.padron.cobro.data.local.MerchantEntity
import mx.gob.padron.cobro.data.remote.TicketDto
import mx.gob.padron.cobro.data.remote.TicketHeader
import mx.gob.padron.cobro.data.remote.TicketItem
import mx.gob.padron.cobro.data.remote.TicketLine
import mx.gob.padron.cobro.util.Money
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter

/**
 * Arma tickets en la app. El ticket oficial lo genera el servidor (TicketPresenter);
 * aquí sólo se arma el COMPROBANTE PROVISIONAL de un cobro capturado sin conexión,
 * con el mismo formato de columnas.
 */
object TicketFormatter {

    /** Renglón de concepto capturado en la pantalla de cobro. */
    data class Line(val concept: String, val quantity: Int, val unitPrice: String, val amount: String)

    fun columns(paperWidth: Int): Int = if (paperWidth == 80) 48 else 32

    private val paymentLabels = mapOf("cash" to "EFECTIVO", "transfer" to "TRANSFERENCIA", "card" to "TARJETA", "other" to "OTRO")

    fun provisional(
        municipality: String,
        footer: String,
        operationUuid: String,
        merchant: MerchantEntity,
        lines: List<Line>,
        total: String,
        paymentMethod: String,
        user: String,
        paperWidth: Int,
        now: LocalDateTime = LocalDateTime.now(),
    ): TicketDto {
        val cols = columns(paperWidth)
        val date = now.format(DateTimeFormatter.ofPattern("dd/MM/yyyy"))
        val time = now.format(DateTimeFormatter.ofPattern("HH:mm"))
        val method = paymentLabels[paymentMethod] ?: paymentMethod.uppercase()
        val out = mutableListOf<TicketLine>()

        fun center(text: String, bold: Boolean = false, size: String = "normal") = out.add(TicketLine(text, "center", bold, size))
        fun left(text: String, bold: Boolean = false) = out.add(TicketLine(text, "left", bold))
        fun sep() = left("-".repeat(cols))
        fun pair(label: String, value: String, bold: Boolean = false) = left(pair(label, value, cols), bold)

        center(municipality.uppercase(), bold = true)
        center("PADRÓN DE COMERCIANTES", bold = true)
        center("COMPROBANTE PROVISIONAL", bold = true, size = "double")
        center("Pendiente de sincronizar")
        sep()
        pair("Operación:", operationUuid.take(8).uppercase())
        pair("Fecha:", date)
        pair("Hora:", time)
        sep()
        left("Contribuyente:")
        left(merchant.taxpayerName.uppercase(), bold = true)
        pair("Folio contribuyente:", merchant.folio)
        merchant.businessType?.let { left("Giro: ${it.uppercase()}") }
        merchant.tianguis?.let { left("Tianguis: ${it.uppercase()}") }
        merchant.route?.let { left("Ruta: ${it.uppercase()}") }
        sep()
        lines.forEach {
            left("Concepto: ${it.concept.uppercase()}")
            pair("Cantidad:", it.quantity.toString())
            pair("Precio unitario:", Money.format(it.unitPrice))
            pair("Importe:", Money.format(it.amount))
            left("")
        }
        sep()
        pair("TOTAL:", "${Money.format(total)} MXN", bold = true)
        pair("Forma de pago:", method)
        pair("Usuario:", user.uppercase())
        sep()
        center("El folio oficial del recibo se")
        center("asignará al sincronizar.")
        center(footer)

        return TicketDto(
            header = TicketHeader(municipality.uppercase(), "PADRÓN DE COMERCIANTES", "COMPROBANTE PROVISIONAL"),
            folio = "PENDIENTE",
            date = date,
            time = time,
            taxpayer = merchant.taxpayerName.uppercase(),
            merchantFolio = merchant.folio,
            businessType = (merchant.businessType ?: "").uppercase(),
            tianguis = merchant.tianguis?.uppercase(),
            route = merchant.route?.uppercase(),
            items = lines.map { TicketItem(it.concept.uppercase(), it.quantity, it.unitPrice, it.amount) },
            total = total,
            paymentMethod = method,
            user = user.uppercase(),
            qrContent = operationUuid,
            footer = footer,
            paperWidth = paperWidth,
            lines = wrap(out, cols),
        )
    }

    /** "Etiqueta:      valor" justificado al ancho del papel. */
    fun pair(label: String, value: String, cols: Int): String {
        val space = cols - label.length - value.length
        return if (space >= 1) label + " ".repeat(space) + value else "$label $value"
    }

    /** Parte renglones que excedan el ancho del papel. */
    fun wrap(lines: List<TicketLine>, cols: Int): List<TicketLine> = lines.flatMap { line ->
        if (line.text.length <= cols) {
            listOf(line)
        } else {
            line.text.chunked(cols).map { line.copy(text = it) }
        }
    }
}
