package mx.gob.padron.cobro.ui.receipt

import android.Manifest
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Print
import androidx.compose.material.icons.filled.Replay
import androidx.compose.material.icons.filled.Share
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.AssistChip
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import mx.gob.padron.cobro.AppContainer
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ReceiptPayload
import mx.gob.padron.cobro.data.remote.TicketDto
import mx.gob.padron.cobro.data.remote.errorMessage
import mx.gob.padron.cobro.ui.common.ErrorMessage
import mx.gob.padron.cobro.ui.common.Loading
import mx.gob.padron.cobro.ui.common.StatusChip
import mx.gob.padron.cobro.ui.common.TicketPreview
import mx.gob.padron.cobro.ui.common.containerViewModel
import mx.gob.padron.cobro.ui.theme.PaidGreen
import mx.gob.padron.cobro.util.Money
import java.io.File

data class ReceiptState(
    val loading: Boolean = true,
    val payload: ReceiptPayload? = null,
    /** Ticket que se muestra / imprime (el de la última reimpresión si la hubo). */
    val ticket: TicketDto? = null,
    val busy: Boolean = false,
    val error: String? = null,
    /** Último envío a la impresora falló: se permite reintentar el mismo ticket. */
    val failedTicket: TicketDto? = null,
    val reprintDialog: Boolean = false,
)

class ReceiptViewModel(private val container: AppContainer, private val receiptId: Int) : ViewModel() {
    private val _state = MutableStateFlow(ReceiptState())
    val state = _state.asStateFlow()

    private val _messages = MutableSharedFlow<String>(extraBufferCapacity = 3)
    val messages = _messages.asSharedFlow()

    private val _sharePdf = MutableSharedFlow<File>(extraBufferCapacity = 1)
    val sharePdf = _sharePdf.asSharedFlow()

    val printerConfigured: Boolean get() = container.ticketPrinter.isConfigured
    val canReprint: Boolean get() = container.session.can("receipts.reprint")

    init { load() }

    fun load() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            when (val r = container.receipts.get(receiptId)) {
                is ApiResult.Success -> _state.update { it.copy(loading = false, payload = r.value, ticket = r.value.ticket) }
                else -> _state.update { it.copy(loading = false, error = r.errorMessage()) }
            }
        }
    }

    /**
     * Impresión original: primero se registra en el servidor (sólo se permite una vez)
     * y después se envía el ticket a la impresora.
     */
    fun printOriginal() = runBusy {
        when (val r = container.receipts.markPrinted(receiptId)) {
            is ApiResult.Success -> {
                _state.update { it.copy(payload = r.value, ticket = r.value.ticket) }
                sendToPrinter(r.value.ticket)
            }
            else -> {
                _messages.emit(r.errorMessage().orEmpty())
                if (r is ApiResult.Failure && r.code == "already_printed") load()
            }
        }
    }

    fun openReprint() = _state.update { it.copy(reprintDialog = true) }
    fun closeReprint() = _state.update { it.copy(reprintDialog = false) }

    /** Reimpresión controlada: el servidor registra número, usuario y motivo. No genera cobro. */
    fun reprint(reason: String) = runBusy {
        _state.update { it.copy(reprintDialog = false) }
        when (val r = container.receipts.reprint(receiptId, reason)) {
            is ApiResult.Success -> {
                _state.update { it.copy(payload = r.value, ticket = r.value.ticket) }
                _messages.emit("Reimpresión No. ${r.value.ticket.reprint?.number} registrada.")
                sendToPrinter(r.value.ticket)
            }
            else -> _messages.emit(r.errorMessage().orEmpty())
        }
    }

    /** Reintenta el mismo ticket cuando la impresora falló (no es una nueva impresión). */
    fun retryPrint() = runBusy {
        _state.value.failedTicket?.let { sendToPrinter(it) }
    }

    fun sharePdf() = runBusy {
        val folio = _state.value.payload?.receipt?.folio ?: return@runBusy
        when (val r = container.receipts.downloadPdf(receiptId, folio)) {
            is ApiResult.Success -> _sharePdf.emit(r.value)
            else -> _messages.emit(r.errorMessage().orEmpty())
        }
    }

    private suspend fun sendToPrinter(ticket: TicketDto) {
        val error = container.ticketPrinter.print(ticket)
        if (error == null) {
            _state.update { it.copy(failedTicket = null) }
            _messages.emit("Ticket impreso.")
        } else {
            _state.update { it.copy(failedTicket = ticket) }
            _messages.emit(error)
        }
    }

    private fun runBusy(block: suspend () -> Unit) {
        if (_state.value.busy) return
        _state.update { it.copy(busy = true) }
        viewModelScope.launch {
            try {
                block()
            } finally {
                _state.update { it.copy(busy = false) }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
fun ReceiptScreen(
    container: AppContainer,
    receiptId: Int,
    justCharged: Boolean,
    onBack: () -> Unit,
    onHome: () -> Unit,
) {
    val vm = containerViewModel(container, key = "receipt-$receiptId") { c, _ -> ReceiptViewModel(c, receiptId) }
    val state by vm.state.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val context = LocalContext.current
    var pendingAction by remember { mutableStateOf<(() -> Unit)?>(null) }

    val bluetoothPermission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) pendingAction?.invoke()
        pendingAction = null
    }
    // Asegura el permiso de Bluetooth (Android 12+) antes de imprimir.
    val withBluetooth: (() -> Unit) -> Unit = { action ->
        if (container.printer.hasPermission() || Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
            action()
        } else {
            pendingAction = action
            bluetoothPermission.launch(Manifest.permission.BLUETOOTH_CONNECT)
        }
    }

    LaunchedEffect(Unit) { vm.messages.collect { if (it.isNotBlank()) snackbar.showSnackbar(it) } }
    LaunchedEffect(Unit) { vm.sharePdf.collect { sharePdf(context, it) } }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(state.payload?.receipt?.folio ?: "Recibo", fontFamily = FontFamily.Monospace) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Regresar") }
                },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        when {
            state.loading && state.payload == null -> Loading(Modifier.padding(padding))
            state.payload == null -> ErrorMessage(state.error ?: "No se pudo cargar el recibo.", vm::load, Modifier.padding(padding))
            else -> {
                val receipt = state.payload!!.receipt
                val cancelled = receipt.status == "cancelled"
                Column(
                    Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    if (justCharged) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CheckCircle, contentDescription = null, tint = PaidGreen)
                            Text(
                                "  Cobro registrado · ${Money.format(receipt.total)}",
                                style = MaterialTheme.typography.titleMedium,
                            )
                        }
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        StatusChip(receipt.status, receipt.statusLabel)
                        Text(
                            if (receipt.printedAt != null) "Impreso · ${receipt.reprintCount ?: 0} reimpresiones" else "Sin imprimir",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }

                    if (!vm.printerConfigured) {
                        Text(
                            "No hay impresora configurada. Selecciónela en Ajustes.",
                            color = MaterialTheme.colorScheme.error,
                            style = MaterialTheme.typography.bodySmall,
                        )
                    }

                    if (!cancelled && receipt.printedAt == null) {
                        Button(
                            onClick = { withBluetooth(vm::printOriginal) },
                            enabled = !state.busy,
                            modifier = Modifier.fillMaxWidth().height(56.dp),
                        ) {
                            Icon(Icons.Default.Print, contentDescription = null)
                            Text("  IMPRIMIR TICKET")
                        }
                    }

                    if (state.failedTicket != null) {
                        Button(
                            onClick = { withBluetooth(vm::retryPrint) },
                            enabled = !state.busy,
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Icon(Icons.Default.Replay, contentDescription = null)
                            Text("  REINTENTAR IMPRESIÓN")
                        }
                    }

                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        if (!cancelled && receipt.printedAt != null && vm.canReprint) {
                            AssistChip(
                                onClick = vm::openReprint,
                                enabled = !state.busy,
                                label = { Text("Reimprimir") },
                                leadingIcon = { Icon(Icons.Default.Print, contentDescription = null) },
                            )
                        }
                        AssistChip(
                            onClick = vm::sharePdf,
                            enabled = !state.busy,
                            label = { Text("Compartir PDF") },
                            leadingIcon = { Icon(Icons.Default.Share, contentDescription = null) },
                        )
                    }

                    state.ticket?.let { TicketPreview(it.lines) }

                    if (justCharged) {
                        OutlinedButton(onClick = onHome, modifier = Modifier.fillMaxWidth()) { Text("TERMINAR") }
                    }
                }
            }
        }
    }

    if (state.reprintDialog) {
        ReprintDialog(onDismiss = vm::closeReprint, onConfirm = { reason -> withBluetooth { vm.reprint(reason) } })
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ReprintDialog(onDismiss: () -> Unit, onConfirm: (String) -> Unit) {
    var reason by rememberSaveable { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Reimprimir ticket") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("La reimpresión no genera un nuevo cobro. Queda registrada con su número, usuario y motivo, y el ticket dirá REIMPRESIÓN.")
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("Ticket dañado", "Ticket extraviado", "Falla de impresora").forEach {
                        AssistChip(onClick = { reason = it }, label = { Text(it) })
                    }
                }
                OutlinedTextField(
                    value = reason,
                    onValueChange = { reason = it.take(255) },
                    label = { Text("Motivo") },
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
        confirmButton = {
            Button(onClick = { onConfirm(reason) }, enabled = reason.trim().length >= 4) { Text("REIMPRIMIR") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancelar") } },
    )
}

private fun sharePdf(context: Context, file: File) {
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.files", file)
    val intent = Intent(Intent.ACTION_SEND).apply {
        type = "application/pdf"
        putExtra(Intent.EXTRA_STREAM, uri)
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }
    context.startActivity(Intent.createChooser(intent, "Compartir recibo"))
}
