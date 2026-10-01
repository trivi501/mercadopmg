package mx.gob.padron.cobro.print

import mx.gob.padron.cobro.data.SessionStore
import mx.gob.padron.cobro.data.remote.TicketDto
import mx.gob.padron.cobro.data.remote.TicketLine

/**
 * Imprime un ticket (oficial o provisional) en la impresora configurada.
 */
class TicketPrinter(
    private val printer: BluetoothPrinter,
    private val session: SessionStore,
) {
    val isConfigured: Boolean get() = session.printerAddress != null

    /** @return null si se imprimió, o el mensaje de error para el usuario. */
    suspend fun print(ticket: TicketDto): String? {
        val address = session.printerAddress ?: return "Configure la impresora en Ajustes."

        return try {
            printer.print(address, EscPos.build(ticket.lines, ticket.qrContent))
            null
        } catch (e: BluetoothPrinter.PrinterException) {
            e.message
        }
    }

    suspend fun printTest(): String? {
        val address = session.printerAddress ?: return "Seleccione una impresora."
        val cols = TicketFormatter.columns(session.paperWidth)
        val lines = listOf(
            TicketLine(session.municipalityName.uppercase(), "center", bold = true),
            TicketLine("PRUEBA DE IMPRESIÓN", "center", bold = true, size = "double"),
            TicketLine("-".repeat(cols)),
            TicketLine(TicketFormatter.pair("Papel:", "${session.paperWidth} mm", cols)),
            TicketLine(TicketFormatter.pair("Columnas:", cols.toString(), cols)),
            TicketLine("Acentos: áéíóú ÁÉÍÓÚ ñÑ ü"),
            TicketLine("1234567890".repeat(5).take(cols)),
        )

        return try {
            printer.print(address, EscPos.build(lines, "PRUEBA"))
            null
        } catch (e: BluetoothPrinter.PrinterException) {
            e.message
        }
    }
}
