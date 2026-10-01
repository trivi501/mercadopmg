package mx.gob.padron.cobro

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import mx.gob.padron.cobro.ui.CobroNavHost
import mx.gob.padron.cobro.ui.theme.CobroTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val container = (application as CobroApp).container

        setContent {
            CobroTheme {
                CobroNavHost(container)
            }
        }
    }
}
