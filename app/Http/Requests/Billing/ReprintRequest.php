<?php

namespace App\Http\Requests\Billing;

use App\Models\Receipt;
use Illuminate\Foundation\Http\FormRequest;

class ReprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('receipt');

        return $receipt instanceof Receipt && (bool) $this->user()?->can('reprint', $receipt);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:4', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'motivo'];
    }
}
