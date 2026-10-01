package mx.gob.padron.cobro.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Transaction
import androidx.room.Upsert
import kotlinx.coroutines.flow.Flow

@Dao
interface MerchantDao {
    @Upsert
    suspend fun upsertAll(merchants: List<MerchantEntity>)

    @Query("SELECT * FROM merchants WHERE id = :id")
    suspend fun find(id: Int): MerchantEntity?

    @Query("SELECT * FROM merchants WHERE folio = :folio")
    suspend fun findByFolio(folio: String): MerchantEntity?

    @Query("SELECT * FROM merchants WHERE searchText LIKE '%' || :term || '%' ORDER BY folio LIMIT 30")
    suspend fun search(term: String): List<MerchantEntity>

    @Query("SELECT COUNT(*) FROM merchants")
    fun count(): Flow<Int>
}

@Dao
abstract class ConceptDao {
    @Query("DELETE FROM concepts")
    protected abstract suspend fun clear()

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    protected abstract suspend fun insertAll(concepts: List<ConceptEntity>)

    /** Sustituye el catálogo completo en una sola transacción. */
    @Transaction
    open suspend fun replaceAll(concepts: List<ConceptEntity>) {
        clear()
        insertAll(concepts)
    }

    @Query("SELECT * FROM concepts ORDER BY code")
    abstract fun observeAll(): Flow<List<ConceptEntity>>

    @Query("SELECT * FROM concepts ORDER BY code")
    abstract suspend fun all(): List<ConceptEntity>
}

@Dao
interface PendingChargeDao {
    @Insert(onConflict = OnConflictStrategy.IGNORE)
    suspend fun insert(charge: PendingChargeEntity)

    @Query("SELECT * FROM pending_charges WHERE status = 'pending' AND userId = :userId ORDER BY createdAt LIMIT :limit")
    suspend fun nextBatch(userId: Int, limit: Int): List<PendingChargeEntity>

    @Query("SELECT * FROM pending_charges WHERE userId = :userId ORDER BY createdAt DESC LIMIT 200")
    fun observeForUser(userId: Int): Flow<List<PendingChargeEntity>>

    @Query("SELECT COUNT(*) FROM pending_charges WHERE status = 'pending' AND userId = :userId")
    fun observePendingCount(userId: Int): Flow<Int>

    @Query("SELECT * FROM pending_charges WHERE uuid = :uuid")
    suspend fun find(uuid: String): PendingChargeEntity?

    @Query("UPDATE pending_charges SET status = 'synced', serverReceiptId = :receiptId, serverFolio = :folio, errorCode = NULL, errorMessage = NULL WHERE uuid = :uuid")
    suspend fun markSynced(uuid: String, receiptId: Int?, folio: String?)

    @Query("UPDATE pending_charges SET status = 'rejected', errorCode = :code, errorMessage = :message WHERE uuid = :uuid")
    suspend fun markRejected(uuid: String, code: String?, message: String?)

    @Query("UPDATE pending_charges SET attempts = attempts + 1 WHERE uuid IN (:uuids)")
    suspend fun incrementAttempts(uuids: List<String>)

    /** Limpia los sincronizados con más de [olderThan] ms (los rechazados se conservan para revisión). */
    @Query("DELETE FROM pending_charges WHERE status = 'synced' AND createdAt < :olderThan")
    suspend fun purgeSynced(olderThan: Long)
}
