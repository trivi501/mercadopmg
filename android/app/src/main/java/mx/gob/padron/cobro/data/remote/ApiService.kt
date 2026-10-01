package mx.gob.padron.cobro.data.remote

import okhttp3.ResponseBody
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query
import retrofit2.http.Streaming

interface ApiService {

    @POST("api/v1/login")
    suspend fun login(@Body body: LoginRequest): DataWrapper<LoginData>

    @POST("api/v1/logout")
    suspend fun logout()

    @GET("api/v1/me")
    suspend fun me(): DataWrapper<UserDto>

    @GET("api/v1/catalogs")
    suspend fun catalogs(): DataWrapper<CatalogsDto>

    @GET("api/v1/dashboard")
    suspend fun dashboard(@Query("mine") mine: Int = 1): DataWrapper<DashboardDto>

    @GET("api/v1/merchants")
    suspend fun merchants(
        @Query("search") search: String? = null,
        @Query("status") status: String? = null,
        @Query("updated_since") updatedSince: String? = null,
        @Query("per_page") perPage: Int = 30,
        @Query("page") page: Int = 1,
    ): Paginated<MerchantDto>

    @GET("api/v1/merchants/folio/{folio}")
    suspend fun merchantByFolio(@Path("folio") folio: String): DataWrapper<MerchantDto>

    @GET("api/v1/merchants/{id}")
    suspend fun merchant(@Path("id") id: Int): DataWrapper<MerchantDto>

    @GET("api/v1/merchants/{id}/account")
    suspend fun account(@Path("id") id: Int): DataWrapper<AccountDto>

    @GET("api/v1/merchants/{id}/receipts")
    suspend fun merchantReceipts(@Path("id") id: Int): Paginated<ReceiptDto>

    @POST("api/v1/payments")
    suspend fun charge(@Body body: ChargeRequest): ChargeResponse

    @GET("api/v1/receipts")
    suspend fun receipts(
        @Query("mine") mine: Int = 1,
        @Query("date_from") dateFrom: String? = null,
        @Query("page") page: Int = 1,
    ): Paginated<ReceiptDto>

    @GET("api/v1/receipts/{id}")
    suspend fun receipt(@Path("id") id: Int, @Query("paper_width") paperWidth: Int): DataWrapper<ReceiptPayload>

    @POST("api/v1/receipts/{id}/print")
    suspend fun markPrinted(@Path("id") id: Int, @Body body: PaperRequest): DataWrapper<ReceiptPayload>

    @POST("api/v1/receipts/{id}/reprint")
    suspend fun reprint(
        @Path("id") id: Int,
        @Body body: ReasonRequest,
        @Query("paper_width") paperWidth: Int,
    ): DataWrapper<ReceiptPayload>

    @Streaming
    @GET("api/v1/receipts/{id}/pdf")
    suspend fun receiptPdf(@Path("id") id: Int): ResponseBody

    @POST("api/v1/sync")
    suspend fun sync(@Body body: SyncRequest): SyncResponse
}
