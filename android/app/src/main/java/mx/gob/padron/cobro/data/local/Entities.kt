package mx.gob.padron.cobro.data.local

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey

/** Copia local del padrón para buscar y cobrar sin conexión. */
@Entity(tableName = "merchants", indices = [Index(value = ["folio"], unique = true)])
data class MerchantEntity(
    @PrimaryKey val id: Int,
    val uuid: String,
    val folio: String,
    val taxpayerName: String,
    val tradeName: String?,
    val businessType: String?,
    val businessDescription: String?,
    val phone: String?,
    val location: String?,
    val tianguisId: Int?,
    val tianguis: String?,
    val route: String?,
    val meters: String,
    val commerceTypeLabel: String,
    val status: String,
    val statusLabel: String,
    /** Texto normalizado (minúsculas, sin acentos) para la búsqueda local. */
    val searchText: String,
)

@Entity(tableName = "concepts")
data class ConceptEntity(
    @PrimaryKey val id: Int,
    val code: String,
    val name: String,
    /** ticket | annuality | other */
    val type: String,
    val amount: String,
)

/**
 * Cobro capturado sin conexión, en espera de sincronizarse.
 * El [uuid] es el mismo que se envía al servidor: reenviarlo nunca duplica el cobro.
 */
@Entity(tableName = "pending_charges", indices = [Index("status"), Index("userId")])
data class PendingChargeEntity(
    @PrimaryKey val uuid: String,
    /** Usuario que cobró: sólo se envía con su propia sesión, para que la auditoría sea correcta. */
    val userId: Int,
    val merchantId: Int,
    val merchantFolio: String,
    val merchantName: String,
    /** ChargeRequest serializado en JSON. */
    val payloadJson: String,
    val total: String,
    val description: String,
    val createdAt: Long,
    val status: String = STATUS_PENDING,
    val serverReceiptId: Int? = null,
    val serverFolio: String? = null,
    val errorCode: String? = null,
    val errorMessage: String? = null,
    val attempts: Int = 0,
) {
    companion object {
        const val STATUS_PENDING = "pending"
        const val STATUS_SYNCED = "synced"
        const val STATUS_REJECTED = "rejected"
    }
}
