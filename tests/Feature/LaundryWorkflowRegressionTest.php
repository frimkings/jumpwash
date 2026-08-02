<?php

namespace Tests\Feature;

use App\Livewire\CustomersManager;
use App\Livewire\DeliveryManagement;
use App\Livewire\GarmentTaggingManager;
use App\Livewire\AccessControlManager;
use App\Livewire\BackupManager;
use App\Livewire\OrdersManager;
use App\Livewire\PaymentsManager;
use App\Livewire\PickupManagement;
use App\Livewire\RateChartManager;
use App\Models\BackupRecord;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\GarmentTag;
use App\Models\LaundryService;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PickupDeliveryTask;
use App\Models\Product;
use App\Models\RateChart;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\ReportBuilder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaundryWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_route_alias_renders_subscription_packages_page(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        LaundryService::factory()->create(['branch_id' => $branch->id, 'name' => 'Monthly Wash']);

        $this->get('/plan')
            ->assertOk()
            ->assertSee('Subscription Packages');
    }

    public function test_order_creation_uses_expected_number_format_and_totals(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        [$product, $service] = $this->pricedProductService($branch, 12.50);

        $component = Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->set('customer_id', (string) $customer->id)
            ->set('status', 'received')
            ->set('rows', [[
                'id' => null,
                'product_id' => (string) $product->id,
                'laundry_service_id' => (string) $service->id,
                'quantity' => 2,
                'unit_price' => 12.50,
                'original_unit_price' => 12.50,
                'price_override_enabled' => false,
                'price_override_reason' => '',
                'amount' => 25,
                'status' => 'received',
            ]])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('activeTab', 'queue');

        $order = Order::query()->firstOrFail();

        $component
            ->assertSet('selectedQueueOrderId', $order->id)
            ->assertSet('activeModal', 'payment')
            ->assertSet('modalOrderId', $order->id)
            ->assertSet('createdPreviewOrderId', null)
            ->assertSee($order->order_number);

        $this->assertMatchesRegularExpression('/^JW-'.now()->format('Ymd').'-0001$/', $order->order_number);
        $this->assertSame('25.00', (string) $order->total_amount);
        $this->assertSame('25.00', (string) $order->balance);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'laundry_service_id' => $service->id,
            'quantity' => 2,
            'unit_price' => 12.50,
            'line_total' => 25,
        ]);
    }

    public function test_order_creation_validation_reports_missing_required_fields(): void
    {
        [, $user] = $this->actingSuperAdmin();

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->set('rows', [[
                'id' => null,
                'product_id' => '',
                'laundry_service_id' => '',
                'quantity' => 0,
                'unit_price' => 0,
                'original_unit_price' => null,
                'price_override_enabled' => false,
                'price_override_reason' => '',
                'amount' => 0,
                'status' => 'received',
            ]])
            ->call('save')
            ->assertHasErrors([
                'customer_id' => 'required',
                'rows.0.product_id' => 'required',
                'rows.0.laundry_service_id' => 'required',
                'rows.0.quantity' => 'min',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_creation_rejects_product_service_without_rate_price(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $product = Product::create([
            'branch_id' => $branch->id,
            'name' => 'Unpriced Coat',
            'description' => 'No rate chart entry',
            'is_active' => true,
        ]);
        $service = LaundryService::create([
            'branch_id' => $branch->id,
            'name' => 'Unpriced Dry Clean',
            'code' => 'UNPRICED-DRY-CLEAN',
            'description' => 'No rate chart entry',
            'price' => 0,
            'tax_percentage' => 0,
            'unit' => 'piece',
            'turnaround_hours' => 24,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->set('customer_id', (string) $customer->id)
            ->set('rows', [[
                'id' => null,
                'product_id' => (string) $product->id,
                'laundry_service_id' => (string) $service->id,
                'quantity' => 1,
                'unit_price' => 0,
                'original_unit_price' => null,
                'price_override_enabled' => false,
                'price_override_reason' => '',
                'amount' => 0,
                'status' => 'received',
            ]])
            ->call('save')
            ->assertHasErrors(['rows.0.unit_price']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_subscription_order_consumes_usage_and_zeroes_payable_balance(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        [$product, $service] = $this->pricedProductService($branch, 12.50);
        $plan = SubscriptionPlan::create([
            'branch_id' => $branch->id,
            'code' => 'SUB-PKG-ORDER-0001',
            'name' => 'Order Subscription',
            'laundry_service_id' => $service->id,
            'billing_cycle' => 'monthly',
            'price' => 100,
            'validity_months' => 1,
            'usage_limit' => 5,
            'pickup_included' => false,
            'amount' => 100,
            'is_active' => true,
        ]);
        $subscription = CustomerSubscription::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'subscription_no' => 'SUB-ORDER-0001',
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->addMonth()->toDateString(),
            'status' => 'active',
            'allowance' => ['limit' => 5, 'used' => 0, 'remaining' => 5],
        ]);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->set('customer_id', (string) $customer->id)
            ->set('useSubscription', true)
            ->set('customer_subscription_id', (string) $subscription->id)
            ->set('rows', [[
                'id' => null,
                'product_id' => (string) $product->id,
                'laundry_service_id' => (string) $service->id,
                'quantity' => 2,
                'unit_price' => 12.50,
                'original_unit_price' => 12.50,
                'price_override_enabled' => false,
                'price_override_reason' => '',
                'amount' => 25,
                'status' => 'received',
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame('subscription', $order->billing_source);
        $this->assertSame($subscription->id, (int) $order->customer_subscription_id);
        $this->assertSame(25.0, (float) $order->discount);
        $this->assertSame('0.00', (string) $order->total_amount);
        $this->assertSame('0.00', (string) $order->balance);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(3, $subscription->fresh()->remainingUses());
    }

    public function test_subscription_order_rejects_wrong_service_or_insufficient_uses(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        [$product, $coveredService] = $this->pricedProductService($branch, 10);
        [, $otherService] = $this->pricedProductService($branch, 20);
        RateChart::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'laundry_service_id' => $otherService->id,
            'price' => 20,
        ]);
        $plan = SubscriptionPlan::create([
            'branch_id' => $branch->id,
            'code' => 'SUB-PKG-ORDER-0002',
            'name' => 'Limited Subscription',
            'laundry_service_id' => $coveredService->id,
            'billing_cycle' => 'monthly',
            'price' => 50,
            'validity_months' => 1,
            'usage_limit' => 1,
            'pickup_included' => false,
            'amount' => 50,
            'is_active' => true,
        ]);
        $subscription = CustomerSubscription::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'subscription_no' => 'SUB-ORDER-0002',
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->addMonth()->toDateString(),
            'status' => 'active',
            'allowance' => ['limit' => 1, 'used' => 0, 'remaining' => 1],
        ]);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->set('customer_id', (string) $customer->id)
            ->set('useSubscription', true)
            ->set('customer_subscription_id', (string) $subscription->id)
            ->set('rows', [[
                'id' => null,
                'product_id' => (string) $product->id,
                'laundry_service_id' => (string) $otherService->id,
                'quantity' => 1,
                'unit_price' => 20,
                'original_unit_price' => 20,
                'price_override_enabled' => false,
                'price_override_reason' => '',
                'amount' => 20,
                'status' => 'received',
            ]])
            ->call('save')
            ->assertHasErrors(['customer_subscription_id']);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->set('customer_id', (string) $customer->id)
            ->set('useSubscription', true)
            ->set('customer_subscription_id', (string) $subscription->id)
            ->set('rows', [[
                'id' => null,
                'product_id' => (string) $product->id,
                'laundry_service_id' => (string) $coveredService->id,
                'quantity' => 2,
                'unit_price' => 10,
                'original_unit_price' => 10,
                'price_override_enabled' => false,
                'price_override_reason' => '',
                'amount' => 20,
                'status' => 'received',
            ]])
            ->call('save')
            ->assertHasErrors(['customer_subscription_id']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $subscription->fresh()->remainingUses());
    }

    public function test_price_override_requires_rate_chart_permission(): void
    {
        [$branch, $user] = $this->actingRole('Cashier');
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        [$product, $service] = $this->pricedProductService($branch, 15);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->set('customer_id', (string) $customer->id)
            ->set('rows', [[
                'id' => null,
                'product_id' => (string) $product->id,
                'laundry_service_id' => (string) $service->id,
                'quantity' => 1,
                'unit_price' => 10,
                'original_unit_price' => 15,
                'price_override_enabled' => true,
                'price_override_reason' => 'Discount request',
                'amount' => 10,
                'status' => 'received',
            ]])
            ->call('save')
            ->assertSet('rows.0.unit_price', 15.0);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_rate_chart_completeness_checker_flags_and_fixes_missing_prices(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $product = Product::create([
            'branch_id' => $branch->id,
            'name' => 'Completeness Coat',
            'description' => 'Completeness checker garment',
            'is_active' => true,
        ]);
        $missingService = LaundryService::create([
            'branch_id' => $branch->id,
            'name' => 'Completeness Wash',
            'code' => 'COMPLETENESS-WASH',
            'description' => 'Missing rate service',
            'price' => 0,
            'tax_percentage' => 0,
            'unit' => 'piece',
            'turnaround_hours' => 24,
            'is_active' => true,
        ]);
        $zeroService = LaundryService::create([
            'branch_id' => $branch->id,
            'name' => 'Completeness Press',
            'code' => 'COMPLETENESS-PRESS',
            'description' => 'Zero rate service',
            'price' => 0,
            'tax_percentage' => 0,
            'unit' => 'piece',
            'turnaround_hours' => 24,
            'is_active' => true,
        ]);

        RateChart::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'laundry_service_id' => $zeroService->id,
            'price' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(RateChartManager::class)
            ->assertSee('Rate Completeness')
            ->assertSee('Completeness Wash')
            ->assertSee('Missing rate')
            ->assertSee('Completeness Press')
            ->assertSee('Zero price')
            ->call('selectMissingRate', $product->id, $missingService->id)
            ->assertSet('product_id', (string) $product->id)
            ->assertSet('laundry_service_id', (string) $missingService->id)
            ->set('price', '14.75')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('rate_charts', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'laundry_service_id' => $missingService->id,
            'price' => 14.75,
        ]);

        Livewire::actingAs($user)
            ->test(RateChartManager::class)
            ->assertDontSee('Missing rate')
            ->assertSee('Zero price');
    }

    public function test_rate_chart_rejects_zero_price_rates(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        [$product, $service] = $this->pricedProductService($branch, 5);

        Livewire::actingAs($user)
            ->test(RateChartManager::class)
            ->set('product_id', (string) $product->id)
            ->set('laundry_service_id', (string) $service->id)
            ->set('price', '0')
            ->call('save')
            ->assertHasErrors(['price' => 'min']);
    }

    public function test_payment_recording_updates_order_balance_and_status(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $this->configureLoyalty($branch);
        $order = $this->orderWithItem($branch, total: 100);

        Livewire::actingAs($user)
            ->test(PaymentsManager::class)
            ->set('selectedOrderId', $order->id)
            ->set('amount', '40.00')
            ->set('payment_method', 'cash')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'amount' => 40,
            'payment_method' => 'cash',
        ]);

        $order->refresh();

        $this->assertSame('40.00', (string) $order->amount_paid);
        $this->assertSame('60.00', (string) $order->balance);
        $this->assertSame('part_paid', $order->payment_status);
        $this->assertSame(4, (int) $order->customer->fresh()->loyalty_points);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $order->customer_id,
            'payment_id' => Payment::query()->firstOrFail()->id,
            'type' => LoyaltyTransaction::TYPE_EARNED,
            'points' => 4,
            'balance_after' => 4,
        ]);
    }

    public function test_order_payment_modal_redeems_loyalty_points_and_earns_on_cash_only(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $this->configureLoyalty($branch, spendPerPoint: '10', valuePerPoint: '0.50', minimum: '10');
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'loyalty_points' => 20,
        ]);
        $order = $this->orderWithItem($branch, customer: $customer, total: 20);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->call('openPaymentModal', $order->id)
            ->set('loyaltyRedeemPoints', '10')
            ->set('paymentLines', [[
                'amount' => '15.00',
                'method' => 'cash',
                'reference' => 'LOYALTY-CASH',
            ]])
            ->call('recordModalPayment')
            ->assertHasNoErrors();

        $order->refresh();
        $customer->refresh();

        $this->assertSame('20.00', (string) $order->amount_paid);
        $this->assertSame('0.00', (string) $order->balance);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(11, (int) $customer->loyalty_points);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'loyalty_credit',
            'amount' => 5,
        ]);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => LoyaltyTransaction::TYPE_REDEEMED,
            'points' => -10,
            'money_value' => 5,
        ]);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => LoyaltyTransaction::TYPE_EARNED,
            'points' => 1,
            'balance_after' => 11,
        ]);
    }

    public function test_order_payment_modal_allows_loyalty_only_redemption(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $this->configureLoyalty($branch, spendPerPoint: '10', valuePerPoint: '1.00', minimum: '10');
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'loyalty_points' => 20,
        ]);
        $order = $this->orderWithItem($branch, customer: $customer, total: 10);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->call('openPaymentModal', $order->id)
            ->set('loyaltyRedeemPoints', '10')
            ->set('paymentLines', [[
                'amount' => '0.00',
                'method' => 'cash',
                'reference' => '',
            ]])
            ->call('recordModalPayment')
            ->assertHasNoErrors();

        $order->refresh();
        $customer->refresh();

        $this->assertSame('10.00', (string) $order->amount_paid);
        $this->assertSame('0.00', (string) $order->balance);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(10, (int) $customer->loyalty_points);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'loyalty_credit',
            'amount' => 10,
        ]);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => LoyaltyTransaction::TYPE_REDEEMED,
            'points' => -10,
            'balance_after' => 10,
        ]);
    }

    public function test_payment_validation_prevents_overpaying_balance(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $order = $this->orderWithItem($branch, total: 100);

        Livewire::actingAs($user)
            ->test(PaymentsManager::class)
            ->set('selectedOrderId', $order->id)
            ->set('amount', '101.00')
            ->set('payment_method', 'cash')
            ->call('recordPayment')
            ->assertHasErrors(['amount' => 'max']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('100.00', (string) $order->fresh()->balance);
    }

    public function test_payment_correction_refund_updates_order_balance_without_editing_original_payment(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $order = $this->orderWithItem($branch, total: 100, overrides: [
            'amount_paid' => 60,
            'balance' => 40,
            'payment_status' => 'part_paid',
        ]);
        $originalPayment = Payment::create([
            'branch_id' => $branch->id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'payment_number' => 'JW-PAY-REFUND-0001',
            'receipt_no' => 'JW-PAY-REFUND-0001',
            'method' => 'cash',
            'payment_method' => 'cash',
            'status' => 'settled',
            'amount' => 60,
            'paid_at' => now(),
            'received_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->call('openAdjustmentModal', $order->id)
            ->set('adjustment_type', 'refund')
            ->set('adjustment_amount', '25.00')
            ->set('adjustment_reason', 'Customer overpaid and received cash refund')
            ->call('recordPaymentCorrection')
            ->assertHasNoErrors();

        $order->refresh();
        $originalPayment->refresh();

        $this->assertSame('60.00', (string) $originalPayment->amount);
        $this->assertSame('35.00', (string) $order->amount_paid);
        $this->assertSame('65.00', (string) $order->balance);
        $this->assertSame('part_paid', $order->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'refund',
            'amount' => -25,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'payment.correction_recorded',
            'subject_type' => Payment::class,
        ]);
    }

    public function test_loyalty_adjustment_requires_reason_and_cannot_make_negative_balance(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'loyalty_points' => 10,
        ]);

        Livewire::actingAs($user)
            ->test(CustomersManager::class)
            ->call('selectCustomer', $customer->id)
            ->set('loyalty_adjustment_points', '-15')
            ->set('loyalty_adjustment_reason', 'Counter correction')
            ->call('adjustLoyaltyPoints')
            ->assertHasErrors(['loyalty_adjustment_points']);

        $this->assertSame(10, (int) $customer->fresh()->loyalty_points);

        Livewire::actingAs($user)
            ->test(CustomersManager::class)
            ->call('selectCustomer', $customer->id)
            ->set('loyalty_adjustment_points', '5')
            ->set('loyalty_adjustment_reason', 'Manager goodwill credit')
            ->call('adjustLoyaltyPoints')
            ->assertHasNoErrors();

        $this->assertSame(15, (int) $customer->fresh()->loyalty_points);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'type' => LoyaltyTransaction::TYPE_ADJUSTED,
            'points' => 5,
            'balance_after' => 15,
            'notes' => 'Manager goodwill credit',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'loyalty.adjustment_recorded',
            'subject_type' => LoyaltyTransaction::class,
        ]);
    }

    public function test_order_payment_flow_opens_receipt_preview_after_recording_payment(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $order = $this->orderWithItem($branch, total: 100);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->call('openPaymentModal', $order->id)
            ->set('paymentLines', [[
                'amount' => '40.00',
                'method' => 'cash',
                'reference' => 'QA-ORDER-PAYMENT',
            ]])
            ->call('recordModalPayment')
            ->assertHasNoErrors()
            ->assertSet('activeModal', null)
            ->assertSet('createdPreviewOrderId', $order->id)
            ->assertSet('receiptPreviewContext', 'payment');

        $this->assertDatabaseHas('payments', [
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'amount' => 40,
            'payment_method' => 'cash',
            'reference' => 'QA-ORDER-PAYMENT',
        ]);
    }

    public function test_customer_create_edit_and_delete_guards_are_enforced(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();

        Livewire::actingAs($user)
            ->test(CustomersManager::class)
            ->set('full_name', 'Ama Mensah')
            ->set('phone', '0240000000')
            ->set('email', 'ama@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::query()->where('phone', '0240000000')->firstOrFail();

        Livewire::actingAs($user)
            ->test(CustomersManager::class)
            ->call('edit', $customer->id)
            ->set('full_name', 'Ama Owusu')
            ->set('phone', '0240000001')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'branch_id' => $branch->id,
            'name' => 'Ama Owusu',
            'phone' => '0240000001',
        ]);

        $this->orderWithItem($branch, customer: $customer->fresh());

        Livewire::actingAs($user)
            ->test(CustomersManager::class)
            ->call('delete', $customer->id);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_customer_save_cannot_update_another_branch_customer(): void
    {
        [, $user] = $this->actingSuperAdmin();
        $otherBranch = Branch::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'branch_id' => $otherBranch->id,
            'name' => 'Other Branch Customer',
            'phone' => '0550000000',
        ]);

        try {
            Livewire::actingAs($user)
                ->test(CustomersManager::class)
                ->set('editingId', $otherCustomer->id)
                ->set('full_name', 'Tampered Name')
                ->set('phone', '0559999999')
                ->call('save');

            $this->fail('Expected saving another branch customer to be blocked.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('customers', [
            'id' => $otherCustomer->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Other Branch Customer',
            'phone' => '0550000000',
        ]);
    }

    public function test_pickup_and_delivery_statuses_sync_linked_order(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $order = $this->orderWithItem($branch, total: 100, overrides: [
            'status' => 'pending_pickup',
            'payment_status' => 'paid',
            'amount_paid' => 100,
            'balance' => 0,
        ]);

        $pickup = PickupDeliveryTask::create([
            'branch_id' => $branch->id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'type' => 'door_pickup',
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'address' => $order->customer->address,
        ]);

        Livewire::actingAs($user)
            ->test(PickupManagement::class)
            ->call('setStatus', $pickup->id, 'completed');

        $this->assertSame('received', $order->fresh()->status);

        $delivery = PickupDeliveryTask::create([
            'branch_id' => $branch->id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'type' => 'door_delivery',
            'status' => 'pending',
            'scheduled_at' => now(),
            'address' => $order->customer->address,
        ]);

        Livewire::actingAs($user)
            ->test(DeliveryManagement::class)
            ->call('setStatus', $delivery->id, 'out_for_delivery')
            ->call('setStatus', $delivery->id, 'delivered');

        $order->refresh();

        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_paid_orders_cannot_be_edited(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $order = $this->orderWithItem($branch, total: 100, overrides: [
            'amount_paid' => 40,
            'balance' => 60,
            'payment_status' => 'part_paid',
        ]);
        Payment::create([
            'branch_id' => $branch->id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'payment_number' => 'JW-PAY-LOCK-0001',
            'receipt_no' => 'JW-PAY-LOCK-0001',
            'method' => 'cash',
            'payment_method' => 'cash',
            'status' => 'settled',
            'amount' => 40,
            'paid_at' => now(),
            'received_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(OrdersManager::class)
            ->call('edit', $order->id)
            ->assertSet('editingId', null);
    }

    public function test_orders_with_payments_tags_or_tasks_cannot_be_deleted(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $paymentOrder = $this->orderWithItem($branch, total: 50);
        $taggedOrder = $this->orderWithItem($branch, total: 50);
        $taskedOrder = $this->orderWithItem($branch, total: 50);

        Payment::create([
            'branch_id' => $branch->id,
            'customer_id' => $paymentOrder->customer_id,
            'order_id' => $paymentOrder->id,
            'payment_number' => 'JW-PAY-DEL-0001',
            'receipt_no' => 'JW-PAY-DEL-0001',
            'method' => 'cash',
            'payment_method' => 'cash',
            'status' => 'settled',
            'amount' => 10,
            'paid_at' => now(),
            'received_by' => $user->id,
        ]);
        GarmentTag::create([
            'order_id' => $taggedOrder->id,
            'tag_code' => 'TAG-DELETE-000001',
            'barcode_payload' => 'TAG-DELETE-000001',
            'status' => 'received',
            'is_scanned' => false,
        ]);
        PickupDeliveryTask::create([
            'branch_id' => $branch->id,
            'customer_id' => $taskedOrder->customer_id,
            'order_id' => $taskedOrder->id,
            'type' => 'door_pickup',
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'address' => $taskedOrder->customer->address,
        ]);

        foreach ([$paymentOrder, $taggedOrder, $taskedOrder] as $order) {
            Livewire::actingAs($user)
                ->test(OrdersManager::class)
                ->call('delete', $order->id);

            $this->assertDatabaseHas('orders', ['id' => $order->id]);
        }
    }

    public function test_delivery_cannot_complete_order_with_balance(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $order = $this->orderWithItem($branch, total: 100);
        $delivery = PickupDeliveryTask::create([
            'branch_id' => $branch->id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'type' => 'door_delivery',
            'status' => 'out_for_delivery',
            'scheduled_at' => now(),
            'address' => $order->customer->address,
        ]);

        Livewire::actingAs($user)
            ->test(DeliveryManagement::class)
            ->call('setStatus', $delivery->id, 'delivered');

        $this->assertSame('out_for_delivery', $delivery->fresh()->status);
        $this->assertNotSame('delivered', $order->fresh()->status);
    }

    public function test_garment_tags_can_be_generated_scanned_and_closed(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $order = $this->orderWithItem($branch, total: 30, quantity: 2);
        $item = $order->items()->firstOrFail();

        Livewire::actingAs($user)
            ->test(GarmentTaggingManager::class)
            ->set('order_id', (string) $order->id)
            ->set('expected_garment_count', '2')
            ->set('tagRows', [[
                'order_item_id' => $item->id,
                'garment_type' => 'Shirt',
                'quantity' => 2,
                'color' => 'Blue',
                'brand' => '',
                'size' => '',
                'gender' => '',
                'condition' => 'Good',
            ]])
            ->call('generateTags')
            ->assertHasNoErrors();

        $tags = GarmentTag::query()->where('order_id', $order->id)->orderBy('id')->get();

        $this->assertCount(2, $tags);
        $this->assertSame(2, (int) $order->fresh()->expected_garment_count);

        foreach ($tags as $tag) {
            Livewire::actingAs($user)
                ->test(GarmentTaggingManager::class)
                ->set('scan_code', $tag->tag_code)
                ->call('scanTag')
                ->call('updateScannedStatus', 'washing')
                ->call('updateScannedStatus', 'drying')
                ->call('updateScannedStatus', 'ironing')
                ->call('updateScannedStatus', 'packaging')
                ->call('updateScannedStatus', 'ready');
        }

        Livewire::actingAs($user)
            ->test(GarmentTaggingManager::class)
            ->set('order_id', (string) $order->id)
            ->call('closeOrder');

        $order->refresh();

        $this->assertNotNull($order->garment_closed_at);
        $this->assertSame('ready', $order->status);
        $this->assertSame(2, GarmentTag::query()->where('order_id', $order->id)->where('is_scanned', true)->count());
    }

    public function test_receipt_renders_order_payment_and_balance_details(): void
    {
        [$branch, $user] = $this->actingSuperAdmin();
        $order = $this->orderWithItem($branch, total: 80, overrides: [
            'payment_status' => 'part_paid',
            'amount_paid' => 30,
            'balance' => 50,
        ]);

        $paymentData = [
            'branch_id' => $branch->id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'payment_number' => 'JW-PAY-TEST-0001',
            'amount' => 30,
            'received_by' => $user->id,
            'method' => 'cash',
            'payment_method' => 'cash',
            'status' => 'settled',
            'paid_at' => now(),
        ];

        if (Schema::hasColumn('payments', 'receipt_number')) {
            $paymentData['receipt_number'] = 'JW-PAY-TEST-0001';
        }

        if (Schema::hasColumn('payments', 'receipt_no')) {
            $paymentData['receipt_no'] = 'JW-PAY-TEST-0001';
        }

        Payment::create($paymentData);

        $this->get(route('receipts.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('JW-PAY-TEST-0001')
            ->assertSee('GHS 80.00')
            ->assertSee('GHS 30.00')
            ->assertSee('GHS 50.00')
            ->assertSee('Part Paid');
    }

    public function test_report_builder_filters_by_branch_and_calculates_sales_totals(): void
    {
        [$branch] = $this->actingSuperAdmin();
        $otherBranch = Branch::factory()->create();
        $included = $this->orderWithItem($branch, total: 80, overrides: [
            'amount_paid' => 50,
            'balance' => 30,
            'payment_status' => 'part_paid',
        ]);
        $this->orderWithItem($otherBranch, total: 999);

        $report = app(ReportBuilder::class)->build('sales', 'daily', null, null, $branch->id);

        $this->assertSame('Sales Report', $report['title']);
        $this->assertSame(1, $report['summary']['records']);
        $this->assertSame($included->order_number, $report['rows'][0][0]);
        $this->assertSame(80.0, $report['summary']['totals'][4]);
        $this->assertSame(50.0, $report['summary']['totals'][5]);
        $this->assertSame(30.0, $report['summary']['totals'][6]);
    }

    public function test_subscription_report_includes_usage_amount_and_remaining_counts(): void
    {
        [$branch] = $this->actingSuperAdmin();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        [, $service] = $this->pricedProductService($branch, 10);
        $plan = SubscriptionPlan::create([
            'branch_id' => $branch->id,
            'code' => 'SUB-PKG-REPORT-0001',
            'name' => 'Report Subscription',
            'laundry_service_id' => $service->id,
            'billing_cycle' => 'monthly',
            'price' => 120,
            'validity_months' => 1,
            'usage_limit' => 6,
            'pickup_included' => false,
            'amount' => 120,
            'is_active' => true,
        ]);

        CustomerSubscription::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'subscription_no' => 'SUB-REPORT-0001',
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->toDateString(),
            'status' => 'active',
            'auto_renew' => true,
            'allowance' => ['limit' => 6, 'used' => 2, 'remaining' => 4],
        ]);

        $report = app(ReportBuilder::class)->build('subscriptions', 'monthly', null, null, $branch->id);

        $this->assertSame('Subscription Report', $report['title']);
        $this->assertSame(['Customer', 'Package', 'Status', 'Amount', 'Limit', 'Used', 'Remaining', 'Auto Renew', 'Start', 'Expiry'], $report['headings']);
        $this->assertSame($customer->name, $report['rows'][0][0]);
        $this->assertSame('Report Subscription', $report['rows'][0][1]);
        $this->assertSame(120.0, $report['rows'][0][3]);
        $this->assertSame(6, $report['rows'][0][4]);
        $this->assertSame(2, $report['rows'][0][5]);
        $this->assertSame(4, $report['rows'][0][6]);
        $this->assertSame('Yes', $report['rows'][0][7]);
    }

    public function test_report_export_validation_rejects_invalid_type(): void
    {
        $this->actingSuperAdmin();

        $this->get(route('reports.export', ['format' => 'excel', 'type' => 'not-real', 'period' => 'daily']))
            ->assertSessionHasErrors('type');
    }

    public function test_backup_requires_external_target_path_and_creates_local_record(): void
    {
        [, $user] = $this->actingSuperAdmin();
        Storage::fake('local');

        Livewire::actingAs($user)
            ->test(BackupManager::class)
            ->set('target', 'usb')
            ->set('target_path', '')
            ->call('createBackup')
            ->assertHasErrors(['target_path']);

        Livewire::actingAs($user)
            ->test(BackupManager::class)
            ->set('type', 'database')
            ->set('target', 'local')
            ->set('mode', 'manual')
            ->call('createBackup')
            ->assertHasNoErrors();

        $record = BackupRecord::query()->firstOrFail();

        $this->assertSame('database', $record->type);
        $this->assertSame('local', $record->target);
        $this->assertSame('completed', $record->status);
        Storage::disk('local')->assertExists($record->file_path);
    }

    public function test_role_permissions_limit_module_access_by_user_type(): void
    {
        [, $cashier] = $this->actingRole('Cashier');

        $this->actingAs($cashier)->get('/orders')->assertOk();
        $this->actingAs($cashier)->get('/payments')->assertOk();
        $this->actingAs($cashier)->get('/reports')->assertForbidden();
        $this->actingAs($cashier)->get('/settings')->assertForbidden();

        [, $deliveryStaff] = $this->actingRole('Delivery Staff');

        $this->actingAs($deliveryStaff)->get('/deliveries')->assertOk();
        $this->actingAs($deliveryStaff)->get('/orders')->assertForbidden();
        $this->actingAs($deliveryStaff)->get('/payments')->assertForbidden();
    }

    public function test_seeded_super_admin_can_login_and_reach_protected_modules(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(UserSeeder::class);

        $user = User::query()->where('email', 'superadmin@jumpwash.test')->firstOrFail();

        $this->post('/login', [
            'email' => 'superadmin@jumpwash.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get('/settings')->assertOk();
        $this->get('/reports')->assertOk();
    }

    public function test_access_control_page_manages_roles_permissions_and_user_assignments(): void
    {
        [$branch, $admin] = $this->actingSuperAdmin();
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Access Test User',
            'email' => 'access-user@jumpwash.test',
            'is_active' => true,
        ]);

        $this->get('/access-control')
            ->assertOk()
            ->assertSee('Access Control')
            ->assertSee('Users')
            ->assertSee('Roles')
            ->assertSee('Permissions');

        Livewire::actingAs($admin)
            ->test(AccessControlManager::class)
            ->set('activeTab', 'permissions')
            ->set('permissionName', 'quality.review')
            ->call('savePermission')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('permissions', ['name' => 'quality.review', 'guard_name' => 'web']);

        Livewire::actingAs($admin)
            ->test(AccessControlManager::class)
            ->set('activeTab', 'roles')
            ->set('roleName', 'Quality Reviewer')
            ->set('rolePermissions', ['dashboard.view', 'quality.review'])
            ->call('saveRole')
            ->assertHasNoErrors();

        $role = Role::query()->where('name', 'Quality Reviewer')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('quality.review'));

        Livewire::actingAs($admin)
            ->test(AccessControlManager::class)
            ->call('editUser', $user->id)
            ->set('userRoles', ['Quality Reviewer'])
            ->call('saveUser')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertTrue($user->hasRole('Quality Reviewer'));
        $this->assertTrue($user->can('quality.review'));
    }

    public function test_access_control_protects_core_super_admin_and_route_permissions(): void
    {
        [, $admin] = $this->actingSuperAdmin();
        $superAdminRole = Role::query()->where('name', 'Super Admin')->firstOrFail();
        $settingsPermission = Permission::query()->where('name', 'settings.manage')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(AccessControlManager::class)
            ->call('deleteRole', $superAdminRole->id)
            ->assertSee('The Super Admin role cannot be deleted.');

        $this->assertDatabaseHas('roles', ['id' => $superAdminRole->id, 'name' => 'Super Admin']);

        Livewire::actingAs($admin)
            ->test(AccessControlManager::class)
            ->call('deletePermission', $settingsPermission->id)
            ->assertSee('Core permissions used by routes cannot be deleted.');

        $this->assertDatabaseHas('permissions', ['id' => $settingsPermission->id, 'name' => 'settings.manage']);
    }

    public function test_staff_page_is_removed_in_favor_of_access_control(): void
    {
        $this->actingSuperAdmin();

        $this->get('/staff')->assertNotFound();
        $this->get('/access-control')->assertOk()->assertSee('Access Control');
    }

    private function actingSuperAdmin(): array
    {
        return $this->actingRole('Super Admin');
    }

    private function actingRole(string $role): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return [$branch, $user];
    }

    private function pricedProductService(Branch $branch, float $price): array
    {
        $product = Product::create([
            'branch_id' => $branch->id,
            'name' => 'Test Shirt '.fake()->unique()->numberBetween(1000, 9999),
            'description' => 'Test garment',
            'is_active' => true,
        ]);
        $service = LaundryService::create([
            'branch_id' => $branch->id,
            'name' => 'Test Laundry '.fake()->unique()->numberBetween(1000, 9999),
            'code' => 'TEST-LAUNDRY-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => 'Test service',
            'price' => $price,
            'tax_percentage' => 0,
            'unit' => 'piece',
            'turnaround_hours' => 24,
            'is_active' => true,
        ]);

        RateChart::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'laundry_service_id' => $service->id,
            'price' => $price,
        ]);

        return [$product, $service];
    }

    private function orderWithItem(Branch $branch, ?Customer $customer = null, float $total = 50, int $quantity = 1, array $overrides = []): Order
    {
        $customer ??= Customer::factory()->create(['branch_id' => $branch->id]);
        [$product, $service] = $this->pricedProductService($branch, $total / max($quantity, 1));

        $order = Order::factory()->create(array_merge([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'order_number' => 'JW-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'status' => 'received',
            'payment_status' => 'unpaid',
            'subtotal' => $total,
            'total' => $total,
            'total_amount' => $total,
            'amount_paid' => 0,
            'balance' => $total,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'laundry_service_id' => $service->id,
            'rate_chart_id' => RateChart::where('product_id', $product->id)->where('laundry_service_id', $service->id)->value('id'),
            'item_name' => $product->name.' + '.$service->name,
            'quantity' => $quantity,
            'unit_price' => $total / max($quantity, 1),
            'line_total' => $total,
            'tax_amount' => 0,
            'status' => $order->status,
        ]);

        return $order->load(['customer', 'items']);
    }

    private function configureLoyalty(Branch $branch, string $spendPerPoint = '10', string $valuePerPoint = '0.10', string $minimum = '10'): void
    {
        foreach ([
            'loyalty_enabled' => '1',
            'loyalty_spend_per_point' => $spendPerPoint,
            'loyalty_value_per_point' => $valuePerPoint,
            'loyalty_min_redeem_points' => $minimum,
        ] as $key => $value) {
            Setting::updateOrCreate(
                ['branch_id' => $branch->id, 'key' => $key],
                ['value' => $value, 'type' => 'number'],
            );
        }
    }
}
