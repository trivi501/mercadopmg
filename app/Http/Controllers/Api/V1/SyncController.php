<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ChargeRequest;
use App\Http\Requests\Merchants\MerchantRequest;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Services\MerchantService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Cola de sincronización de la app Android.
 *
 * Recibe un lote de operaciones capturadas sin conexión. Cada operación
 * trae su propio UUID, por lo que reenviar el lote completo es seguro: las
 * operaciones ya aplicadas se reportan como `duplicate` y no se cobran otra vez.
 */
class SyncController extends Controller
{
    public const int MAX_OPERATIONS = 100;

    public function __construct(
        private readonly MerchantService $merchants,
        private readonly PaymentService $payments,
    ) {}

    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:'.self::MAX_OPERATIONS],
            'operations.*.uuid' => ['required', 'uuid'],
            'operations.*.type' => ['required', 'in:merchant.create,payment.create'],
            'operations.*.payload' => ['required', 'array'],
        ]);

        $user = $request->user();
        $results = [];

        foreach ($request->input('operations') as $op) {
            $payload = [...$op['payload'], 'uuid' => $op['uuid']];

            try {
                $result = $op['type'] === 'merchant.create'
                    ? $this->createMerchant($payload, $request)
                    : $this->createPayment($payload, $request);

                $results[] = $result + ['uuid' => $op['uuid'], 'type' => $op['type']];
            } catch (ValidationException $e) {
                $results[] = ['uuid' => $op['uuid'], 'type' => $op['type'], 'status' => 'rejected', 'code' => 'validation_error', 'message' => $e->getMessage(), 'errors' => $e->errors()];
            } catch (BusinessRuleException $e) {
                $results[] = ['uuid' => $op['uuid'], 'type' => $op['type'], 'status' => 'rejected', 'code' => $e->errorCode, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'data' => $results,
            'server_time' => now()->toIso8601String(),
            'user_id' => $user?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createMerchant(array $payload, Request $request): array
    {
        abort_unless($request->user()?->can('create', Merchant::class), 403);

        $rules = MerchantRequest::create('/', 'POST', $payload)->rules();
        $data = Validator::make($payload, $rules)->validate();
        unset($data['meters_reason']);

        $merchant = $this->merchants->create($data, $request->user());

        return [
            'status' => $merchant->wasRecentlyCreated ? 'created' : 'duplicate',
            'id' => $merchant->id,
            'folio' => $merchant->folio,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createPayment(array $payload, Request $request): array
    {
        abort_unless($request->user()?->can('create', Receipt::class), 403);

        // Un comerciante dado de alta sin conexión se referencia por su UUID local.
        if (empty($payload['merchant_id']) && ! empty($payload['merchant_uuid'])) {
            $payload['merchant_id'] = Merchant::query()->where('uuid', $payload['merchant_uuid'])->value('id');
        }

        $data = Validator::make($payload, ChargeRequest::chargeRules())->validate();

        $receipt = $this->payments->charge([
            ...$data,
            'source' => 'android',
            'device' => $request->header('X-Device-Name') ?: $request->userAgent(),
        ], $request->user());

        return [
            'status' => $receipt->wasRecentlyCreated ? 'created' : 'duplicate',
            'id' => $receipt->id,
            'folio' => $receipt->folio,
            'total' => $receipt->total,
        ];
    }
}
