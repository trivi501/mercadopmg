package mx.gob.padron.cobro.data.repository

import mx.gob.padron.cobro.data.SessionStore
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ApiService
import mx.gob.padron.cobro.data.remote.LoginRequest
import mx.gob.padron.cobro.data.remote.UserDto
import mx.gob.padron.cobro.data.remote.apiCall

class AuthRepository(
    private val api: ApiService,
    private val session: SessionStore,
) {
    val user = session.user

    suspend fun login(username: String, password: String): ApiResult<UserDto> {
        val result = apiCall { api.login(LoginRequest(username.trim(), password, session.deviceName)) }

        return when (result) {
            is ApiResult.Success -> {
                val data = result.value.data
                if (!canCharge(data.user)) {
                    // El token se crea en el servidor; se revoca de inmediato.
                    session.saveSession(data.token, data.user)
                    apiCall { api.logout() }
                    session.clearSession()
                    ApiResult.Failure(403, "forbidden", "Su rol (${data.user.roleLabel ?: "sin rol"}) no tiene permiso para cobrar.")
                } else {
                    session.saveSession(data.token, data.user)
                    ApiResult.Success(data.user)
                }
            }
            is ApiResult.Failure -> result
            is ApiResult.Offline -> result
        }
    }

    /** Cierra la sesión en el servidor (revoca el token) y localmente. */
    suspend fun logout() {
        apiCall { api.logout() }
        session.clearSession()
    }

    private fun canCharge(user: UserDto): Boolean =
        user.role == "admin" || "payments.create" in user.permissions
}
