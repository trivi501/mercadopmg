package mx.gob.padron.cobro.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.toRoute
import kotlinx.serialization.Serializable
import mx.gob.padron.cobro.AppContainer
import mx.gob.padron.cobro.ui.charge.ChargeScreen
import mx.gob.padron.cobro.ui.history.HistoryScreen
import mx.gob.padron.cobro.ui.home.HomeScreen
import mx.gob.padron.cobro.ui.login.LoginScreen
import mx.gob.padron.cobro.ui.merchant.MerchantScreen
import mx.gob.padron.cobro.ui.receipt.ProvisionalTicketScreen
import mx.gob.padron.cobro.ui.receipt.ReceiptScreen
import mx.gob.padron.cobro.ui.search.SearchScreen
import mx.gob.padron.cobro.ui.settings.SettingsScreen

@Serializable object LoginRoute
@Serializable object HomeRoute
@Serializable data class SearchRoute(val scan: Boolean = false)
@Serializable data class MerchantRoute(val id: Int)
@Serializable data class ChargeRoute(val merchantId: Int)
@Serializable data class ReceiptRoute(val id: Int, val justCharged: Boolean = false)
@Serializable object ProvisionalTicketRoute
@Serializable object HistoryRoute
@Serializable object SettingsRoute

@Composable
fun CobroNavHost(container: AppContainer) {
    val nav = rememberNavController()

    // Token rechazado por el servidor: volver al login.
    LaunchedEffect(Unit) {
        container.apiClient.sessionExpired.collect {
            nav.navigate(LoginRoute) { popUpTo(0) { inclusive = true } }
        }
    }

    NavHost(navController = nav, startDestination = if (container.session.isLoggedIn) HomeRoute else LoginRoute) {
        composable<LoginRoute> {
            LoginScreen(container, onLoggedIn = {
                nav.navigate(HomeRoute) { popUpTo(LoginRoute) { inclusive = true } }
            })
        }
        composable<HomeRoute> {
            HomeScreen(
                container,
                onSearch = { scan -> nav.navigate(SearchRoute(scan)) },
                onHistory = { nav.navigate(HistoryRoute) },
                onSettings = { nav.navigate(SettingsRoute) },
            )
        }
        composable<SearchRoute> { entry ->
            SearchScreen(
                container,
                startWithScan = entry.toRoute<SearchRoute>().scan,
                onBack = { nav.popBackStack() },
                onMerchant = { id -> nav.navigate(MerchantRoute(id)) },
            )
        }
        composable<MerchantRoute> { entry ->
            val id = entry.toRoute<MerchantRoute>().id
            MerchantScreen(
                container,
                merchantId = id,
                onBack = { nav.popBackStack() },
                onCharge = { nav.navigate(ChargeRoute(id)) },
                onReceipt = { receiptId -> nav.navigate(ReceiptRoute(receiptId)) },
            )
        }
        composable<ChargeRoute> { entry ->
            val merchantId = entry.toRoute<ChargeRoute>().merchantId
            ChargeScreen(
                container,
                merchantId = merchantId,
                onBack = { nav.popBackStack() },
                onIssued = { receiptId ->
                    nav.navigate(ReceiptRoute(receiptId, justCharged = true)) {
                        popUpTo<ChargeRoute> { inclusive = true }
                    }
                },
                onQueued = {
                    nav.navigate(ProvisionalTicketRoute) {
                        popUpTo<ChargeRoute> { inclusive = true }
                    }
                },
            )
        }
        composable<ReceiptRoute> { entry ->
            val route = entry.toRoute<ReceiptRoute>()
            ReceiptScreen(
                container,
                receiptId = route.id,
                justCharged = route.justCharged,
                onBack = { nav.popBackStack() },
                onHome = { nav.navigate(HomeRoute) { popUpTo(HomeRoute) { inclusive = true } } },
            )
        }
        composable<ProvisionalTicketRoute> {
            ProvisionalTicketScreen(
                container,
                onHome = { nav.navigate(HomeRoute) { popUpTo(HomeRoute) { inclusive = true } } },
            )
        }
        composable<HistoryRoute> {
            HistoryScreen(
                container,
                onBack = { nav.popBackStack() },
                onReceipt = { id -> nav.navigate(ReceiptRoute(id)) },
            )
        }
        composable<SettingsRoute> {
            SettingsScreen(
                container,
                onBack = { nav.popBackStack() },
                onLoggedOut = { nav.navigate(LoginRoute) { popUpTo(0) { inclusive = true } } },
            )
        }
    }
}
