<?php

namespace Tests\Unit;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Repositories\EloquentCustomerRepository;
use App\Repositories\EloquentOrderRepository;
use App\Repositories\EloquentPaymentRepository;
use Tests\TestCase;

class ArchitectureBindingsTest extends TestCase
{
    public function test_repository_contracts_resolve_to_eloquent_implementations(): void
    {
        $this->assertInstanceOf(EloquentCustomerRepository::class, $this->app->make(CustomerRepositoryInterface::class));
        $this->assertInstanceOf(EloquentOrderRepository::class, $this->app->make(OrderRepositoryInterface::class));
        $this->assertInstanceOf(EloquentPaymentRepository::class, $this->app->make(PaymentRepositoryInterface::class));
    }
}
