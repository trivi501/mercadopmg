package mx.gob.padron.cobro

import android.content.Context
import mx.gob.padron.cobro.data.SessionStore
import mx.gob.padron.cobro.data.local.AppDatabase
import mx.gob.padron.cobro.data.remote.ApiClient
import mx.gob.padron.cobro.data.repository.AuthRepository
import mx.gob.padron.cobro.data.repository.CatalogRepository
import mx.gob.padron.cobro.data.repository.ChargeRepository
import mx.gob.padron.cobro.data.repository.MerchantRepository
import mx.gob.padron.cobro.data.repository.ReceiptRepository
import mx.gob.padron.cobro.data.remote.TicketDto
import mx.gob.padron.cobro.print.BluetoothPrinter
import mx.gob.padron.cobro.print.TicketPrinter
import mx.gob.padron.cobro.sync.SyncScheduler

/**
 * Inyección de dependencias manual: una instancia por aplicación.
 */
class AppContainer(context: Context) {
    val session = SessionStore(context)
    val apiClient = ApiClient(session)
    private val api = apiClient.api
    private val db = AppDatabase.build(context)

    val scheduler = SyncScheduler(context)
    val printer = BluetoothPrinter(context)
    val ticketPrinter = TicketPrinter(printer, session)

    /** Último comprobante provisional (cobro encolado sin conexión) para mostrarlo e imprimirlo. */
    var provisionalTicket: TicketDto? = null

    val auth = AuthRepository(api, session)
    val catalogs = CatalogRepository(api, db.concepts(), session)
    val merchants = MerchantRepository(api, db.merchants(), session)
    val charges = ChargeRepository(api, db.pendingCharges(), session, scheduler)
    val receipts = ReceiptRepository(api, session, context)
}
