package mx.gob.padron.cobro.ui.cash

import android.Manifest
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Print
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
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
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import mx.gob.padron.cobro.AppContainer
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.CashPayload
import mx.gob.padron.cobro.data.remote.errorMessage
import mx.gob.padron.cobro.ui.common.ErrorMessage
import mx.gob.padron.cobro.ui.common.Loading
import mx.gob.padron.cobro.ui.common.SectionCard
import mx.gob.padron.cobro.ui.common.TicketPreview
import mx.gob.padron.cobro.ui.common.containerViewModel
import mx.gob.padron.cobro.ui.theme.PaidGreen
import mx.gob.padron.cobro.util.Money
import java.math.BigDecimal

/**
 * Inicio de cobro: fondo de caja con el que empieza el recaudador.
 */
@Composable
fun OpenCashDialog(busy: Boolean, error: String?, onDismiss: () -> Unit, onOpen: (amount: String, notes: String) -> Unit) {
    var amount by rememberSaveable { mutableStateOf("0") }
    var notes by rememberSaveable { mutableStateOf("") }
    val valid = amount.toBigDecimalOrNull()?.let { it >= BigDecimal.ZERO } == true

    AlertDialog(
        onDismissRequest = { if (!busy) onDismiss() },
        title = { Text("Iniciar cobro") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("Se abre su corte de caja. Los cobros que registre quedarán en este corte hasta el corte final.")
                OutlinedTextField(
                    value = amount,
                    onValueChange = { amount = it.filter { c -> c.isDigit() || c == '.' }.take(10) },
                    label = { Text("Fondo inicial de caja") },
                    prefix = { Text("$") },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    supportingText = { Text("Efectivo con el que empieza (cambio). 0 si no lleva.") },
                    modifier = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(
                    value = notes,
                    onValueChange = { notes = it.take(500) },
                    label = { Text("Notas (opcional)") },
                    placeholder = { Text("Tianguis, ruta o turno") },
                    modifier = Modifier.fillMaxWidth(),
                )
                error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
            }
        },
        confirmButton = {
            Button(onClick = { onOpen(Money.plain(BigDecimal(amount)), notes) }, enabled = valid && !busy) {
                if (busy) CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.height(18.dp)) else Text("INICIAR")
            }
        },
        dismissButton = { TextButton(onClick = onDismiss, enabled = !busy) { Text("Cancelar") } },
    )
}

data class CashState(
    val loading: Boolean = true,
    val payload: CashPayload? = null,
    val error: String? = null,
    val counted: String = "",
    val notes: String = "",
    val confirming: Boolean = false,
    val closing: Boolean = false,
    val printing: Boolean = false,
)

class CashViewModel(private val container: AppContainer, private val sessionId: Int) : ViewModel() {
    private val _state = MutableStateFlow(CashState())
    val state = _state.asStateFlow()
    val pendingCount = container.charges.pendingCount.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), 0)

    init { load() }

    fun load() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            when (val r = container.cash.get(sessionId)) {
                is ApiResult.Success -> _state.update { it.copy(loading = false, payload = r.value) }
                else -> _state.update { it.copy(loading = false, error = r.errorMessage()) }
            }
        }
    }

    fun setCounted(v: String) = _state.update { it.copy(counted = v.filter { c -> c.isDigit() || c == '.' }.take(12), error = null) }
    fun setNotes(v: String) = _state.update { it.copy(notes = v.take(1000)) }
    fun askConfirm() = _state.update { it.copy(confirming = it.counted.toBigDecimalOrNull() != null) }
    fun dismissConfirm() = _state.update { it.copy(confirming = false) }

    fun close() {
        val s = _state.value
        val counted = s.counted.toBigDecimalOrNull() ?: return
        _state.update { it.copy(confirming = false, closing = true, error = null) }
        viewModelScope.launch {
            when (val r = container.cash.close(sessionId, Money.plain(counted), s.notes)) {
                is ApiResult.Success -> _state.update { it.copy(closing = false, payload = r.value) }
                else -> _state.update { it.copy(closing = false, error = r.errorMessage()) }
            }
        }
    }

    suspend fun print(): String? {
        val lines = _state.value.payload?.lines ?: return "Sin reporte."
        _state.update { it.copy(printing = true) }
        return container.ticketPrinter.printLines(lines).also { _state.update { it.copy(printing = false) } }
    }
}

/**
 * Corte final con arqueo y reporte imprimible. Con el corte ya cerrado sólo
 * muestra e imprime el reporte.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CashScreen(container: AppContainer, sessionId: Int, onBack: () -> Unit, onHome: () -> Unit) {
    val vm = containerViewModel(container, key = "cash-$sessionId") { c, _ -> CashViewModel(c, sessionId) }
    val state by vm.state.collectAsStateWithLifecycle()
    val pending by vm.pendingCount.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()

    val print: () -> Unit = { scope.launch { snackbar.showSnackbar(vm.print() ?: "Reporte impreso.") } }
    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { if (it) print() }
    val withBluetooth: () -> Unit = {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S && !container.printer.hasPermission()) {
            permission.launch(Manifest.permission.BLUETOOTH_CONNECT)
        } else {
            print()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(state.payload?.session?.let { if (it.isOpen) "Corte final ${it.folio}" else "Corte ${it.folio}" } ?: "Corte de caja") },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Regresar") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        val payload = state.payload
        when {
            state.loading -> Loading(Modifier.padding(padding))
            payload == null -> ErrorMessage(state.error ?: "No se pudo cargar el corte.", onRetry = vm::load, modifier = Modifier.padding(padding))
            else -> Column(
                Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                val session = payload.session
                val summary = payload.summary

                if (session.isOpen) {
                    SectionCard("Arqueo de efectivo") {
                        Amount("Total cobrado (${summary.receipts} recibos)", summary.totalCollected)
                        Amount("Fondo inicial", summary.openingAmount)
                        Amount("Cobros en efectivo", summary.cashCollected)
                        Amount("Efectivo esperado", summary.expectedCash, strong = true)
                    }

                    OutlinedTextField(
                        value = state.counted,
                        onValueChange = vm::setCounted,
                        label = { Text("Efectivo contado") },
                        prefix = { Text("$") },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        modifier = Modifier.fillMaxWidth(),
                    )
                    state.counted.toBigDecimalOrNull()?.let { counted ->
                        Difference(counted - Money.parse(summary.expectedCash))
                    }
                    OutlinedTextField(
                        value = state.notes,
                        onValueChange = vm::setNotes,
                        label = { Text("Notas del corte (opcional)") },
                        placeholder = { Text("Explique sobrantes o faltantes") },
                        modifier = Modifier.fillMaxWidth(),
                    )

                    if (pending > 0) {
                        Text(
                            "Tiene $pending cobros sin sincronizar. Envíelos desde el inicio antes de hacer el corte final.",
                            color = MaterialTheme.colorScheme.error,
                        )
                    }
                    state.error?.let { Text(it, color = MaterialTheme.colorScheme.error) }

                    Button(
                        onClick = vm::askConfirm,
                        enabled = state.counted.toBigDecimalOrNull() != null && pending == 0 && !state.closing,
                        colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error),
                        modifier = Modifier.fillMaxWidth().height(56.dp),
                    ) {
                        if (state.closing) {
                            CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.height(20.dp))
                        } else {
                            Icon(Icons.Default.Lock, contentDescription = null)
                            Text("  CERRAR CORTE")
                        }
                    }
                    OutlinedButton(onClick = withBluetooth, enabled = !state.printing, modifier = Modifier.fillMaxWidth()) {
                        Icon(Icons.Default.Print, contentDescription = null)
                        Text("  IMPRIMIR CORTE PARCIAL")
                    }
                } else {
                    Text(
                        "Corte cerrado ${session.closedAtLabel ?: ""}",
                        style = MaterialTheme.typography.titleMedium,
                        color = PaidGreen,
                    )
                    session.difference?.let { Difference(Money.parse(it)) }
                    Button(
                        onClick = withBluetooth,
                        enabled = !state.printing,
                        modifier = Modifier.fillMaxWidth().height(56.dp),
                    ) {
                        Icon(Icons.Default.Print, contentDescription = null)
                        Text("  IMPRIMIR REPORTE")
                    }
                    OutlinedButton(onClick = onHome, modifier = Modifier.fillMaxWidth()) { Text("TERMINAR") }
                }

                TicketPreview(payload.lines)
            }
        }
    }

    if (state.confirming) {
        val expected = state.payload?.summary?.expectedCash
        AlertDialog(
            onDismissRequest = vm::dismissConfirm,
            title = { Text("¿Cerrar el corte?") },
            text = {
                Text(
                    "Efectivo contado: ${Money.format(state.counted)}\nEsperado: ${Money.format(expected)}\n\n" +
                        "Después de cerrar ya no podrá cobrar en este corte; para seguir cobrando inicie uno nuevo.",
                )
            },
            confirmButton = { Button(onClick = vm::close) { Text("CERRAR CORTE") } },
            dismissButton = { TextButton(onClick = vm::dismissConfirm) { Text("Cancelar") } },
        )
    }

    LaunchedEffect(state.payload?.session?.isOpen) {
        if (state.payload?.session?.isOpen == false) snackbar.showSnackbar("Corte cerrado. Imprima el reporte y entréguelo con el efectivo.")
    }
}

@Composable
private fun Amount(label: String, value: String, strong: Boolean = false) {
    Row(Modifier.fillMaxWidth().padding(vertical = 2.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(
            label,
            modifier = Modifier.weight(1f),
            color = if (strong) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
            fontWeight = if (strong) FontWeight.SemiBold else FontWeight.Normal,
        )
        Text(Money.format(value), fontWeight = if (strong) FontWeight.SemiBold else FontWeight.Normal)
    }
}

@Composable
private fun Difference(diff: BigDecimal) {
    val (label, color) = when {
        diff > BigDecimal.ZERO -> "Sobrante: ${Money.format(diff)}" to PaidGreen
        diff < BigDecimal.ZERO -> "Faltante: ${Money.format(diff.abs())}" to MaterialTheme.colorScheme.error
        else -> "El efectivo cuadra" to PaidGreen
    }
    Text(label, color = color, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.titleMedium)
}
