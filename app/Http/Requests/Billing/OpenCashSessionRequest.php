<?php

namespace App\Http\Requests\Billing;

use App\Models\CashRegisterSession;
use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', CashRegisterSession::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'opening_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'opening_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['opening_amount' => 'fondo inicial', 'opening_notes' => 'notas'];
    }
}
