package mx.gob.padron.cobro.ui.home

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.LockOpen
import androidx.compose.material.icons.filled.Print
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
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
import mx.gob.padron.cobro.data.remote.CashCurrentDto
import mx.gob.padron.cobro.data.remote.DashboardDto
import mx.gob.padron.cobro.data.remote.errorMessage
import mx.gob.padron.cobro.ui.cash.OpenCashDialog
import mx.gob.padron.cobro.ui.common.containerViewModel
import mx.gob.padron.cobro.util.Money

data class HomeState(
    val dashboard: DashboardDto? = null,
    val offline: Boolean = false,
    val refreshing: Boolean = false,
    val cash: CashCurrentDto? = null,
    val openingCash: Boolean = false,
    val cashBusy: Boolean = false,
    val cashError: String? = null,
)

class HomeViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(HomeState())
    val state = _state.asStateFlow()

    val user = container.session.user
    val pendingCount = container.charges.pendingCount.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), 0)
    val syncing = container.scheduler.isSyncing.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), false)

    fun refresh() {
        _state.update { it.copy(refreshing = true) }
        viewModelScope.launch {
            when (val r = container.receipts.dashboard()) {
                is ApiResult.Success -> _state.update { it.copy(dashboard = r.value, offline = false, refreshing = false) }
                else -> _state.update { it.copy(offline = r is ApiResult.Offline, refreshing = false) }
            }
            (container.cash.current() as? ApiResult.Success)?.let { r -> _state.update { it.copy(cash = r.value) } }
        }
    }

    fun syncNow() = container.scheduler.syncSoon()

    /** Corte abierto: el del servidor o, sin conexión, el último conocido. */
    val cachedCashSessionId: Int? get() = container.session.cashSessionId
    val cachedCashFolio: String? get() = container.session.cashSessionFolio

    fun showOpenCash(show: Boolean) = _state.update { it.copy(openingCash = show, cashError = null) }

    fun openCash(amount: String, notes: String) {
        _state.update { it.copy(cashBusy = true, cashError = null) }
        viewModelScope.launch {
            when (val r = container.cash.open(amount, notes)) {
                is ApiResult.Success -> {
                    _state.update { it.copy(cashBusy = false, openingCash = false) }
                    refresh()
                }
                else -> _state.update { it.copy(cashBusy = false, cashError = r.errorMessage()) }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    container: AppContainer,
    onSearch: (scan: Boolean) -> Unit,
    onHistory: () -> Unit,
    onSettings: () -> Unit,
    onCash: (sessionId: Int) -> Unit,
) {
    val vm = containerViewModel(container) { c, _ -> HomeViewModel(c) }
    val state by vm.state.collectAsStateWithLifecycle()
    val user by vm.user.collectAsStateWithLifecycle()
    val pending by vm.pendingCount.collectAsStateWithLifecycle()
    val syncing by vm.syncing.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { vm.refresh() }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Cobro de Padrón")
                        Text(
                            listOfNotNull(user?.name, user?.roleLabel).joinToString(" · "),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                },
                actions = {
                    IconButton(onClick = onSettings) { Icon(Icons.Default.Settings, contentDescription = "Ajustes") }
                },
            )
        },
    ) { padding ->
        PullToRefreshBox(
            isRefreshing = state.refreshing,
            onRefresh = vm::refresh,
            modifier = Modifier.fillMaxSize().padding(padding),
        ) {
            Column(
                Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (pending > 0) {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.tertiaryContainer),
                        onClick = onHistory,
                    ) {
                        Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Sync, contentDescription = null)
                            Column(Modifier.weight(1f).padding(horizontal = 12.dp)) {
                                Text("$pending cobros por sincronizar", fontWeight = FontWeight.SemiBold)
                                Text(
                                    if (syncing) "Sincronizando…" else "Se enviarán al recuperar la conexión",
                                    style = MaterialTheme.typography.bodySmall,
                                )
                            }
                            OutlinedButton(onClick = vm::syncNow, enabled = !syncing) { Text("Enviar") }
                        }
                    }
                }

                val current = state.cash?.current
                val openId = current?.session?.id ?: vm.cachedCashSessionId
                CashCard(
                    folio = current?.session?.folio ?: vm.cachedCashFolio,
                    openedAt = current?.session?.openedAtLabel,
                    collected = current?.summary?.totalCollected,
                    isOpen = openId != null,
                    onOpen = { vm.showOpenCash(true) },
                    onClose = { openId?.let(onCash) },
                )
                state.cash?.lastClosed?.takeIf { openId == null }?.let { last ->
                    TextButton(onClick = { onCash(last.id) }) {
                        Icon(Icons.Default.Print, contentDescription = null)
                        Text("  Reimprimir último corte ${last.folio}")
                    }
                }

                Text("Mis cobros de hoy", style = MaterialTheme.typography.titleMedium)
                val d = state.dashboard
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Stat("Recaudado", d?.let { Money.format(it.revenueToday) } ?: "—", Modifier.weight(1f))
                    Stat("Cobros", d?.chargesToday?.toString() ?: "—", Modifier.weight(1f))
                }
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Stat("Boletos", d?.ticketsToday?.toString() ?: "—", Modifier.weight(1f))
                    Stat("Anualidades", d?.annualitiesPaidToday?.toString() ?: "—", Modifier.weight(1f))
                }
                if (state.offline) {
                    Text(
                        "Sin conexión: los indicadores se actualizarán al reconectar.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }

                // Sin corte abierto no se cobra: escanear y buscar quedan bloqueados.
                Button(onClick = { onSearch(true) }, enabled = openId != null, modifier = Modifier.fillMaxWidth().height(64.dp).padding(top = 8.dp)) {
                    Icon(Icons.Default.QrCodeScanner, contentDescription = null, modifier = Modifier.size(28.dp))
                    Text("  ESCANEAR QR", style = MaterialTheme.typography.titleMedium)
                }
                OutlinedButton(onClick = { onSearch(false) }, enabled = openId != null, modifier = Modifier.fillMaxWidth().height(56.dp)) {
                    Icon(Icons.Default.Search, contentDescription = null)
                    Text("  BUSCAR COMERCIANTE")
                }
                OutlinedButton(onClick = onHistory, modifier = Modifier.fillMaxWidth().height(56.dp)) {
                    Icon(Icons.Default.History, contentDescription = null)
                    Text("  HISTORIAL")
                }
            }
        }
    }

    if (state.openingCash) {
        OpenCashDialog(
            busy = state.cashBusy,
            error = state.cashError,
            onDismiss = { vm.showOpenCash(false) },
            onOpen = vm::openCash,
        )
    }
}

@Composable
private fun CashCard(
    folio: String?,
    openedAt: String?,
    collected: String?,
    isOpen: Boolean,
    onOpen: () -> Unit,
    onClose: () -> Unit,
) {
    Card(
        colors = CardDefaults.cardColors(
            containerColor = if (isOpen) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.errorContainer,
        ),
    ) {
        Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(if (isOpen) Icons.Default.LockOpen else Icons.Default.Lock, contentDescription = null)
            Column(Modifier.weight(1f).padding(horizontal = 12.dp)) {
                if (isOpen) {
                    Text("Corte ${folio ?: ""} abierto", fontWeight = FontWeight.SemiBold)
                    Text(
                        listOfNotNull(openedAt?.let { "Desde $it" }, collected?.let { "Cobrado ${Money.format(it)}" }).joinToString(" · "),
                        style = MaterialTheme.typography.bodySmall,
                    )
                } else {
                    Text("Sin corte abierto", fontWeight = FontWeight.SemiBold)
                    Text("Inicie el cobro para poder cobrar.", style = MaterialTheme.typography.bodySmall)
                }
            }
            if (isOpen) {
                OutlinedButton(onClick = onClose) { Text("CORTE FINAL") }
            } else {
                Button(onClick = onOpen) { Text("INICIAR COBRO") }
            }
        }
    }
}

@Composable
private fun Stat(label: String, value: String, modifier: Modifier = Modifier) {
    Card(modifier) {
        Column(Modifier.padding(16.dp)) {
            Text(label, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text(value, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
        }
    }
}
