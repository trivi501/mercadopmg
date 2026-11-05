package mx.gob.padron.cobro

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import mx.gob.padron.cobro.ui.CobroNavHost
import mx.gob.padron.cobro.ui.theme.CobroTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        hideSystemBars()

        val container = (application as CobroApp).container

        setContent {
            CobroTheme {
                CobroNavHost(container)
            }
        }
    }

    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        // Diálogos del sistema (permisos, Bluetooth) vuelven a mostrar las barras.
        if (hasFocus) hideSystemBars()
    }

    /**
     * Pantalla completa: sin barra de estado ni botones de navegación del sistema.
     * Si el usuario desliza desde una orilla aparecen un momento y se ocultan solas.
     */
    private fun hideSystemBars() {
        WindowCompat.getInsetsController(window, window.decorView).apply {
            systemBarsBehavior = WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
            hide(WindowInsetsCompat.Type.systemBars())
        }
    }
}
