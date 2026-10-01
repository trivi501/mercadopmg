<?php

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMethod;
use App\Models\Receipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro de un cobro (boletos, anualidad u otros conceptos).
 */
class ChargeRequest extends FormRequest
{
    public const int MAX_TICKETS = 500;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Receipt::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::chargeRules();
    }

    /**
     * Reglas reutilizadas por la sincronización por lotes.
     *
     * @return array<string, mixed>
     */
    public static function chargeRules(string $prefix = ''): array
    {
        return [
            "{$prefix}uuid" => ['nullable', 'uuid'],
            "{$prefix}merchant_id" => ['required', 'integer', Rule::exists('merchants', 'id')->whereNull('deleted_at')],
            "{$prefix}payment_method" => ['required', Rule::enum(PaymentMethod::class)],
            "{$prefix}reference" => ['nullable', 'string', 'max:100'],
            "{$prefix}notes" => ['nullable', 'string', 'max:500'],
            "{$prefix}issued_at" => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(10)->toDateTimeString()],
            "{$prefix}items" => ['required', 'array', 'min:1', 'max:20'],
            "{$prefix}items.*.concept_id" => ['required', 'integer', 'exists:concepts,id'],
            "{$prefix}items.*.quantity" => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_TICKETS],
            "{$prefix}items.*.fiscal_year" => ['nullable', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            "{$prefix}items.*.service_date" => ['nullable', 'date'],
            "{$prefix}items.*.tianguis_id" => ['nullable', 'integer', 'exists:tianguis,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'merchant_id' => 'comerciante',
            'payment_method' => 'forma de pago',
            'items' => 'conceptos',
            'items.*.quantity' => 'cantidad',
            'items.*.fiscal_year' => 'ejercicio',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function chargeData(): array
    {
        return [
            ...$this->validated(),
            'source' => $this->is('api/*') ? 'android' : 'web',
            'device' => $this->header('X-Device-Name') ?: $this->userAgent(),
        ];
    }
}
