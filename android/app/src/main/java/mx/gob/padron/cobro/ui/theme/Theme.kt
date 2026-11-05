package mx.gob.padron.cobro.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

// Colores del logotipo del Ayuntamiento de Guadalupe 2024-2027.
private val Guinda = Color(0xFF783548)
private val Arena = Color(0xFFC69776)

private val LightColors = lightColorScheme(
    primary = Guinda,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFFFD9E0),
    onPrimaryContainer = Color(0xFF3B0716),
    secondary = Arena,
    onSecondary = Color(0xFF2E1500),
)

private val DarkColors = darkColorScheme(
    primary = Color(0xFFFFB1C3),
    onPrimary = Color(0xFF561D30),
    primaryContainer = Guinda,
    onPrimaryContainer = Color(0xFFFFD9E0),
    secondary = Arena,
    onSecondary = Color(0xFF2E1500),
)

/** Verde/ámbar para estados; siempre acompañados de texto. */
val PaidGreen = Color(0xFF1B7F3B)
val PendingAmber = Color(0xFFB26A00)

@Composable
fun CobroTheme(darkTheme: Boolean = isSystemInDarkTheme(), content: @Composable () -> Unit) {
    // Colores institucionales fijos (sin color dinámico del sistema).
    MaterialTheme(colorScheme = if (darkTheme) DarkColors else LightColors, content = content)
}
