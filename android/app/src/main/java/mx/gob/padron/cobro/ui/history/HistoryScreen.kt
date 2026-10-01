package mx.gob.padron.cobro.ui.history

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
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
import mx.gob.padron.cobro.data.local.PendingChargeEntity
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ReceiptDto
import mx.gob.padron.cobro.data.remote.errorMessage
import mx.gob.padron.cobro.ui.common.StatusChip
import mx.gob.padron.cobro.ui.common.containerViewModel
import mx.gob.padron.cobro.util.Money
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

data class HistoryState(
    val receipts: List<ReceiptDto> = emptyList(),
    val loading: Boolean = false,
    val error: String? = null,
)

class HistoryViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(HistoryState())
    val state = _state.asStateFlow()

    val queue = container.charges.queue.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), emptyList())
    val syncing = container.scheduler.isSyncing.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), false)

    fun refresh() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            when (val r = container.receipts.myReceiptsToday()) {
                is ApiResult.Success -> _state.update { it.copy(loading = false, receipts = r.value) }
                else -> _state.update { it.copy(loading = false, error = r.errorMessage()) }
            }
        }
    }

    fun syncNow() = container.scheduler.syncSoon()
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HistoryScreen(container: AppContainer, onBack: () -> Unit, onReceipt: (Int) -> Unit) {
    val vm = containerViewModel(container) { c, _ -> HistoryViewModel(c) }
    val state by vm.state.collectAsStateWithLifecycle()
    val queue by vm.queue.collectAsStateWithLifecycle()
    val syncing by vm.syncing.collectAsStateWithLifecycle()
    var tab by rememberSaveable { mutableIntStateOf(0) }
    val pendingCount = queue.count { it.status == PendingChargeEntity.STATUS_PENDING }

    // Cuando termina una sincronización se recargan los recibos del servidor.
    LaunchedEffect(syncing) { if (!syncing) vm.refresh() }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Historial") },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Regresar") }
                },
                actions = {
                    IconButton(onClick = vm::syncNow, enabled = !syncing) { Icon(Icons.Default.Sync, contentDescription = "Sincronizar") }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            TabRow(selectedTabIndex = tab) {
                Tab(selected = tab == 0, onClick = { tab = 0 }, text = { Text("Mis cobros de hoy") })
                Tab(selected = tab == 1, onClick = { tab = 1 }, text = { Text("Sin conexión ($pendingCount)") })
            }
            if (syncing) LinearProgressIndicator(Modifier.fillMaxWidth())

            if (tab == 0) {
                PullToRefreshBox(isRefreshing = state.loading, onRefresh = vm::refresh, modifier = Modifier.fillMaxSize()) {
                    LazyColumn(Modifier.fillMaxSize()) {
                        state.error?.let { item { Text(it, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(16.dp)) } }
                        if (!state.loading && state.error == null && state.receipts.isEmpty()) {
                            item { Text("Aún no hay cobros hoy.", modifier = Modifier.padding(16.dp)) }
                        }
                        if (state.receipts.isNotEmpty()) {
                            item {
                                val total = Money.sum(state.receipts.filter { it.status != "cancelled" }.map { Money.parse(it.total) })
                                Text(
                                    "${state.receipts.size} recibos · ${Money.format(total)}",
                                    fontWeight = FontWeight.SemiBold,
                                    modifier = Modifier.padding(16.dp),
                                )
                            }
                        }
                        items(state.receipts, key = { it.id }) { r ->
                            ListItem(
                                overlineContent = { Text("${r.folio} · ${r.time}", fontFamily = FontFamily.Monospace) },
                                headlineContent = { Text(r.merchant?.taxpayerName.orEmpty()) },
                                supportingContent = { Text(r.concepts.orEmpty()) },
                                trailingContent = {
                                    Column(horizontalAlignment = Alignment.End) {
                                        Text(Money.format(r.total), fontWeight = FontWeight.SemiBold)
                                        StatusChip(r.status, r.statusLabel)
                                    }
                                },
                                modifier = Modifier.clickable { onReceipt(r.id) },
                            )
                            HorizontalDivider()
                        }
                    }
                }
            } else {
                LazyColumn(Modifier.fillMaxSize()) {
                    if (queue.isEmpty()) {
                        item { Text("No hay cobros capturados sin conexión.", modifier = Modifier.padding(16.dp)) }
                    }
                    items(queue, key = { it.uuid }) { p ->
                        val (status, label) = when (p.status) {
                            PendingChargeEntity.STATUS_SYNCED -> "synced" to "SINCRONIZADO"
                            PendingChargeEntity.STATUS_REJECTED -> "rejected" to "RECHAZADO"
                            else -> "pending" to "PENDIENTE"
                        }
                        ListItem(
                            overlineContent = {
                                Text(
                                    "${p.merchantFolio} · ${formatTime(p.createdAt)}" + (p.serverFolio?.let { " · $it" } ?: ""),
                                    fontFamily = FontFamily.Monospace,
                                )
                            },
                            headlineContent = { Text(p.merchantName) },
                            supportingContent = {
                                Column {
                                    Text(p.description)
                                    p.errorMessage?.let { Text(it, color = MaterialTheme.colorScheme.error) }
                                }
                            },
                            trailingContent = {
                                Column(horizontalAlignment = Alignment.End) {
                                    Text(Money.format(p.total), fontWeight = FontWeight.SemiBold)
                                    StatusChip(status, label)
                                }
                            },
                            modifier = Modifier.clickable(enabled = p.serverReceiptId != null) {
                                p.serverReceiptId?.let(onReceipt)
                            },
                        )
                        HorizontalDivider()
                    }
                }
            }
        }
    }
}

private fun formatTime(millis: Long): String = SimpleDateFormat("dd/MM HH:mm", Locale.ROOT).format(Date(millis))
