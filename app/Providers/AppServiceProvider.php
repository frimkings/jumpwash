<?php

namespace App\Providers;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Customer;
use App\Models\GarmentTag;
use App\Models\Order;
use App\Models\Payment;
use App\Policies\CustomerPolicy;
use App\Policies\GarmentTagPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Repositories\EloquentCustomerRepository;
use App\Repositories\EloquentOrderRepository;
use App\Repositories\EloquentPaymentRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CustomerRepositoryInterface::class, EloquentCustomerRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(GarmentTag::class, GarmentTagPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
    }
}
