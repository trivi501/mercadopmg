package mx.gob.padron.cobro.data.repository

import android.content.Context
import mx.gob.padron.cobro.data.SessionStore
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ApiService
import mx.gob.padron.cobro.data.remote.DashboardDto
import mx.gob.padron.cobro.data.remote.PaperRequest
import mx.gob.padron.cobro.data.remote.ReasonRequest
import mx.gob.padron.cobro.data.remote.ReceiptDto
import mx.gob.padron.cobro.data.remote.ReceiptPayload
import mx.gob.padron.cobro.data.remote.apiCall
import mx.gob.padron.cobro.data.remote.map
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.time.LocalDate

/**
 * Recibos: consulta, impresión original (una sola vez) y reimpresión controlada.
 * Ninguna de estas operaciones genera un cobro.
 */
class ReceiptRepository(
    private val api: ApiService,
    private val session: SessionStore,
    private val context: Context,
) {
    suspend fun get(id: Int): ApiResult<ReceiptPayload> = apiCall { api.receipt(id, session.paperWidth) }.map { it.data }

    /** Registra la impresión original en el servidor. Si ya se imprimió responde `already_printed`. */
    suspend fun markPrinted(id: Int): ApiResult<ReceiptPayload> =
        apiCall { api.markPrinted(id, PaperRequest(session.paperWidth)) }.map { it.data }

    /** Reimpresión con motivo: queda numerada y el ticket trae la leyenda REIMPRESIÓN. */
    suspend fun reprint(id: Int, reason: String): ApiResult<ReceiptPayload> =
        apiCall { api.reprint(id, ReasonRequest(reason.trim()), session.paperWidth) }.map { it.data }

    suspend fun myReceiptsToday(): ApiResult<List<ReceiptDto>> =
        apiCall { api.receipts(mine = 1, dateFrom = LocalDate.now().toString()) }.map { it.data }

    suspend fun dashboard(): ApiResult<DashboardDto> = apiCall { api.dashboard(mine = 1) }.map { it.data }

    /** Descarga el PDF del ticket a caché para compartirlo. */
    suspend fun downloadPdf(id: Int, folio: String): ApiResult<File> =
        when (val r = apiCall { api.receiptPdf(id) }) {
            is ApiResult.Success -> withContext(Dispatchers.IO) {
                val dir = File(context.cacheDir, "tickets").apply { mkdirs() }
                val file = File(dir, "recibo-$folio.pdf")
                r.value.use { body -> file.outputStream().use { out -> body.byteStream().copyTo(out) } }
                ApiResult.Success(file)
            }
            is ApiResult.Failure -> r
            is ApiResult.Offline -> r
        }
}
