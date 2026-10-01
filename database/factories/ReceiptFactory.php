<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Solo para pruebas de lectura; los cobros reales se crean con PaymentService.
 *
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'folio' => 'REC-'.fake()->unique()->numerify('9#######'),
            'merchant_id' => Merchant::factory(),
            'user_id' => User::factory(),
            'issued_at' => now(),
            'subtotal' => '40.00',
            'total' => '40.00',
            'payment_method' => PaymentMethod::Cash,
            'status' => ReceiptStatus::Issued,
        ];
    }
}
