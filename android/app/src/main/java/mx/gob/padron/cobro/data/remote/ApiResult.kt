package mx.gob.padron.cobro.data.remote

import kotlinx.serialization.json.Json
import retrofit2.HttpException
import java.io.IOException

/**
 * Resultado de una llamada a la API con errores ya traducidos al formato
 * estándar del servidor: {message, code}.
 */
sealed interface ApiResult<out T> {
    data class Success<T>(val value: T) : ApiResult<T>

    /** El servidor respondió con error (4xx/5xx). [code] es estable, p. ej. "annuality_already_paid". */
    data class Failure(val status: Int, val code: String?, val message: String) : ApiResult<Nothing>

    /** Sin conexión o tiempo de espera agotado: la operación puede encolarse. */
    data class Offline(val cause: IOException) : ApiResult<Nothing>
}

private val errorJson = Json { ignoreUnknownKeys = true }

suspend fun <T> apiCall(block: suspend () -> T): ApiResult<T> = try {
    ApiResult.Success(block())
} catch (e: HttpException) {
    val body = runCatching {
        e.response()?.errorBody()?.string()?.let { errorJson.decodeFromString(ApiErrorBody.serializer(), it) }
    }.getOrNull()
    ApiResult.Failure(
        status = e.code(),
        code = body?.code,
        message = body?.message?.takeIf { it.isNotBlank() } ?: defaultMessage(e.code()),
    )
} catch (e: IOException) {
    ApiResult.Offline(e)
}

private fun defaultMessage(status: Int): String = when (status) {
    401 -> "Su sesión expiró. Inicie sesión de nuevo."
    403 -> "No tiene permiso para realizar esta acción."
    404 -> "No se encontró el registro."
    429 -> "Demasiadas solicitudes. Espere un momento."
    in 500..599 -> "Error del servidor. Intente más tarde."
    else -> "Error inesperado ($status)."
}

fun ApiResult<*>.errorMessage(): String? = when (this) {
    is ApiResult.Failure -> message
    is ApiResult.Offline -> "Sin conexión con el servidor."
    is ApiResult.Success -> null
}

inline fun <T, R> ApiResult<T>.map(transform: (T) -> R): ApiResult<R> = when (this) {
    is ApiResult.Success -> ApiResult.Success(transform(value))
    is ApiResult.Failure -> this
    is ApiResult.Offline -> this
}
