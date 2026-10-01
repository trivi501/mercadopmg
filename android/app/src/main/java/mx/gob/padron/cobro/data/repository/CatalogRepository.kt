package mx.gob.padron.cobro.data.repository

import kotlinx.coroutines.flow.Flow
import mx.gob.padron.cobro.data.SessionStore
import mx.gob.padron.cobro.data.local.ConceptDao
import mx.gob.padron.cobro.data.local.ConceptEntity
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ApiService
import mx.gob.padron.cobro.data.remote.apiCall
import mx.gob.padron.cobro.data.remote.map

class CatalogRepository(
    private val api: ApiService,
    private val concepts: ConceptDao,
    private val session: SessionStore,
) {
    val allConcepts: Flow<List<ConceptEntity>> = concepts.observeAll()

    suspend fun conceptsNow(): List<ConceptEntity> = concepts.all()

    /**
     * Descarga conceptos y datos del ticket; quedan disponibles sin conexión.
     * Devuelve la hora del servidor (para la descarga incremental del padrón).
     */
    suspend fun refresh(): ApiResult<String?> {
        val result = apiCall { api.catalogs() }
        if (result is ApiResult.Success) {
            val data = result.value.data
            concepts.replaceAll(
                data.concepts.filter { it.isActive }.map {
                    ConceptEntity(id = it.id, code = it.code, name = it.name, type = it.type, amount = it.amount)
                },
            )
            data.settings.municipalityName?.let { session.municipalityName = it }
            data.settings.ticketFooter?.let { session.ticketFooter = it }
        }
        return result.map { it.data.serverTime }
    }
}
