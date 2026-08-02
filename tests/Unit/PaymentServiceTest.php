<?php

namespace Tests\Unit;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Services\PaymentService;
use Mockery;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    public function test_payment_status_logic_matches_order_balance_rules(): void
    {
        $service = new PaymentService(Mockery::mock(PaymentRepositoryInterface::class));

        $this->assertSame('unpaid', $service->statusFor(500, 0));
        $this->assertSame('part_paid', $service->statusFor(500, 200));
        $this->assertSame('paid', $service->statusFor(500, 500));
        $this->assertSame('paid', $service->statusFor(500, 700));
    }
}
