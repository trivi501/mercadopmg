package mx.gob.padron.cobro.ui.receipt

import android.Manifest
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.Print
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import mx.gob.padron.cobro.AppContainer
import mx.gob.padron.cobro.ui.common.ErrorMessage
import mx.gob.padron.cobro.ui.common.TicketPreview

/**
 * Comprobante provisional de un cobro capturado sin conexión. El recibo oficial
 * (con folio REC-) se emite al sincronizar y puede imprimirse desde el Historial.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProvisionalTicketScreen(container: AppContainer, onHome: () -> Unit) {
    val ticket = remember { container.provisionalTicket }
    val snackbar = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    var printing by remember { mutableStateOf(false) }

    val print: () -> Unit = {
        if (ticket != null && !printing) {
            printing = true
            scope.launch {
                val error = container.ticketPrinter.print(ticket)
                printing = false
                snackbar.showSnackbar(error ?: "Comprobante impreso.")
            }
        }
    }
    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { if (it) print() }

    Scaffold(
        topBar = { TopAppBar(title = { Text("Cobro guardado") }) },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        if (ticket == null) {
            ErrorMessage("No hay comprobante pendiente.", onRetry = null, modifier = Modifier.padding(padding))
            return@Scaffold
        }
        Column(
            Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(Icons.Default.CloudOff, contentDescription = null, tint = MaterialTheme.colorScheme.tertiary)
            Text(
                "Sin conexión. El cobro quedó guardado en el teléfono y se enviará automáticamente. " +
                    "El folio oficial se asignará al sincronizar; el recibo oficial podrá imprimirse desde Historial.",
                style = MaterialTheme.typography.bodyMedium,
            )
            Button(
                onClick = {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S && !container.printer.hasPermission()) {
                        permission.launch(Manifest.permission.BLUETOOTH_CONNECT)
                    } else {
                        print()
                    }
                },
                enabled = !printing,
                modifier = Modifier.fillMaxWidth().height(56.dp),
            ) {
                Icon(Icons.Default.Print, contentDescription = null)
                Text("  IMPRIMIR COMPROBANTE")
            }
            TicketPreview(ticket.lines)
            OutlinedButton(onClick = onHome, modifier = Modifier.fillMaxWidth()) { Text("TERMINAR") }
        }
    }
}
