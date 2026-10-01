package mx.gob.padron.cobro.data.repository

import kotlinx.coroutines.flow.Flow
import mx.gob.padron.cobro.data.SessionStore
import mx.gob.padron.cobro.data.local.MerchantDao
import mx.gob.padron.cobro.data.local.MerchantEntity
import mx.gob.padron.cobro.data.remote.AccountDto
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ApiService
import mx.gob.padron.cobro.data.remote.MerchantDto
import mx.gob.padron.cobro.data.remote.ReceiptDto
import mx.gob.padron.cobro.data.remote.apiCall
import mx.gob.padron.cobro.data.remote.map
import mx.gob.padron.cobro.util.normalizedForSearch

/**
 * Consulta del padrón: en línea cuando hay conexión y desde Room cuando no.
 */
class MerchantRepository(
    private val api: ApiService,
    private val dao: MerchantDao,
    private val session: SessionStore,
) {
    val cachedCount: Flow<Int> = dao.count()

    /** Resultado de búsqueda con indicación de si viene de la copia local. */
    data class SearchResult(val merchants: List<MerchantEntity>, val offline: Boolean, val error: String? = null)

    suspend fun search(term: String): SearchResult {
        val query = term.trim()
        if (query.length < 2) return SearchResult(emptyList(), offline = false)

        return when (val result = apiCall { api.merchants(search = query, perPage = 30) }) {
            is ApiResult.Success -> {
                val entities = result.value.data.map { it.toEntity() }
                dao.upsertAll(entities)
                SearchResult(entities, offline = false)
            }
            is ApiResult.Offline -> SearchResult(dao.search(query.normalizedForSearch()), offline = true)
            is ApiResult.Failure -> SearchResult(emptyList(), offline = false, error = result.message)
        }
    }

    /** Búsqueda por el contenido del QR (el folio). */
    suspend fun byFolio(folio: String): ApiResult<MerchantEntity> {
        val clean = folio.trim().uppercase()
        return when (val result = apiCall { api.merchantByFolio(clean) }) {
            is ApiResult.Success -> ApiResult.Success(result.value.data.toEntity().also { dao.upsertAll(listOf(it)) })
            is ApiResult.Offline -> dao.findByFolio(clean)?.let { ApiResult.Success(it) } ?: result
            is ApiResult.Failure -> result
        }
    }

    suspend fun get(id: Int): ApiResult<MerchantEntity> =
        when (val result = apiCall { api.merchant(id) }) {
            is ApiResult.Success -> ApiResult.Success(result.value.data.toEntity().also { dao.upsertAll(listOf(it)) })
            is ApiResult.Offline -> dao.find(id)?.let { ApiResult.Success(it) } ?: result
            is ApiResult.Failure -> result
        }

    suspend fun account(id: Int): ApiResult<AccountDto> = apiCall { api.account(id) }.map { it.data }

    suspend fun receipts(id: Int): ApiResult<List<ReceiptDto>> = apiCall { api.merchantReceipts(id) }.map { it.data }

    /**
     * Descarga incremental del padrón activo para trabajar sin conexión.
     * Usa `updated_since` con la hora del servidor de la sincronización anterior.
     */
    suspend fun syncDown(serverTime: String?): ApiResult<Int> {
        var page = 1
        var total = 0
        val since = session.merchantsSyncedAt

        while (true) {
            val result = apiCall { api.merchants(updatedSince = since, perPage = 500, page = page) }
            when (result) {
                is ApiResult.Success -> {
                    dao.upsertAll(result.value.data.map { it.toEntity() })
                    total += result.value.data.size
                    val meta = result.value.meta
                    if (meta == null || meta.currentPage >= meta.lastPage) break
                    page++
                }
                is ApiResult.Failure -> return result
                is ApiResult.Offline -> return result
            }
        }

        if (serverTime != null) session.merchantsSyncedAt = serverTime
        return ApiResult.Success(total)
    }
}

fun MerchantDto.toEntity() = MerchantEntity(
    id = id,
    uuid = uuid,
    folio = folio,
    taxpayerName = taxpayerName,
    tradeName = tradeName,
    businessType = businessType,
    businessDescription = businessDescription,
    phone = phone,
    location = location,
    tianguisId = tianguisId,
    tianguis = tianguis,
    route = route,
    meters = meters,
    commerceTypeLabel = commerceTypeLabel,
    status = status,
    statusLabel = statusLabel,
    searchText = listOfNotNull(folio, taxpayerName, tradeName, phone, businessType)
        .joinToString(" ")
        .normalizedForSearch(),
)
