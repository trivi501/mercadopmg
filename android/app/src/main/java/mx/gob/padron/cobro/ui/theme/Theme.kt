package mx.gob.padron.cobro.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val Blue = Color(0xFF1F5FAE)

private val LightColors = lightColorScheme(
    primary = Blue,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFD6E3FF),
    onPrimaryContainer = Color(0xFF001B3E),
)

private val DarkColors = darkColorScheme(
    primary = Color(0xFFA9C7FF),
    onPrimary = Color(0xFF003063),
    primaryContainer = Color(0xFF00468C),
    onPrimaryContainer = Color(0xFFD6E3FF),
)

/** Verde/ámbar para estados; siempre acompañados de texto. */
val PaidGreen = Color(0xFF1B7F3B)
val PendingAmber = Color(0xFFB26A00)

@Composable
fun CobroTheme(darkTheme: Boolean = isSystemInDarkTheme(), content: @Composable () -> Unit) {
    // Colores institucionales fijos (sin color dinámico del sistema).
    MaterialTheme(colorScheme = if (darkTheme) DarkColors else LightColors, content = content)
}
