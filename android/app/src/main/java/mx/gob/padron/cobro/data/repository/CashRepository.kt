package mx.gob.padron.cobro.data.repository

import mx.gob.padron.cobro.data.SessionStore
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ApiService
import mx.gob.padron.cobro.data.remote.CashCurrentDto
import mx.gob.padron.cobro.data.remote.CashPayload
import mx.gob.padron.cobro.data.remote.CloseCashRequest
import mx.gob.padron.cobro.data.remote.OpenCashRequest
import mx.gob.padron.cobro.data.remote.apiCall
import mx.gob.padron.cobro.data.remote.map
import java.util.UUID

/**
 * Cortes de caja: inicio de cobro con fondo inicial y corte final con arqueo.
 * Guarda en la sesión el corte abierto para decidir si se puede cobrar sin conexión.
 */
class CashRepository(
    private val api: ApiService,
    private val session: SessionStore,
) {
    suspend fun current(): ApiResult<CashCurrentDto> =
        apiCall { api.currentCashSession(session.paperWidth) }.map { it.data }.also { r ->
            if (r is ApiResult.Success) remember(r.value.current)
        }

    /** [uuid] fijo por intento: reintentar no abre un segundo corte. */
    suspend fun open(openingAmount: String, notes: String?, uuid: String = UUID.randomUUID().toString()): ApiResult<CashPayload> =
        apiCall { api.openCashSession(OpenCashRequest(uuid, openingAmount, notes?.ifBlank { null }), session.paperWidth) }
            .map { it.data }
            .also { if (it is ApiResult.Success) remember(it.value) }

    suspend fun get(id: Int): ApiResult<CashPayload> =
        apiCall { api.cashSession(id, session.paperWidth) }.map { it.data }

    suspend fun close(id: Int, countedCash: String, notes: String?): ApiResult<CashPayload> =
        apiCall { api.closeCashSession(id, CloseCashRequest(countedCash, notes?.ifBlank { null }), session.paperWidth) }
            .map { it.data }
            .also { if (it is ApiResult.Success) remember(null) }

    /** El servidor rechazó un cobro por no tener corte abierto. */
    fun forget() = remember(null)

    private fun remember(payload: CashPayload?) {
        val open = payload?.session?.takeIf { it.isOpen }
        session.cashSessionId = open?.id
        session.cashSessionFolio = open?.folio
    }
}
