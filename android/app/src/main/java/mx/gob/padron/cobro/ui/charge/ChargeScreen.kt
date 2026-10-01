package mx.gob.padron.cobro.ui.charge

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalIconButton
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.lifecycle.SavedStateHandle
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
import mx.gob.padron.cobro.data.local.ConceptEntity
import mx.gob.padron.cobro.data.local.MerchantEntity
import mx.gob.padron.cobro.data.remote.AccountDto
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.ChargeItem
import mx.gob.padron.cobro.data.remote.ChargeRequest
import mx.gob.padron.cobro.data.remote.errorMessage
import mx.gob.padron.cobro.data.repository.ChargeRepository
import mx.gob.padron.cobro.print.TicketFormatter
import mx.gob.padron.cobro.ui.common.ErrorMessage
import mx.gob.padron.cobro.ui.common.Loading
import mx.gob.padron.cobro.ui.common.OfflineBanner
import mx.gob.padron.cobro.ui.common.SectionCard
import mx.gob.padron.cobro.ui.common.containerViewModel
import mx.gob.padron.cobro.util.Money
import java.math.BigDecimal
import java.time.Year
import java.util.UUID

private val PAYMENT_METHODS = listOf("cash" to "Efectivo", "transfer" to "Transferencia", "card" to "Tarjeta")

data class ChargeState(
    val loading: Boolean = true,
    val merchant: MerchantEntity? = null,
    val account: AccountDto? = null,
    val offline: Boolean = false,
    val ticketConcept: ConceptEntity? = null,
    val annualityConcept: ConceptEntity? = null,
    val tickets: Int = 1,
    val annualityYear: Int? = null,
    val paymentMethod: String = "cash",
    val reference: String = "",
    val confirming: Boolean = false,
    val submitting: Boolean = false,
    val error: String? = null,
) {
    /** Ejercicios con anualidad pendiente (hasta el año actual). */
    val pendingYears: List<Int>
        get() = account?.annualities
            ?.filter { it.status == "pending" && it.fiscalYear <= Year.now().value }
            ?.map { it.fiscalYear }
            ?.sortedDescending()
            .orEmpty()

    val lines: List<TicketFormatter.Line>
        get() = buildList {
            val t = ticketConcept
            if (t != null && tickets > 0) {
                add(TicketFormatter.Line(t.name, tickets, t.amount, Money.plain(Money.line(tickets, t.amount))))
            }
            val a = annualityConcept
            val year = annualityYear
            if (a != null && year != null) {
                val amount = account?.annualities?.firstOrNull { it.fiscalYear == year }?.amount ?: a.amount
                add(TicketFormatter.Line("${a.name} $year", 1, amount, Money.plain(Money.parse(amount))))
            }
        }

    val total: BigDecimal get() = Money.sum(lines.map { Money.parse(it.amount) })
}

sealed interface ChargeEvent {
    data class Issued(val receiptId: Int, val duplicate: Boolean) : ChargeEvent
    data object Queued : ChargeEvent
}

class ChargeViewModel(
    private val container: AppContainer,
    private val merchantId: Int,
    savedState: SavedStateHandle,
) : ViewModel() {
    private val _state = MutableStateFlow(ChargeState())
    val state = _state.asStateFlow()

    private val _events = MutableSharedFlow<ChargeEvent>(extraBufferCapacity = 1)
    val events = _events.asSharedFlow()

    /**
     * Un UUID por operación de cobro. Se conserva aunque se rote la pantalla o
     * se reintente, para que el servidor nunca registre el cobro dos veces.
     */
    private val operationUuid: String = savedState.get<String>(KEY_UUID)
        ?: UUID.randomUUID().toString().also { savedState[KEY_UUID] = it }

    init { load() }

    fun load() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            val concepts = container.catalogs.conceptsNow().ifEmpty {
                container.catalogs.refresh()
                container.catalogs.conceptsNow()
            }
            val merchant = container.merchants.get(merchantId)
            val account = container.merchants.account(merchantId)

            _state.update {
                it.copy(
                    loading = false,
                    merchant = (merchant as? ApiResult.Success)?.value,
                    account = (account as? ApiResult.Success)?.value,
                    offline = account is ApiResult.Offline,
                    ticketConcept = concepts.firstOrNull { c -> c.type == "ticket" },
                    annualityConcept = concepts.firstOrNull { c -> c.type == "annuality" },
                    error = when {
                        merchant !is ApiResult.Success -> merchant.errorMessage()
                        concepts.isEmpty() -> "No hay conceptos de cobro. Conéctese para descargar el catálogo."
                        else -> null
                    },
                )
            }
        }
    }

    fun setTickets(n: Int) = _state.update { it.copy(tickets = n.coerceIn(0, MAX_TICKETS), error = null) }
    fun toggleAnnuality(year: Int) = _state.update { it.copy(annualityYear = if (it.annualityYear == year) null else year, error = null) }
    fun setPaymentMethod(m: String) = _state.update { it.copy(paymentMethod = m) }
    fun setReference(r: String) = _state.update { it.copy(reference = r.take(100)) }
    fun askConfirm() = _state.update { it.copy(confirming = it.lines.isNotEmpty()) }
    fun dismissConfirm() = _state.update { it.copy(confirming = false) }

    fun charge() {
        val s = _state.value
        val merchant = s.merchant ?: return
        if (s.lines.isEmpty() || s.submitting) return

        val items = buildList {
            s.ticketConcept?.let { if (s.tickets > 0) add(ChargeItem(conceptId = it.id, quantity = s.tickets)) }
            s.annualityConcept?.let { c -> s.annualityYear?.let { add(ChargeItem(conceptId = c.id, quantity = 1, fiscalYear = it)) } }
        }
        val request = ChargeRequest(
            uuid = operationUuid,
            merchantId = merchant.id,
            paymentMethod = s.paymentMethod,
            reference = s.reference.ifBlank { null },
            paperWidth = container.session.paperWidth,
            items = items,
        )

        _state.update { it.copy(confirming = false, submitting = true, error = null) }

        viewModelScope.launch {
            val outcome = container.charges.charge(
                request = request,
                merchant = merchant,
                description = s.lines.joinToString(", ") { if (it.quantity > 1) "${it.concept} × ${it.quantity}" else it.concept },
                total = Money.plain(s.total),
                lines = s.lines,
            )

            when (outcome) {
                is ChargeRepository.Outcome.Issued -> _events.emit(ChargeEvent.Issued(outcome.payload.receipt.id, outcome.duplicate))
                is ChargeRepository.Outcome.Queued -> {
                    container.provisionalTicket = outcome.ticket
                    _events.emit(ChargeEvent.Queued)
                }
                is ChargeRepository.Outcome.Rejected -> {
                    _state.update { it.copy(submitting = false, error = outcome.message) }
                    if (outcome.code == "annuality_already_paid") {
                        _state.update { it.copy(annualityYear = null) }
                        load()
                    }
                }
            }
        }
    }

    private companion object {
        const val KEY_UUID = "operation_uuid"
        const val MAX_TICKETS = 500
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
fun ChargeScreen(
    container: AppContainer,
    merchantId: Int,
    onBack: () -> Unit,
    onIssued: (Int) -> Unit,
    onQueued: () -> Unit,
) {
    val vm = containerViewModel(container, key = "charge-$merchantId") { c, saved -> ChargeViewModel(c, merchantId, saved) }
    val state by vm.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) {
        vm.events.collect {
            when (it) {
                is ChargeEvent.Issued -> onIssued(it.receiptId)
                ChargeEvent.Queued -> onQueued()
            }
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Cobrar") },
                navigationIcon = {
                    IconButton(onClick = onBack, enabled = !state.submitting) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Regresar")
                    }
                },
            )
        },
        bottomBar = {
            if (state.merchant != null) {
                Surface(tonalElevation = 3.dp) {
                    Column(Modifier.padding(16.dp)) {
                        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                            Text("TOTAL", style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                            Text(Money.format(state.total), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                        }
                        state.error?.let {
                            Text(it, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(top = 4.dp))
                        }
                        Button(
                            onClick = vm::askConfirm,
                            enabled = state.lines.isNotEmpty() && !state.submitting && state.merchant?.status == "active",
                            modifier = Modifier.fillMaxWidth().height(56.dp).padding(top = 8.dp),
                        ) {
                            if (state.submitting) {
                                CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(22.dp))
                            } else {
                                Text("COBRAR ${Money.format(state.total)}", style = MaterialTheme.typography.titleMedium)
                            }
                        }
                    }
                }
            }
        },
    ) { padding ->
        when {
            state.loading && state.merchant == null -> Loading(Modifier.padding(padding))
            state.merchant == null -> ErrorMessage(state.error ?: "No se pudo cargar el comerciante.", vm::load, Modifier.padding(padding))
            else -> {
                val m = state.merchant!!
                Column(Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState())) {
                    if (state.offline) {
                        OfflineBanner("Sin conexión: el cobro se guardará y se enviará después. La anualidad requiere conexión.")
                    }
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Column {
                            Text(m.folio, fontFamily = FontFamily.Monospace, color = MaterialTheme.colorScheme.primary)
                            Text(m.taxpayerName, style = MaterialTheme.typography.titleLarge)
                            Text(
                                listOfNotNull(m.businessType, m.tianguis, m.route).joinToString(" · "),
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }

                        state.ticketConcept?.let { concept ->
                            SectionCard("Boletos de tianguista") {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    FilledTonalIconButton(onClick = { vm.setTickets(state.tickets - 1) }, modifier = Modifier.size(56.dp)) {
                                        Icon(Icons.Default.Remove, contentDescription = "Menos")
                                    }
                                    OutlinedTextField(
                                        value = state.tickets.toString(),
                                        onValueChange = { v -> vm.setTickets(v.filter(Char::isDigit).take(3).toIntOrNull() ?: 0) },
                                        singleLine = true,
                                        textStyle = MaterialTheme.typography.headlineSmall.copy(textAlign = TextAlign.Center),
                                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                        modifier = Modifier.width(96.dp).padding(horizontal = 8.dp),
                                    )
                                    FilledTonalIconButton(onClick = { vm.setTickets(state.tickets + 1) }, modifier = Modifier.size(56.dp)) {
                                        Icon(Icons.Default.Add, contentDescription = "Más")
                                    }
                                }
                                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 8.dp)) {
                                    listOf(1, 2, 3, 4, 5, 10).forEach { n ->
                                        FilterChip(selected = state.tickets == n, onClick = { vm.setTickets(n) }, label = { Text("$n") })
                                    }
                                }
                                Text(
                                    "${state.tickets} × ${Money.format(concept.amount)} = ${Money.format(Money.line(state.tickets, concept.amount))}",
                                    style = MaterialTheme.typography.bodyLarge,
                                    modifier = Modifier.padding(top = 4.dp),
                                )
                            }
                        }

                        state.annualityConcept?.let { concept ->
                            SectionCard("Anualidad · ${concept.name}") {
                                when {
                                    state.account == null ->
                                        Text("Requiere conexión para verificar si ya fue pagada.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    state.pendingYears.isEmpty() ->
                                        Text("Anualidad ${state.account?.fiscalYear} PAGADA.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    else -> state.pendingYears.forEach { year ->
                                        val amount = state.account?.annualities?.firstOrNull { it.fiscalYear == year }?.amount ?: concept.amount
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Checkbox(checked = state.annualityYear == year, onCheckedChange = { vm.toggleAnnuality(year) })
                                            Text("Ejercicio $year — ${Money.format(amount)}  (PENDIENTE)")
                                        }
                                    }
                                }
                            }
                        }

                        SectionCard("Forma de pago") {
                            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                PAYMENT_METHODS.forEach { (value, label) ->
                                    FilterChip(
                                        selected = state.paymentMethod == value,
                                        onClick = { vm.setPaymentMethod(value) },
                                        label = { Text(label) },
                                    )
                                }
                            }
                            if (state.paymentMethod != "cash") {
                                OutlinedTextField(
                                    value = state.reference,
                                    onValueChange = vm::setReference,
                                    label = { Text("Referencia") },
                                    singleLine = true,
                                    modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    if (state.confirming) {
        AlertDialog(
            onDismissRequest = vm::dismissConfirm,
            title = { Text("Confirmar cobro") },
            text = {
                Column {
                    Text(state.merchant?.taxpayerName.orEmpty(), fontWeight = FontWeight.SemiBold)
                    HorizontalDivider(Modifier.padding(vertical = 8.dp))
                    state.lines.forEach { line ->
                        Row(Modifier.fillMaxWidth()) {
                            Text(
                                if (line.quantity > 1) "${line.concept} × ${line.quantity}" else line.concept,
                                modifier = Modifier.weight(1f),
                            )
                            Text(Money.format(line.amount))
                        }
                    }
                    HorizontalDivider(Modifier.padding(vertical = 8.dp))
                    Row(Modifier.fillMaxWidth()) {
                        Text("TOTAL", fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                        Text(Money.format(state.total), fontWeight = FontWeight.Bold)
                    }
                    Text(
                        PAYMENT_METHODS.firstOrNull { it.first == state.paymentMethod }?.second.orEmpty(),
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            },
            confirmButton = { Button(onClick = vm::charge) { Text("COBRAR") } },
            dismissButton = { TextButton(onClick = vm::dismissConfirm) { Text("Cancelar") } },
        )
    }
}
