package mx.gob.padron.cobro.ui.settings

import android.Manifest
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
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
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import mx.gob.padron.cobro.AppContainer
import mx.gob.padron.cobro.print.BluetoothPrinter
import mx.gob.padron.cobro.ui.common.SectionCard
import mx.gob.padron.cobro.ui.common.containerViewModel

data class SettingsState(
    val server: String,
    val deviceName: String,
    val paperWidth: Int,
    val printerName: String?,
    val printerAddress: String?,
    val devices: List<BluetoothPrinter.Device> = emptyList(),
    val busy: Boolean = false,
    val loggedOut: Boolean = false,
)

class SettingsViewModel(private val container: AppContainer) : ViewModel() {
    private val session = container.session

    private val _state = MutableStateFlow(
        SettingsState(
            server = session.serverUrl,
            deviceName = session.deviceName,
            paperWidth = session.paperWidth,
            printerName = session.printerName,
            printerAddress = session.printerAddress,
        ),
    )
    val state = _state.asStateFlow()

    private val _messages = MutableSharedFlow<String>(extraBufferCapacity = 2)
    val messages = _messages.asSharedFlow()

    val pending = container.charges.pendingCount.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), 0)
    val cachedMerchants = container.merchants.cachedCount.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), 0)
    val user = session.user

    fun onServer(v: String) = _state.update { it.copy(server = v) }
    fun onDeviceName(v: String) = _state.update { it.copy(deviceName = v) }

    fun save() {
        session.serverUrl = _state.value.server
        session.deviceName = _state.value.deviceName
        _messages.tryEmit("Ajustes guardados.")
    }

    fun setPaper(width: Int) {
        session.paperWidth = width
        _state.update { it.copy(paperWidth = session.paperWidth) }
    }

    fun loadDevices() {
        if (!container.printer.isEnabled()) {
            _messages.tryEmit("Active el Bluetooth y vincule la impresora en los ajustes del teléfono.")
        }
        _state.update { it.copy(devices = container.printer.pairedDevices()) }
    }

    fun selectPrinter(device: BluetoothPrinter.Device) {
        session.printerAddress = device.address
        session.printerName = device.name
        _state.update { it.copy(printerAddress = device.address, printerName = device.name) }
    }

    fun printTest() {
        _state.update { it.copy(busy = true) }
        viewModelScope.launch {
            _messages.emit(container.ticketPrinter.printTest() ?: "Prueba enviada a la impresora.")
            _state.update { it.copy(busy = false) }
        }
    }

    fun syncNow() {
        container.scheduler.syncSoon()
        _messages.tryEmit("Sincronización programada.")
    }

    fun logout() {
        _state.update { it.copy(busy = true) }
        viewModelScope.launch {
            container.scheduler.cancelAll()
            container.auth.logout()
            _state.update { it.copy(busy = false, loggedOut = true) }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(container: AppContainer, onBack: () -> Unit, onLoggedOut: () -> Unit) {
    val vm = containerViewModel(container) { c, _ -> SettingsViewModel(c) }
    val state by vm.state.collectAsStateWithLifecycle()
    val pending by vm.pending.collectAsStateWithLifecycle()
    val cached by vm.cachedMerchants.collectAsStateWithLifecycle()
    val user by vm.user.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    var confirmLogout by remember { mutableStateOf(false) }

    val btPermission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) vm.loadDevices()
    }
    val withBluetooth: (() -> Unit) -> Unit = { action ->
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S && !container.printer.hasPermission()) {
            btPermission.launch(Manifest.permission.BLUETOOTH_CONNECT)
        } else {
            action()
        }
    }

    LaunchedEffect(Unit) { vm.messages.collect { snackbar.showSnackbar(it) } }
    LaunchedEffect(state.loggedOut) { if (state.loggedOut) onLoggedOut() }
    LaunchedEffect(Unit) { if (container.printer.hasPermission()) vm.loadDevices() }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Ajustes") },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Regresar") }
                },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            SectionCard("Impresora térmica") {
                Text(
                    state.printerName?.let { "Seleccionada: $it" } ?: "Ninguna seleccionada",
                    style = MaterialTheme.typography.bodyMedium,
                )
                Text(
                    "Vincule la impresora en Ajustes › Bluetooth del teléfono y selecciónela aquí.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(vertical = 4.dp),
                )
                state.devices.forEach { device ->
                    Row(
                        Modifier.fillMaxWidth().clickable { vm.selectPrinter(device) }.padding(vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        RadioButton(selected = state.printerAddress == device.address, onClick = { vm.selectPrinter(device) })
                        Column {
                            Text(device.name)
                            Text(device.address, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 8.dp)) {
                    OutlinedButton(onClick = { withBluetooth(vm::loadDevices) }) { Text("Buscar vinculadas") }
                    Button(onClick = { withBluetooth(vm::printTest) }, enabled = state.printerAddress != null && !state.busy) {
                        Text("Imprimir prueba")
                    }
                }
                Text("Ancho de papel", modifier = Modifier.padding(top = 12.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf(58, 80).forEach { w ->
                        FilterChip(selected = state.paperWidth == w, onClick = { vm.setPaper(w) }, label = { Text("$w mm") })
                    }
                }
            }

            SectionCard("Sincronización") {
                Text("Cobros pendientes de enviar: $pending")
                Text("Comerciantes guardados para uso sin conexión: $cached")
                OutlinedButton(onClick = vm::syncNow, modifier = Modifier.padding(top = 8.dp)) { Text("Sincronizar ahora") }
            }

            SectionCard("Conexión") {
                OutlinedTextField(
                    value = state.server,
                    onValueChange = vm::onServer,
                    label = { Text("Servidor") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(
                    value = state.deviceName,
                    onValueChange = vm::onDeviceName,
                    label = { Text("Nombre de este dispositivo") },
                    supportingText = { Text("Aparece en la auditoría de cada cobro.") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
                )
                Button(onClick = vm::save, modifier = Modifier.padding(top = 8.dp)) { Text("Guardar") }
            }

            SectionCard("Sesión") {
                Text(listOfNotNull(user?.name, user?.username, user?.roleLabel).joinToString(" · "))
                OutlinedButton(
                    onClick = { confirmLogout = true },
                    enabled = !state.busy,
                    modifier = Modifier.padding(top = 8.dp),
                ) { Text("Cerrar sesión") }
            }
        }
    }

    if (confirmLogout) {
        AlertDialog(
            onDismissRequest = { confirmLogout = false },
            title = { Text("Cerrar sesión") },
            text = {
                Text(
                    if (pending > 0) {
                        "Hay $pending cobros sin enviar. Se conservarán en el teléfono y se enviarán cuando vuelva a iniciar sesión con conexión."
                    } else {
                        "¿Desea cerrar la sesión en este dispositivo?"
                    },
                )
            },
            confirmButton = {
                TextButton(onClick = {
                    confirmLogout = false
                    vm.logout()
                }) { Text("Cerrar sesión") }
            },
            dismissButton = { TextButton(onClick = { confirmLogout = false }) { Text("Cancelar") } },
        )
    }
}
