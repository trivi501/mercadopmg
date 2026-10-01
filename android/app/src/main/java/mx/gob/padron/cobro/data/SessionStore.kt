package mx.gob.padron.cobro.data

import android.content.Context
import android.content.SharedPreferences
import android.os.Build
import androidx.core.content.edit
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import mx.gob.padron.cobro.BuildConfig
import mx.gob.padron.cobro.data.remote.AppJson
import mx.gob.padron.cobro.data.remote.UserDto

/**
 * Sesión y ajustes del dispositivo, cifrados con una llave del Android Keystore.
 */
class SessionStore(context: Context) {

    private val prefs: SharedPreferences = EncryptedSharedPreferences.create(
        context,
        "cobro_session",
        MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(),
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
    )

    private val _user = MutableStateFlow(readUser())
    val user: StateFlow<UserDto?> = _user.asStateFlow()

    val token: String? get() = prefs.getString(KEY_TOKEN, null)

    val isLoggedIn: Boolean get() = token != null

    var serverUrl: String
        get() = prefs.getString(KEY_SERVER, null) ?: BuildConfig.DEFAULT_SERVER_URL
        set(value) = prefs.edit { putString(KEY_SERVER, value.trim().trimEnd('/')) }

    var deviceName: String
        get() = prefs.getString(KEY_DEVICE, null) ?: "${Build.MANUFACTURER} ${Build.MODEL}".take(60)
        set(value) = prefs.edit { putString(KEY_DEVICE, value.trim().take(60)) }

    var printerAddress: String?
        get() = prefs.getString(KEY_PRINTER, null)
        set(value) = prefs.edit { putString(KEY_PRINTER, value) }

    var printerName: String?
        get() = prefs.getString(KEY_PRINTER_NAME, null)
        set(value) = prefs.edit { putString(KEY_PRINTER_NAME, value) }

    /** Ancho de papel de la impresora térmica: 58 u 80 mm. */
    var paperWidth: Int
        get() = prefs.getInt(KEY_PAPER, 58)
        set(value) = prefs.edit { putInt(KEY_PAPER, if (value == 80) 80 else 58) }

    var municipalityName: String
        get() = prefs.getString(KEY_MUNICIPALITY, null) ?: "MUNICIPIO"
        set(value) = prefs.edit { putString(KEY_MUNICIPALITY, value) }

    var ticketFooter: String
        get() = prefs.getString(KEY_FOOTER, null) ?: "Conserve este recibo como comprobante de pago."
        set(value) = prefs.edit { putString(KEY_FOOTER, value) }

    /** Marca de tiempo del servidor de la última descarga del padrón (sincronización incremental). */
    var merchantsSyncedAt: String?
        get() = prefs.getString(KEY_MERCHANTS_SYNC, null)
        set(value) = prefs.edit { putString(KEY_MERCHANTS_SYNC, value) }

    fun saveSession(token: String, user: UserDto) {
        prefs.edit {
            putString(KEY_TOKEN, token)
            putString(KEY_USER, AppJson.encodeToString(UserDto.serializer(), user))
        }
        _user.value = user
    }

    fun clearSession() {
        prefs.edit {
            remove(KEY_TOKEN)
            remove(KEY_USER)
        }
        _user.value = null
    }

    fun can(permission: String): Boolean = _user.value?.let {
        it.role == "admin" || permission in it.permissions
    } ?: false

    private fun readUser(): UserDto? = prefs.getString(KEY_USER, null)?.let {
        runCatching { AppJson.decodeFromString(UserDto.serializer(), it) }.getOrNull()
    }

    private companion object {
        const val KEY_TOKEN = "token"
        const val KEY_USER = "user"
        const val KEY_SERVER = "server_url"
        const val KEY_DEVICE = "device_name"
        const val KEY_PRINTER = "printer_address"
        const val KEY_PRINTER_NAME = "printer_name"
        const val KEY_PAPER = "paper_width"
        const val KEY_MUNICIPALITY = "municipality"
        const val KEY_FOOTER = "ticket_footer"
        const val KEY_MERCHANTS_SYNC = "merchants_synced_at"
    }
}
