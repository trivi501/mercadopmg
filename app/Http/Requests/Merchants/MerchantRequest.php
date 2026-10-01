<?php

namespace App\Http\Requests\Merchants;

use App\Enums\CommerceType;
use App\Enums\MerchantStatus;
use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y modificación de comerciantes (web y API).
 */
class MerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchant = $this->route('merchant');

        return $merchant instanceof Merchant
            ? (bool) $this->user()?->can('update', $merchant)
            : (bool) $this->user()?->can('create', Merchant::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['taxpayer_name', 'trade_name']))
            ->map(fn ($v) => is_string($v) ? preg_replace('/\s+/', ' ', trim($v)) : $v)
            ->all());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('merchant') instanceof Merchant;
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'uuid' => ['nullable', 'uuid'],
            'taxpayer_name' => [$required, 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'business_type_id' => [$required, 'integer', Rule::exists('business_types', 'id')->whereNull('deleted_at')],
            'business_description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:150'],
            'municipality' => ['nullable', 'string', 'max:150'],
            'state' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_street' => ['nullable', 'string', 'max:150'],
            'location_number' => ['nullable', 'string', 'max:20'],
            'location_neighborhood' => ['nullable', 'string', 'max:150'],
            'location_reference' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'route_id' => ['nullable', 'integer', Rule::exists('routes', 'id')->whereNull('deleted_at'), Rule::requiredIf($this->input('commerce_type') === CommerceType::Route->value)],
            'tianguis_id' => ['nullable', 'integer', Rule::exists('tianguis', 'id')->whereNull('deleted_at'), Rule::requiredIf($this->input('commerce_type') === CommerceType::Tianguis->value)],
            'meters' => [$required, 'numeric', 'min:0', 'max:999'],
            'meters_reason' => ['nullable', 'string', 'max:255'],
            'commerce_type' => [$required, Rule::enum(CommerceType::class)],
            'status' => ['sometimes', Rule::enum(MerchantStatus::class)],
            'registered_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'taxpayer_name' => 'nombre del contribuyente',
            'trade_name' => 'nombre comercial',
            'business_type_id' => 'giro',
            'business_description' => 'descripción del giro',
            'phone' => 'teléfono',
            'meters' => 'metros',
            'commerce_type' => 'tipo de comercio',
            'tianguis_id' => 'tianguis',
            'route_id' => 'ruta',
            'latitude' => 'latitud',
            'longitude' => 'longitud',
            'registered_at' => 'fecha de alta',
        ];
    }

    /**
     * Datos del comerciante sin campos auxiliares.
     *
     * @return array<string, mixed>
     */
    public function merchantData(): array
    {
        return collect($this->validated())->except(['meters_reason'])->all();
    }
}
