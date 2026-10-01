package mx.gob.padron.cobro.data.remote

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.serialization.json.Json
import mx.gob.padron.cobro.BuildConfig
import mx.gob.padron.cobro.data.SessionStore
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit

val AppJson = Json {
    ignoreUnknownKeys = true
    explicitNulls = false
    encodeDefaults = true
}

/**
 * Cliente HTTP. La URL del servidor se toma de Ajustes en cada petición, de
 * modo que puede cambiarse sin reiniciar la app.
 */
class ApiClient(private val session: SessionStore) {

    private val _sessionExpired = MutableSharedFlow<Unit>(extraBufferCapacity = 1)

    /** Emite cuando el servidor rechaza el token (401): la UI regresa al login. */
    val sessionExpired: SharedFlow<Unit> = _sessionExpired.asSharedFlow()

    private val serverInterceptor = Interceptor { chain ->
        val original = chain.request()
        val server = session.serverUrl.trimEnd('/').toHttpUrlOrNull()
            ?: throw java.io.IOException("URL del servidor inválida: ${session.serverUrl}")

        val url = server.newBuilder()
            .addEncodedPathSegments(original.url.encodedPath.removePrefix("/"))
            .encodedQuery(original.url.encodedQuery)
            .build()

        val builder = original.newBuilder()
            .url(url)
            .header("Accept", "application/json")
            .header("X-Device-Name", session.deviceName)

        session.token?.let { builder.header("Authorization", "Bearer $it") }

        val response = chain.proceed(builder.build())
        if (response.code == 401 && !original.url.encodedPath.endsWith("/login")) {
            session.clearSession()
            _sessionExpired.tryEmit(Unit)
        }
        response
    }

    private val okHttp = OkHttpClient.Builder()
        .connectTimeout(10, TimeUnit.SECONDS)
        .readTimeout(20, TimeUnit.SECONDS)
        .writeTimeout(20, TimeUnit.SECONDS)
        .addInterceptor(serverInterceptor)
        .apply {
            if (BuildConfig.DEBUG) {
                addInterceptor(
                    HttpLoggingInterceptor().apply {
                        level = HttpLoggingInterceptor.Level.BASIC
                        redactHeader("Authorization")
                    },
                )
            }
        }
        .build()

    val api: ApiService = Retrofit.Builder()
        // Marcador: el interceptor sustituye el servidor real.
        .baseUrl("http://localhost/")
        .client(okHttp)
        .addConverterFactory(AppJson.asConverterFactory("application/json; charset=UTF-8".toMediaType()))
        .build()
        .create(ApiService::class.java)
}
