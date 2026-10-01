package mx.gob.padron.cobro.data.repository

import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.flowOf
import mx.gob.padron.cobro.data.SessionStore
import mx.gob.padron.cobro.data.local.MerchantEntity
import mx.gob.padron.cobro.data.local.PendingChargeDao
import mx.gob.padron.cobro.data.local.PendingChargeEntity
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ApiService
import mx.gob.padron.cobro.data.remote.AppJson
import mx.gob.padron.cobro.data.remote.ChargeRequest
import mx.gob.padron.cobro.data.remote.ReceiptPayload
import mx.gob.padron.cobro.data.remote.SyncOperation
import mx.gob.padron.cobro.data.remote.SyncRequest
import mx.gob.padron.cobro.data.remote.TicketDto
import mx.gob.padron.cobro.data.remote.apiCall
import mx.gob.padron.cobro.print.TicketFormatter
import mx.gob.padron.cobro.sync.SyncScheduler
import kotlinx.serialization.json.jsonObject
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter

/**
 * Registro de cobros. Si no hay conexión el cobro se guarda en la cola local
 * con su UUID y se sincroniza después sin riesgo de duplicarse.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ChargeRepository(
    private val api: ApiService,
    private val pending: PendingChargeDao,
    private val session: SessionStore,
    private val scheduler: SyncScheduler,
) {
    sealed interface Outcome {
        /** Cobro registrado en el servidor; [payload] trae recibo oficial y ticket. */
        data class Issued(val payload: ReceiptPayload, val duplicate: Boolean) : Outcome

        /** Sin conexión: se guardó en la cola. [ticket] es un comprobante provisional. */
        data class Queued(val entity: PendingChargeEntity, val ticket: TicketDto) : Outcome

        data class Rejected(val code: String?, val message: String) : Outcome
    }

    /** Cobros sin enviar del usuario con sesión activa. */
    val pendingCount: Flow<Int> = session.user.flatMapLatest { user ->
        if (user == null) flowOf(0) else pending.observePendingCount(user.id)
    }

    val queue: Flow<List<PendingChargeEntity>> = session.user.flatMapLatest { user ->
        if (user == null) flowOf(emptyList()) else pending.observeForUser(user.id)
    }

    suspend fun charge(
        request: ChargeRequest,
        merchant: MerchantEntity,
        description: String,
        total: String,
        lines: List<TicketFormatter.Line>,
    ): Outcome {
        // Hora real del cobro: si se encola, el recibo conserva esta fecha.
        val stamped = request.copy(issuedAt = OffsetDateTime.now().format(DateTimeFormatter.ISO_OFFSET_DATE_TIME))

        return when (val result = apiCall { api.charge(stamped) }) {
            is ApiResult.Success -> Outcome.Issued(result.value.data, result.value.duplicate)
            is ApiResult.Failure -> Outcome.Rejected(result.code, result.message)
            is ApiResult.Offline -> {
                val entity = PendingChargeEntity(
                    uuid = stamped.uuid,
                    userId = session.user.value?.id ?: return Outcome.Rejected("unauthenticated", "Inicie sesión de nuevo."),
                    merchantId = merchant.id,
                    merchantFolio = merchant.folio,
                    merchantName = merchant.taxpayerName,
                    payloadJson = AppJson.encodeToString(ChargeRequest.serializer(), stamped),
                    total = total,
                    description = description,
                    createdAt = System.currentTimeMillis(),
                )
                pending.insert(entity)
                scheduler.syncSoon()

                val ticket = TicketFormatter.provisional(
                    municipality = session.municipalityName,
                    footer = session.ticketFooter,
                    operationUuid = stamped.uuid,
                    merchant = merchant,
                    lines = lines,
                    total = total,
                    paymentMethod = request.paymentMethod,
                    user = session.user.value?.name.orEmpty(),
                    paperWidth = session.paperWidth,
                )
                Outcome.Queued(entity, ticket)
            }
        }
    }

    /**
     * Envía la cola al servidor en lotes. Devuelve cuántas operaciones quedaron resueltas
     * o el error de red/servidor para que WorkManager reintente.
     */
    suspend fun pushPending(): ApiResult<Int> {
        val userId = session.user.value?.id ?: return ApiResult.Success(0)
        var processed = 0

        while (true) {
            val batch = pending.nextBatch(userId, BATCH_SIZE)
            if (batch.isEmpty()) break

            val operations = batch.map {
                SyncOperation(
                    uuid = it.uuid,
                    type = "payment.create",
                    payload = AppJson.parseToJsonElement(it.payloadJson).jsonObject,
                )
            }
            pending.incrementAttempts(batch.map { it.uuid })

            when (val result = apiCall { api.sync(SyncRequest(operations)) }) {
                is ApiResult.Success -> {
                    result.value.data.forEach { r ->
                        when (r.status) {
                            "created", "duplicate" -> pending.markSynced(r.uuid, r.id, r.folio)
                            else -> pending.markRejected(r.uuid, r.code, r.message)
                        }
                    }
                    processed += result.value.data.size
                }
                is ApiResult.Failure -> return result
                is ApiResult.Offline -> return result
            }
        }

        pending.purgeSynced(System.currentTimeMillis() - KEEP_SYNCED_MS)
        return ApiResult.Success(processed)
    }

    private companion object {
        const val BATCH_SIZE = 50
        const val KEEP_SYNCED_MS = 7L * 24 * 60 * 60 * 1000
    }
}
