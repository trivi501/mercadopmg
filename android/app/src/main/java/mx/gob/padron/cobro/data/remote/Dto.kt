package mx.gob.padron.cobro.data.remote

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject

/*
 * Contrato de la API /api/v1 (ver docs/API.md del backend).
 * Los importes viajan como texto decimal ("40.00"); se convierten a BigDecimal en util/Money.kt.
 */

@Serializable
data class DataWrapper<T>(val data: T)

@Serializable
data class PageMeta(
    @SerialName("current_page") val currentPage: Int = 1,
    @SerialName("last_page") val lastPage: Int = 1,
    val total: Int = 0,
)

@Serializable
data class Paginated<T>(val data: List<T>, val meta: PageMeta? = null)

@Serializable
data class ApiErrorBody(val message: String = "", val code: String? = null)

// ---------- Autenticación ----------

@Serializable
data class LoginRequest(
    val username: String,
    val password: String,
    @SerialName("device_name") val deviceName: String,
)

@Serializable
data class LoginData(
    val token: String,
    @SerialName("expires_at") val expiresAt: String? = null,
    val user: UserDto,
)

@Serializable
data class UserDto(
    val id: Int,
    val name: String,
    val username: String,
    val role: String? = null,
    @SerialName("role_label") val roleLabel: String? = null,
    val permissions: List<String> = emptyList(),
)

// ---------- Catálogos ----------

@Serializable
data class NamedDto(val id: Int, val name: String)

@Serializable
data class OptionDto(val value: String, val label: String)

@Serializable
data class ConceptDto(
    val id: Int,
    val code: String,
    val name: String,
    val type: String,
    val amount: String,
    @SerialName("is_active") val isActive: Boolean = true,
)

@Serializable
data class SettingsDto(
    @SerialName("municipality_name") val municipalityName: String? = null,
    @SerialName("ticket_footer") val ticketFooter: String? = null,
)

@Serializable
data class CatalogsDto(
    val concepts: List<ConceptDto> = emptyList(),
    val tianguis: List<NamedDto> = emptyList(),
    val routes: List<NamedDto> = emptyList(),
    @SerialName("payment_methods") val paymentMethods: List<OptionDto> = emptyList(),
    val settings: SettingsDto = SettingsDto(),
    @SerialName("server_time") val serverTime: String? = null,
)

// ---------- Comerciantes ----------

@Serializable
data class MerchantDto(
    val id: Int,
    val uuid: String,
    val folio: String,
    @SerialName("taxpayer_name") val taxpayerName: String,
    @SerialName("trade_name") val tradeName: String? = null,
    @SerialName("business_type") val businessType: String? = null,
    @SerialName("business_description") val businessDescription: String? = null,
    val phone: String? = null,
    val location: String? = null,
    @SerialName("tianguis_id") val tianguisId: Int? = null,
    val tianguis: String? = null,
    val route: String? = null,
    val meters: String = "0",
    @SerialName("commerce_type_label") val commerceTypeLabel: String = "",
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    @SerialName("updated_at") val updatedAt: String? = null,
)

@Serializable
data class AnnualityDto(
    val concept: String = "",
    val amount: String,
    @SerialName("fiscal_year") val fiscalYear: Int,
    val status: String,
    @SerialName("receipt_folio") val receiptFolio: String? = null,
)

@Serializable
data class TicketsSummaryDto(
    val quantity: Int = 0,
    @SerialName("unit_price") val unitPrice: String = "0.00",
    val total: String = "0.00",
)

@Serializable
data class DebtDto(val count: Int = 0, val total: String = "0.00", val years: List<Int> = emptyList())

@Serializable
data class AccountDto(
    @SerialName("fiscal_year") val fiscalYear: Int,
    val annuality: AnnualityDto? = null,
    val annualities: List<AnnualityDto> = emptyList(),
    val tickets: TicketsSummaryDto = TicketsSummaryDto(),
    @SerialName("total_paid") val totalPaid: String = "0.00",
    val debt: DebtDto = DebtDto(),
)

// ---------- Cobros y recibos ----------

@Serializable
data class ChargeItem(
    @SerialName("concept_id") val conceptId: Int,
    val quantity: Int? = null,
    @SerialName("fiscal_year") val fiscalYear: Int? = null,
)

@Serializable
data class ChargeRequest(
    val uuid: String,
    @SerialName("merchant_id") val merchantId: Int,
    @SerialName("payment_method") val paymentMethod: String,
    val reference: String? = null,
    @SerialName("issued_at") val issuedAt: String? = null,
    @SerialName("paper_width") val paperWidth: Int = 58,
    val items: List<ChargeItem>,
)

@Serializable
data class MerchantRefDto(val id: Int, val folio: String, @SerialName("taxpayer_name") val taxpayerName: String)

@Serializable
data class ReceiptItemDto(
    val description: String,
    val quantity: Int,
    @SerialName("unit_price") val unitPrice: String,
    val amount: String,
)

@Serializable
data class ReceiptDto(
    val id: Int,
    val uuid: String,
    val folio: String,
    @SerialName("merchant_id") val merchantId: Int,
    val merchant: MerchantRefDto? = null,
    val date: String,
    val time: String,
    val total: String,
    @SerialName("payment_method_label") val paymentMethodLabel: String = "",
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    @SerialName("printed_at") val printedAt: String? = null,
    @SerialName("reprint_count") val reprintCount: Int? = 0,
    val concepts: String? = null,
    val items: List<ReceiptItemDto> = emptyList(),
)

@Serializable
data class TicketHeader(val municipality: String, val system: String, val title: String)

@Serializable
data class TicketItem(
    val concept: String,
    val quantity: Int,
    @SerialName("unit_price") val unitPrice: String,
    val amount: String,
)

@Serializable
data class ReprintInfo(val number: Int, val reason: String, val date: String, val time: String, val user: String)

/** Renglón listo para la impresora térmica (ya ajustado a 32/48 columnas por el servidor). */
@Serializable
data class TicketLine(
    val text: String,
    val align: String = "left",
    val bold: Boolean = false,
    val size: String = "normal",
)

@Serializable
data class TicketDto(
    val header: TicketHeader,
    val folio: String,
    val date: String,
    val time: String,
    val taxpayer: String,
    @SerialName("merchant_folio") val merchantFolio: String,
    @SerialName("business_type") val businessType: String,
    val tianguis: String? = null,
    val route: String? = null,
    val items: List<TicketItem>,
    val total: String,
    val currency: String = "MXN",
    @SerialName("payment_method") val paymentMethod: String,
    val user: String,
    @SerialName("is_cancelled") val isCancelled: Boolean = false,
    val reprint: ReprintInfo? = null,
    @SerialName("qr_content") val qrContent: String,
    val footer: String = "",
    @SerialName("paper_width") val paperWidth: Int = 58,
    val lines: List<TicketLine> = emptyList(),
    @SerialName("pdf_url") val pdfUrl: String? = null,
)

@Serializable
data class ReceiptPayload(val receipt: ReceiptDto, val ticket: TicketDto)

@Serializable
data class ChargeResponse(val data: ReceiptPayload, val duplicate: Boolean = false, val message: String = "")

@Serializable
data class ReasonRequest(val reason: String)

@Serializable
data class PaperRequest(@SerialName("paper_width") val paperWidth: Int)

// ---------- Sincronización ----------

@Serializable
data class SyncOperation(val uuid: String, val type: String, val payload: JsonObject)

@Serializable
data class SyncRequest(val operations: List<SyncOperation>)

@Serializable
data class SyncResult(
    val uuid: String,
    val type: String,
    val status: String,
    val id: Int? = null,
    val folio: String? = null,
    val total: String? = null,
    val code: String? = null,
    val message: String? = null,
)

@Serializable
data class SyncResponse(val data: List<SyncResult>, @SerialName("server_time") val serverTime: String? = null)

// ---------- Dashboard ----------

@Serializable
data class DashboardDto(
    @SerialName("revenue_today") val revenueToday: String = "0.00",
    @SerialName("charges_today") val chargesToday: Int = 0,
    @SerialName("tickets_today") val ticketsToday: Int = 0,
    @SerialName("annualities_paid_today") val annualitiesPaidToday: Int = 0,
    @SerialName("merchants_total") val merchantsTotal: Int = 0,
)
