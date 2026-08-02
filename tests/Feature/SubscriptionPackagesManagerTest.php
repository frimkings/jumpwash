<?php

namespace Tests\Feature;

use App\Livewire\SubscriptionPackagesManager;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\LaundryService;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionPackagesManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_page_renders_packages_and_searches_by_service(): void
    {
        [$branch, $user] = $this->userForBranch();
        $service = LaundryService::factory()->create(['branch_id' => $branch->id, 'name' => 'Blanket Cleaning']);
        $this->package($branch, $service, ['name' => 'Family Bundle']);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->set('search', 'Blanket')
            ->assertSee('Family Bundle')
            ->assertSee('Blanket Cleaning');
    }

    public function test_can_create_subscription_package(): void
    {
        [$branch, $user] = $this->userForBranch();
        $service = LaundryService::factory()->create(['branch_id' => $branch->id]);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->set('name', 'Monthly Home Care')
            ->set('laundry_service_id', (string) $service->id)
            ->set('validity_months', '2')
            ->set('usage_limit', '12')
            ->set('pickup_included', true)
            ->set('amount', '250')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subscription_plans', [
            'branch_id' => $branch->id,
            'laundry_service_id' => $service->id,
            'name' => 'Monthly Home Care',
            'billing_cycle' => 'monthly',
            'usage_limit' => 12,
            'validity_months' => 2,
            'pickup_included' => true,
            'amount' => 250,
            'price' => 250,
            'is_active' => true,
        ]);

        if (Schema::hasColumn('subscription_plans', 'wash_limit')) {
            $this->assertDatabaseHas('subscription_plans', ['name' => 'Monthly Home Care', 'wash_limit' => 12]);
        }

        if (Schema::hasColumn('subscription_plans', 'validity_days')) {
            $this->assertDatabaseHas('subscription_plans', ['name' => 'Monthly Home Care', 'validity_days' => 60]);
        }

        $this->assertNotNull(SubscriptionPlan::where('name', 'Monthly Home Care')->value('code'));
    }

    public function test_package_requires_service_usage_and_positive_amount(): void
    {
        [, $user] = $this->userForBranch();

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->set('name', 'Broken Package')
            ->set('laundry_service_id', '')
            ->set('validity_months', '1')
            ->set('usage_limit', '0')
            ->set('amount', '0')
            ->call('save')
            ->assertHasErrors([
                'laundry_service_id' => 'required',
                'usage_limit' => 'min',
                'amount' => 'min',
            ]);

        $this->assertDatabaseMissing('subscription_plans', ['name' => 'Broken Package']);
    }

    public function test_incomplete_legacy_package_is_flagged_and_cannot_be_enabled(): void
    {
        [$branch, $user] = $this->userForBranch();
        $package = SubscriptionPlan::create([
            'branch_id' => $branch->id,
            'code' => 'SUB-PKG-INCOMPLETE',
            'name' => 'Incomplete Legacy',
            'billing_cycle' => 'monthly',
            'price' => 0,
            'validity_months' => 1,
            'usage_limit' => 0,
            'pickup_included' => true,
            'amount' => 0,
            'is_active' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->assertSee('Incomplete Legacy')
            ->assertSee('Needs setup')
            ->assertSee('Service missing')
            ->assertSee('Usage missing')
            ->assertSee('Amount missing')
            ->call('toggleStatus', $package->id)
            ->assertSee('Complete the package service, usage limit, validity, and amount before enabling it.');

        $this->assertFalse($package->fresh()->is_active);
    }

    public function test_can_assign_renew_cancel_and_expire_customer_subscription(): void
    {
        [$branch, $user] = $this->userForBranch();
        $service = LaundryService::factory()->create(['branch_id' => $branch->id]);
        $package = $this->package($branch, $service, [
            'name' => 'Gold Care',
            'usage_limit' => 6,
            'validity_months' => 2,
            'amount' => 300,
            'price' => 300,
        ]);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->set('subscription_customer_id', (string) $customer->id)
            ->set('subscription_plan_id', (string) $package->id)
            ->set('subscription_starts_at', '2026-07-01')
            ->set('subscription_auto_renew', true)
            ->set('subscription_remarks', 'Paid at counter')
            ->call('assignSubscription')
            ->assertHasNoErrors()
            ->assertSee('Customer subscription assigned.');

        $subscription = CustomerSubscription::query()->firstOrFail();

        $this->assertSame('active', $subscription->status);
        $this->assertSame(6, $subscription->remainingUses());
        $this->assertSame('2026-09-01', $subscription->ends_at->toDateString());
        $this->assertTrue((bool) $subscription->auto_renew);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->call('cancelSubscription', $subscription->id)
            ->assertSee('Subscription cancelled.')
            ->call('renewSubscription', $subscription->id)
            ->assertSee('Subscription renewed.');

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertSame(6, $subscription->remainingUses());

        $subscription->update(['ends_at' => now()->subDay()->toDateString()]);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->call('expireDueSubscriptions')
            ->assertSee('1 expired subscription updated.');

        $this->assertSame('expired', $subscription->fresh()->status);
    }

    public function test_active_customer_subscriptions_prevent_disabling_package(): void
    {
        [$branch, $user] = $this->userForBranch();
        $service = LaundryService::factory()->create(['branch_id' => $branch->id]);
        $package = $this->package($branch, $service);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);

        CustomerSubscription::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'subscription_plan_id' => $package->id,
            'subscription_no' => 'SUB-TEST-0001',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->call('toggleStatus', $package->id);

        $this->assertTrue($package->fresh()->is_active);
    }

    public function test_assigned_package_cannot_be_deleted(): void
    {
        [$branch, $user] = $this->userForBranch();
        $service = LaundryService::factory()->create(['branch_id' => $branch->id]);
        $package = $this->package($branch, $service);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);

        CustomerSubscription::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'subscription_plan_id' => $package->id,
            'subscription_no' => 'SUB-TEST-0002',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'status' => 'expired',
        ]);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->call('delete', $package->id);

        $this->assertDatabaseHas('subscription_plans', ['id' => $package->id]);
    }

    public function test_can_edit_customer_subscription_with_reason_and_audit_log(): void
    {
        [$branch, $user] = $this->userForBranch();
        $service = LaundryService::factory()->create(['branch_id' => $branch->id]);
        $package = $this->package($branch, $service, ['name' => 'Care Plan', 'usage_limit' => 6]);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $subscription = CustomerSubscription::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'subscription_plan_id' => $package->id,
            'subscription_no' => 'SUB-EDIT-0001',
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-08-01',
            'status' => 'active',
            'auto_renew' => false,
            'allowance' => ['limit' => 6, 'used' => 1, 'remaining' => 5],
        ]);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->call('editSubscription', $subscription->id)
            ->set('edit_subscription_ends_at', '2026-08-15')
            ->set('edit_subscription_auto_renew', true)
            ->set('edit_subscription_used_uses', '2')
            ->set('edit_subscription_remaining_uses', '4')
            ->set('edit_subscription_adjustment_reason', 'Counter correction')
            ->set('edit_subscription_remarks', 'Updated after review')
            ->call('updateSubscription')
            ->assertHasNoErrors()
            ->assertSee('Subscription updated.');

        $subscription->refresh();

        $this->assertSame('2026-08-15', $subscription->ends_at->toDateString());
        $this->assertTrue((bool) $subscription->auto_renew);
        $this->assertSame('active', $subscription->status);
        $this->assertSame(6, $subscription->usageLimit());
        $this->assertSame(2, $subscription->usedUses());
        $this->assertSame(4, $subscription->remainingUses());
        $this->assertSame('Updated after review', $subscription->remarks);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'subscription.updated',
            'subject_id' => $subscription->id,
        ]);
    }

    public function test_subscription_edit_requires_reason_confirms_identity_change_and_exhausts_zero_remaining(): void
    {
        [$branch, $user] = $this->userForBranch();
        $service = LaundryService::factory()->create(['branch_id' => $branch->id]);
        $package = $this->package($branch, $service, ['name' => 'Original Plan', 'usage_limit' => 6]);
        $newPackage = $this->package($branch, $service, ['name' => 'Corrected Plan', 'usage_limit' => 6]);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $subscription = CustomerSubscription::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'subscription_plan_id' => $package->id,
            'subscription_no' => 'SUB-EDIT-0002',
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-08-01',
            'status' => 'active',
            'allowance' => ['limit' => 6, 'used' => 2, 'remaining' => 4],
        ]);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->call('editSubscription', $subscription->id)
            ->set('edit_subscription_used_uses', '3')
            ->set('edit_subscription_remaining_uses', '3')
            ->call('updateSubscription')
            ->assertHasErrors(['edit_subscription_adjustment_reason']);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->call('editSubscription', $subscription->id)
            ->set('edit_subscription_plan_id', (string) $newPackage->id)
            ->set('edit_subscription_adjustment_reason', 'Wrong package selected')
            ->call('updateSubscription')
            ->assertHasErrors(['edit_confirm_identity_change']);

        Livewire::actingAs($user)
            ->test(SubscriptionPackagesManager::class)
            ->call('editSubscription', $subscription->id)
            ->set('edit_subscription_plan_id', (string) $newPackage->id)
            ->set('edit_subscription_used_uses', '6')
            ->set('edit_subscription_remaining_uses', '0')
            ->set('edit_subscription_adjustment_reason', 'Move to corrected package and close allowance')
            ->set('edit_confirm_identity_change', true)
            ->call('updateSubscription')
            ->assertHasNoErrors();

        $subscription->refresh();

        $this->assertSame($newPackage->id, $subscription->subscription_plan_id);
        $this->assertSame('exhausted', $subscription->status);
        $this->assertSame(0, $subscription->remainingUses());
    }

    private function userForBranch(): array
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        return [$branch, $user];
    }

    private function package(Branch $branch, LaundryService $service, array $overrides = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'branch_id' => $branch->id,
            'code' => fake()->unique()->bothify('SUB-PKG-####'),
            'name' => 'Starter Package',
            'laundry_service_id' => $service->id,
            'billing_cycle' => 'monthly',
            'price' => 100,
            'validity_months' => 1,
            'usage_limit' => 4,
            'pickup_included' => false,
            'amount' => 100,
            'is_active' => true,
        ], $overrides));
    }
}
