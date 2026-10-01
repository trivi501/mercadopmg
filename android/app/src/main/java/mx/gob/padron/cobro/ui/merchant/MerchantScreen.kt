package mx.gob.padron.cobro.ui.merchant

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import mx.gob.padron.cobro.AppContainer
import mx.gob.padron.cobro.data.local.MerchantEntity
import mx.gob.padron.cobro.data.remote.AccountDto
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ReceiptDto
import mx.gob.padron.cobro.data.remote.errorMessage
import mx.gob.padron.cobro.ui.common.ErrorMessage
import mx.gob.padron.cobro.ui.common.InfoRow
import mx.gob.padron.cobro.ui.common.Loading
import mx.gob.padron.cobro.ui.common.OfflineBanner
import mx.gob.padron.cobro.ui.common.SectionCard
import mx.gob.padron.cobro.ui.common.StatusChip
import mx.gob.padron.cobro.ui.common.containerViewModel
import mx.gob.padron.cobro.util.Money

data class MerchantState(
    val loading: Boolean = true,
    val merchant: MerchantEntity? = null,
    val account: AccountDto? = null,
    val receipts: List<ReceiptDto> = emptyList(),
    val offline: Boolean = false,
    val error: String? = null,
)

class MerchantViewModel(private val container: AppContainer, private val merchantId: Int) : ViewModel() {
    private val _state = MutableStateFlow(MerchantState())
    val state = _state.asStateFlow()

    val canCharge: Boolean get() = container.session.can("payments.create")

    init { load() }

    fun load() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            when (val m = container.merchants.get(merchantId)) {
                is ApiResult.Success -> {
                    val account = container.merchants.account(merchantId)
                    val receipts = container.merchants.receipts(merchantId)
                    _state.update {
                        it.copy(
                            loading = false,
                            merchant = m.value,
                            account = (account as? ApiResult.Success)?.value,
                            receipts = (receipts as? ApiResult.Success)?.value.orEmpty().take(10),
                            offline = account is ApiResult.Offline,
                        )
                    }
                }
                else -> _state.update { it.copy(loading = false, error = m.errorMessage()) }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MerchantScreen(
    container: AppContainer,
    merchantId: Int,
    onBack: () -> Unit,
    onCharge: () -> Unit,
    onReceipt: (Int) -> Unit,
) {
    val vm = containerViewModel(container, key = "merchant-$merchantId") { c, _ -> MerchantViewModel(c, merchantId) }
    val state by vm.state.collectAsStateWithLifecycle()

    // Al regresar de un cobro se actualiza el estado de cuenta.
    LaunchedEffect(Unit) { if (state.merchant != null) vm.load() }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(state.merchant?.folio ?: "Comerciante", fontFamily = FontFamily.Monospace) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Regresar") }
                },
            )
        },
        bottomBar = {
            val m = state.merchant
            if (m != null && vm.canCharge) {
                Column(Modifier.padding(16.dp)) {
                    Button(
                        onClick = onCharge,
                        enabled = m.status == "active",
                        modifier = Modifier.fillMaxWidth().height(56.dp),
                    ) {
                        Icon(Icons.Default.Payments, contentDescription = null)
                        Text(if (m.status == "active") "  COBRAR" else "  COMERCIANTE ${m.statusLabel.uppercase()}")
                    }
                }
            }
        },
    ) { padding ->
        when {
            state.loading && state.merchant == null -> Loading(Modifier.padding(padding))
            state.merchant == null -> ErrorMessage(state.error ?: "No se encontró el comerciante.", vm::load, Modifier.padding(padding))
            else -> {
                val m = state.merchant!!
                Column(
                    Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()),
                ) {
                    if (state.offline) OfflineBanner("Sin conexión: datos guardados; el estado de cuenta no está disponible.")
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(m.taxpayerName, style = MaterialTheme.typography.titleLarge)
                                m.tradeName?.let { Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                            }
                            StatusChip(m.status, m.statusLabel)
                        }

                        SectionCard("Datos") {
                            InfoRow("Folio", m.folio)
                            InfoRow("Giro", listOfNotNull(m.businessType, m.businessDescription).joinToString(" · "))
                            InfoRow("Tipo", m.commerceTypeLabel)
                            InfoRow("Tianguis", m.tianguis)
                            InfoRow("Ruta", m.route)
                            InfoRow("Ubicación", m.location)
                            InfoRow("Metros", "${Money.parse(m.meters).stripTrailingZeros().toPlainString()} m")
                            InfoRow("Teléfono", m.phone)
                        }

                        state.account?.let { AccountCard(it) }

                        if (state.receipts.isNotEmpty()) {
                            SectionCard("Historial de pagos") {
                                state.receipts.forEachIndexed { i, r ->
                                    if (i > 0) HorizontalDivider()
                                    Row(
                                        Modifier.fillMaxWidth().clickable { onReceipt(r.id) }.padding(vertical = 10.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                    ) {
                                        Column(Modifier.weight(1f)) {
                                            Text(r.folio, fontFamily = FontFamily.Monospace, fontWeight = FontWeight.SemiBold)
                                            Text(
                                                "${r.date} ${r.time} · ${r.concepts.orEmpty()}",
                                                style = MaterialTheme.typography.bodySmall,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                            )
                                        }
                                        Column(horizontalAlignment = Alignment.End) {
                                            Text(Money.format(r.total), fontWeight = FontWeight.SemiBold)
                                            StatusChip(r.status, r.statusLabel)
                                        }
                                    }
                                }
                            }
                        }
                        Spacer(Modifier.height(8.dp))
                    }
                }
            }
        }
    }
}

@Composable
private fun AccountCard(account: AccountDto) {
    SectionCard("Estado de cuenta ${account.fiscalYear}") {
        val annuality = account.annuality
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.weight(1f)) {
                Text("Anualidad", fontWeight = FontWeight.SemiBold)
                Text(
                    annuality?.let { Money.format(it.amount) } ?: "Sin obligación",
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            annuality?.let { StatusChip(it.status, if (it.status == "paid") "PAGADO" else "PENDIENTE") }
        }
        HorizontalDivider(Modifier.padding(vertical = 8.dp))
        InfoRow("Boletos", "${account.tickets.quantity} × ${Money.format(account.tickets.unitPrice)} = ${Money.format(account.tickets.total)}")
        InfoRow("Total pagado", Money.format(account.totalPaid))
        InfoRow(
            "Adeudos",
            if (account.debt.count == 0) "Sin adeudos" else "${Money.format(account.debt.total)} (${account.debt.years.joinToString(", ")})",
        )
    }
}
