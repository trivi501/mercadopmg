package mx.gob.padron.cobro.ui.search

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.launchIn
import kotlinx.coroutines.flow.onEach
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import mx.gob.padron.cobro.AppContainer
import mx.gob.padron.cobro.data.local.MerchantEntity
import mx.gob.padron.cobro.data.remote.ApiResult
import mx.gob.padron.cobro.data.remote.errorMessage
import mx.gob.padron.cobro.ui.common.OfflineBanner
import mx.gob.padron.cobro.ui.common.StatusChip
import mx.gob.padron.cobro.ui.common.containerViewModel

data class SearchState(
    val query: String = "",
    val results: List<MerchantEntity> = emptyList(),
    val loading: Boolean = false,
    val offline: Boolean = false,
    val error: String? = null,
)

@OptIn(FlowPreview::class)
class SearchViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(SearchState())
    val state = _state.asStateFlow()

    /** Emite el id del comerciante encontrado por QR. */
    private val _found = MutableSharedFlow<Int>(extraBufferCapacity = 1)
    val found = _found.asSharedFlow()

    private val queries = MutableStateFlow("")

    init {
        queries
            .debounce(350)
            .distinctUntilChanged()
            .onEach { search(it) }
            .launchIn(viewModelScope)
    }

    fun onQuery(value: String) {
        _state.update { it.copy(query = value) }
        queries.value = value
    }

    private suspend fun search(term: String) {
        if (term.trim().length < 2) {
            _state.update { it.copy(results = emptyList(), loading = false, error = null) }
            return
        }
        _state.update { it.copy(loading = true) }
        val result = container.merchants.search(term)
        _state.update { it.copy(results = result.merchants, offline = result.offline, error = result.error, loading = false) }
    }

    /** El QR del comerciante contiene sólo su folio (p. ej. COM-000001). */
    fun onScanned(content: String) {
        val folio = content.trim()
        _state.update { it.copy(loading = true, query = folio, error = null) }
        viewModelScope.launch {
            when (val r = container.merchants.byFolio(folio)) {
                is ApiResult.Success -> {
                    _state.update { it.copy(loading = false) }
                    _found.tryEmit(r.value.id)
                }
                else -> _state.update {
                    it.copy(
                        loading = false,
                        error = if (r is ApiResult.Failure && r.status == 404) "El QR \"$folio\" no corresponde a ningún comerciante." else r.errorMessage(),
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SearchScreen(
    container: AppContainer,
    startWithScan: Boolean,
    onBack: () -> Unit,
    onMerchant: (Int) -> Unit,
) {
    val vm = containerViewModel(container) { c, _ -> SearchViewModel(c) }
    val state by vm.state.collectAsStateWithLifecycle()
    val focus = remember { FocusRequester() }
    var scanLaunched by rememberSaveable { mutableStateOf(false) }

    val scanner = rememberLauncherForActivityResult(ScanContract()) { result ->
        result.contents?.let(vm::onScanned)
    }
    val launchScan = {
        scanner.launch(
            ScanOptions()
                .setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                .setPrompt("Apunte al código QR del comerciante")
                .setBeepEnabled(true)
                .setOrientationLocked(true),
        )
    }

    LaunchedEffect(Unit) {
        vm.found.collect { onMerchant(it) }
    }
    LaunchedEffect(Unit) {
        if (startWithScan && !scanLaunched) {
            scanLaunched = true
            launchScan()
        } else if (!startWithScan) {
            focus.requestFocus()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Buscar comerciante") },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Regresar") }
                },
                actions = {
                    IconButton(onClick = launchScan) { Icon(Icons.Default.QrCodeScanner, contentDescription = "Escanear QR") }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            OutlinedTextField(
                value = state.query,
                onValueChange = vm::onQuery,
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
                placeholder = { Text("Folio, nombre o teléfono") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth().padding(16.dp).focusRequester(focus),
            )
            if (state.loading) LinearProgressIndicator(Modifier.fillMaxWidth())
            if (state.offline) OfflineBanner()
            state.error?.let {
                Text(it, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(16.dp))
            }

            LazyColumn {
                items(state.results, key = { it.id }) { m ->
                    ListItem(
                        headlineContent = { Text(m.taxpayerName) },
                        overlineContent = { Text(m.folio, fontFamily = FontFamily.Monospace) },
                        supportingContent = {
                            Text(listOfNotNull(m.businessType, m.tianguis, m.route).joinToString(" · "))
                        },
                        trailingContent = { StatusChip(m.status, m.statusLabel) },
                        modifier = Modifier.clickable { onMerchant(m.id) },
                    )
                    HorizontalDivider()
                }
            }

            if (!state.loading && state.query.trim().length >= 2 && state.results.isEmpty() && state.error == null) {
                Text(
                    "Sin resultados.",
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(16.dp),
                )
            }
        }
    }
}
