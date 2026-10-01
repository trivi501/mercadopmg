package mx.gob.padron.cobro.sync

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import mx.gob.padron.cobro.CobroApp
import mx.gob.padron.cobro.data.remote.ApiResult

/**
 * Sincronización con el servidor:
 * 1. Envía la cola de cobros capturados sin conexión (POST /api/v1/sync).
 * 2. Actualiza catálogos (conceptos e importes vigentes).
 * 3. Descarga los cambios del padrón para consulta sin conexión.
 */
class SyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {

    override suspend fun doWork(): Result {
        val container = (applicationContext as CobroApp).container
        if (!container.session.isLoggedIn) return Result.success()

        // Primero los cobros: es lo más importante y no depende de lo demás.
        when (val pushed = container.charges.pushPending()) {
            is ApiResult.Offline -> return Result.retry()
            is ApiResult.Failure -> return if (pushed.status in 500..599 || pushed.status == 429) Result.retry() else Result.failure()
            is ApiResult.Success -> Unit
        }

        val serverTime = when (val catalogs = container.catalogs.refresh()) {
            is ApiResult.Success -> catalogs.value
            is ApiResult.Offline -> return Result.retry()
            is ApiResult.Failure -> null
        }

        if (container.merchants.syncDown(serverTime) is ApiResult.Offline) return Result.retry()

        return Result.success()
    }
}
