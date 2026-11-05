<?php

namespace App\Http\Requests\Billing;

use App\Models\CashRegisterSession;
use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('cashSession');

        return $session instanceof CashRegisterSession && (bool) $this->user()?->can('close', $session);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['counted_cash' => 'efectivo contado', 'closing_notes' => 'notas'];
    }
}
