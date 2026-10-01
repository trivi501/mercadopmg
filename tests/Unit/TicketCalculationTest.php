<?php

namespace Tests\Unit;

use App\Services\MerchantService;
use App\Services\PaymentService;
use PHPUnit\Framework\TestCase;

class TicketCalculationTest extends TestCase
{
    public function test_ticket_total_is_quantity_times_forty(): void
    {
        $this->assertSame('200.00', PaymentService::lineTotal(5, '40.00'));
        $this->assertSame('40.00', PaymentService::lineTotal(1, '40.00'));
        $this->assertSame('400.00', PaymentService::lineTotal(10, '40.00'));
    }

    public function test_decimal_arithmetic_is_exact(): void
    {
        $this->assertSame('0.30', PaymentService::lineTotal(3, '0.10'));
        $this->assertSame('465.00', PaymentService::lineTotal(3, '155.00'));
    }

    public function test_haversine_distance(): void
    {
        // ~1.11 km por cada 0.01° de latitud.
        $this->assertEqualsWithDelta(1.112, MerchantService::haversine(19.40, -99.10, 19.41, -99.10), 0.01);
        $this->assertSame(0.0, MerchantService::haversine(19.4, -99.1, 19.4, -99.1));
    }
}
