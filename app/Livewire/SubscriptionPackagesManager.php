<?php

namespace App\Livewire;

use App\Models\CustomerSubscription;
use App\Models\Customer;
use App\Models\ActivityLog;
use App\Models\LaundryService;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class SubscriptionPackagesManager extends Component
{
    public string $activeTab = 'packages';
    public bool $showPackageForm = false;
    public ?int $editingId = null;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $name = '';
    public string $laundry_service_id = '';
    public string $validity_months = '1';
    public string $usage_limit = '1';
    public bool $pickup_included = false;
    public string $amount = '';
    public bool $is_active = true;
    public string $subscription_customer_id = '';
    public string $subscription_plan_id = '';
    public string $subscription_starts_at = '';
    public bool $subscription_auto_renew = false;
    public string $subscription_remarks = '';
    public string $customerSearch = '';
    public string $packageSearch = '';
    public string $subscriptionSearch = '';
    public string $subscriptionFilter = 'active';
    public ?int $selectedSubscriptionId = null;
    public bool $showSubscriptionEditor = false;
    public ?int $editingSubscriptionId = null;
    public string $edit_subscription_customer_id = '';
    public string $edit_subscription_plan_id = '';
    public string $edit_subscription_starts_at = '';
    public string $edit_subscription_ends_at = '';
    public string $edit_subscription_status = 'active';
    public bool $edit_subscription_auto_renew = false;
    public string $edit_subscription_usage_limit = '0';
    public string $edit_subscription_used_uses = '0';
    public string $edit_subscription_remaining_uses = '0';
    public string $edit_subscription_remarks = '';
    public string $edit_subscription_adjustment_reason = '';
    public string $editCustomerSearch = '';
    public string $editPackageSearch = '';
    public bool $edit_confirm_identity_change = false;

    public function mount(): void
    {
        $this->subscription_starts_at = now()->toDateString();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'laundry_service_id' => ['required', 'exists:laundry_services,id'],
            'validity_months' => ['required', 'integer', 'min:1', 'max:120'],
            'usage_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'pickup_included' => ['boolean'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'is_active' => ['boolean'],
        ]);

        $branchId = auth()->user()?->branch_id;
        $existing = $this->editingId ? $this->packageQuery()->findOrFail($this->editingId) : null;

        $data = [
            'branch_id' => $branchId,
            'code' => $existing?->code ?? $this->nextPackageCode($branchId),
            'laundry_service_id' => $validated['laundry_service_id'],
            'name' => $validated['name'],
            'billing_cycle' => 'monthly',
            'validity_months' => $validated['validity_months'],
            'usage_limit' => $validated['usage_limit'],
            'pickup_included' => $validated['pickup_included'],
            'amount' => $validated['amount'],
            'price' => $validated['amount'],
            'is_active' => $validated['is_active'],
        ];

        if (Schema::hasColumn('subscription_plans', 'wash_limit')) {
            $data['wash_limit'] = $validated['usage_limit'];
        }

        if (Schema::hasColumn('subscription_plans', 'validity_days')) {
            $data['validity_days'] = ((int) $validated['validity_months']) * 30;
        }

        SubscriptionPlan::updateOrCreate(['id' => $this->editingId], $data);

        $this->resetForm();
        $this->showPackageForm = false;
        session()->flash('status', 'Subscription package saved.');
    }

    public function assignSubscription(): void
    {
        $validated = $this->validate([
            'subscription_customer_id' => ['required', 'exists:customers,id'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'subscription_starts_at' => ['required', 'date'],
            'subscription_auto_renew' => ['boolean'],
            'subscription_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Customer::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('is_active', true)
            ->findOrFail($validated['subscription_customer_id']);
        $package = $this->packageQuery()
            ->where('is_active', true)
            ->findOrFail($validated['subscription_plan_id']);

        if (! $this->packageIsComplete($package)) {
            $this->addError('subscription_plan_id', 'Complete the package before assigning it to a customer.');
            return;
        }

        $existingActive = CustomerSubscription::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('subscription_plan_id', $package->id)
            ->where('status', 'active')
            ->whereDate('ends_at', '>=', today())
            ->exists();

        if ($existingActive) {
            $this->addError('subscription_plan_id', 'Customer already has an active subscription for this package.');
            return;
        }

        $start = Carbon::parse($validated['subscription_starts_at']);
        $limit = max((int) ($package->usage_limit ?: $package->wash_limit), 1);
        $subscription = CustomerSubscription::create($this->subscriptionPayload($customer, $package, $start, $limit, [
            'auto_renew' => $validated['subscription_auto_renew'],
            'remarks' => $validated['subscription_remarks'] ?: null,
        ]));

        ActivityLog::record('subscription.assigned', $subscription, [
            'customer' => $customer->name,
            'package' => $package->name,
            'remaining' => $limit,
        ]);

        $this->activeTab = 'subscriptions';
        $this->selectedSubscriptionId = $subscription->id;
        $this->resetSubscriptionForm();
        session()->flash('status', 'Customer subscription assigned.');
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['packages', 'assign', 'subscriptions', 'expiring'], true)) {
            return;
        }

        $this->activeTab = $tab;

        if ($tab === 'expiring' && $this->subscriptionFilter === 'active') {
            $this->subscriptionFilter = 'due_30';
        }
    }

    public function openPackageForm(): void
    {
        $this->resetForm();
        $this->showPackageForm = true;
        $this->activeTab = 'packages';
    }

    public function cancelPackageForm(): void
    {
        $this->resetForm();
        $this->showPackageForm = false;
    }

    public function setSubscriptionFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'active', 'due_7', 'due_30', 'exhausted', 'cancelled_expired'], true)) {
            return;
        }

        $this->subscriptionFilter = $filter;
        $this->selectedSubscriptionId = null;
        $this->cancelSubscriptionEditor();
    }

    public function selectSubscription(int $id): void
    {
        $this->selectedSubscriptionId = $id;
        $this->cancelSubscriptionEditor();
    }

    public function selectSubscriptionCustomer(int $id): void
    {
        $customer = Customer::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('is_active', true)
            ->findOrFail($id);

        $this->subscription_customer_id = (string) $customer->id;
        $this->customerSearch = $customer->name;
    }

    public function clearSubscriptionCustomer(): void
    {
        $this->subscription_customer_id = '';
        $this->customerSearch = '';
    }

    public function selectSubscriptionPackage(int $id): void
    {
        $package = $this->packageQuery()
            ->where('is_active', true)
            ->with('service')
            ->findOrFail($id);

        if (! $this->packageIsComplete($package)) {
            $this->addError('subscription_plan_id', 'Complete the package before assigning it to a customer.');
            return;
        }

        $this->subscription_plan_id = (string) $package->id;
        $this->packageSearch = $package->name;
    }

    public function clearSubscriptionPackage(): void
    {
        $this->subscription_plan_id = '';
        $this->packageSearch = '';
    }

    public function editSubscription(int $id): void
    {
        $subscription = $this->subscriptionQuery()
            ->with(['customer', 'plan.service'])
            ->findOrFail($id);

        $this->activeTab = 'subscriptions';
        $this->selectedSubscriptionId = $subscription->id;
        $this->editingSubscriptionId = $subscription->id;
        $this->showSubscriptionEditor = true;
        $this->edit_subscription_customer_id = (string) $subscription->customer_id;
        $this->edit_subscription_plan_id = (string) $subscription->subscription_plan_id;
        $this->edit_subscription_starts_at = $subscription->starts_at?->toDateString() ?? now()->toDateString();
        $this->edit_subscription_ends_at = $subscription->ends_at?->toDateString() ?? now()->toDateString();
        $this->edit_subscription_status = $subscription->status ?: 'active';
        $this->edit_subscription_auto_renew = (bool) $subscription->auto_renew;
        $this->edit_subscription_usage_limit = (string) max($subscription->usageLimit(), 1);
        $this->edit_subscription_used_uses = (string) max($subscription->usedUses(), 0);
        $this->edit_subscription_remaining_uses = (string) max($subscription->remainingUses(), 0);
        $this->edit_subscription_remarks = (string) ($subscription->remarks ?? '');
        $this->edit_subscription_adjustment_reason = '';
        $this->editCustomerSearch = (string) ($subscription->customer?->name ?? '');
        $this->editPackageSearch = (string) ($subscription->plan?->name ?? '');
        $this->edit_confirm_identity_change = false;
        $this->resetValidation();
    }

    public function cancelSubscriptionEditor(): void
    {
        $this->showSubscriptionEditor = false;
        $this->editingSubscriptionId = null;
        $this->edit_subscription_customer_id = '';
        $this->edit_subscription_plan_id = '';
        $this->edit_subscription_starts_at = '';
        $this->edit_subscription_ends_at = '';
        $this->edit_subscription_status = 'active';
        $this->edit_subscription_auto_renew = false;
        $this->edit_subscription_usage_limit = '0';
        $this->edit_subscription_used_uses = '0';
        $this->edit_subscription_remaining_uses = '0';
        $this->edit_subscription_remarks = '';
        $this->edit_subscription_adjustment_reason = '';
        $this->editCustomerSearch = '';
        $this->editPackageSearch = '';
        $this->edit_confirm_identity_change = false;
        $this->resetValidation();
    }

    public function selectEditSubscriptionCustomer(int $id): void
    {
        $customer = Customer::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('is_active', true)
            ->findOrFail($id);

        $this->edit_subscription_customer_id = (string) $customer->id;
        $this->editCustomerSearch = $customer->name;
    }

    public function clearEditSubscriptionCustomer(): void
    {
        $this->edit_subscription_customer_id = '';
        $this->editCustomerSearch = '';
    }

    public function selectEditSubscriptionPackage(int $id): void
    {
        $package = $this->packageQuery()
            ->with('service')
            ->findOrFail($id);

        if (! $this->packageIsComplete($package)) {
            $this->addError('edit_subscription_plan_id', 'Only complete packages can be assigned to a subscription.');
            return;
        }

        $this->edit_subscription_plan_id = (string) $package->id;
        $this->editPackageSearch = $package->name;
    }

    public function clearEditSubscriptionPackage(): void
    {
        $this->edit_subscription_plan_id = '';
        $this->editPackageSearch = '';
    }

    public function updateSubscription(): void
    {
        $validated = $this->validate([
            'edit_subscription_customer_id' => ['required', 'exists:customers,id'],
            'edit_subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'edit_subscription_starts_at' => ['required', 'date'],
            'edit_subscription_ends_at' => ['required', 'date', 'after_or_equal:edit_subscription_starts_at'],
            'edit_subscription_status' => ['required', 'in:active,expired,cancelled,exhausted'],
            'edit_subscription_auto_renew' => ['boolean'],
            'edit_subscription_usage_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'edit_subscription_used_uses' => ['required', 'integer', 'min:0', 'max:100000'],
            'edit_subscription_remaining_uses' => ['required', 'integer', 'min:0', 'max:100000'],
            'edit_subscription_remarks' => ['nullable', 'string', 'max:1000'],
            'edit_subscription_adjustment_reason' => ['nullable', 'string', 'max:500'],
            'edit_confirm_identity_change' => ['boolean'],
        ]);

        $subscription = $this->subscriptionQuery()
            ->with(['customer', 'plan'])
            ->findOrFail((int) $this->editingSubscriptionId);
        $customer = Customer::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('is_active', true)
            ->findOrFail($validated['edit_subscription_customer_id']);
        $package = $this->packageQuery()
            ->with('service')
            ->findOrFail($validated['edit_subscription_plan_id']);

        if (! $this->packageIsComplete($package)) {
            $this->addError('edit_subscription_plan_id', 'Only complete packages can be assigned to a subscription.');
            return;
        }

        $limit = (int) $validated['edit_subscription_usage_limit'];
        $used = (int) $validated['edit_subscription_used_uses'];
        $remaining = (int) $validated['edit_subscription_remaining_uses'];

        if ($used + $remaining !== $limit) {
            $this->addError('edit_subscription_usage_limit', 'Used and remaining uses must add up to the usage limit.');
            return;
        }

        $oldValues = $this->subscriptionAuditValues($subscription);
        $oldUsed = $subscription->usedUses();
        $identityChanged = (int) $subscription->customer_id !== (int) $customer->id
            || (int) $subscription->subscription_plan_id !== (int) $package->id;

        if ($identityChanged && $oldUsed > 0 && ! $validated['edit_confirm_identity_change']) {
            $this->addError('edit_confirm_identity_change', 'Confirm customer/package changes because this subscription already has usage.');
            return;
        }

        $status = $validated['edit_subscription_status'];
        if ($status !== 'cancelled') {
            if ($remaining <= 0) {
                $status = 'exhausted';
            } elseif (Carbon::parse($validated['edit_subscription_ends_at'])->lt(today())) {
                $status = 'expired';
            }
        }

        $newValues = [
            'customer_id' => (int) $customer->id,
            'subscription_plan_id' => (int) $package->id,
            'starts_at' => Carbon::parse($validated['edit_subscription_starts_at'])->toDateString(),
            'ends_at' => Carbon::parse($validated['edit_subscription_ends_at'])->toDateString(),
            'status' => $status,
            'auto_renew' => (bool) $validated['edit_subscription_auto_renew'],
            'usage_limit' => $limit,
            'used_uses' => $used,
            'remaining_uses' => $remaining,
            'remarks' => $validated['edit_subscription_remarks'] ?: null,
        ];

        $sensitiveChanged = collect($newValues)
            ->except(['auto_renew', 'remarks'])
            ->contains(fn ($value, string $key) => ($oldValues[$key] ?? null) !== $value);

        if ($sensitiveChanged && trim((string) $validated['edit_subscription_adjustment_reason']) === '') {
            $this->addError('edit_subscription_adjustment_reason', 'A reason is required for date, status, customer, package, or usage changes.');
            return;
        }

        $payload = [
            'customer_id' => $newValues['customer_id'],
            'subscription_plan_id' => $newValues['subscription_plan_id'],
            'starts_at' => $newValues['starts_at'],
            'ends_at' => $newValues['ends_at'],
            'status' => $newValues['status'],
            'auto_renew' => $newValues['auto_renew'],
            'remarks' => $newValues['remarks'],
            'allowance' => [
                'limit' => $limit,
                'used' => $used,
                'remaining' => $remaining,
            ],
        ];

        if (Schema::hasColumn('customer_subscriptions', 'washes_remaining')) {
            $payload['washes_remaining'] = $remaining;
        }

        $subscription->forceFill($payload)->save();

        ActivityLog::record('subscription.updated', $subscription->fresh(), [
            'customer' => $customer->name,
            'package' => $package->name,
            'reason' => $validated['edit_subscription_adjustment_reason'] ?: 'General update',
            'identity_changed' => $identityChanged,
        ], $oldValues, $newValues);

        $this->selectedSubscriptionId = $subscription->id;
        $this->cancelSubscriptionEditor();
        session()->flash('status', 'Subscription updated.');
    }

    public function extendSubscription(int $id, int $days = 30): void
    {
        $subscription = $this->subscriptionQuery()->findOrFail($id);
        $oldValues = $this->subscriptionAuditValues($subscription);
        $base = $subscription->ends_at && $subscription->ends_at->greaterThan(today()) ? $subscription->ends_at : today();
        $subscription->forceFill([
            'ends_at' => $base->copy()->addDays(max($days, 1))->toDateString(),
            'status' => $subscription->remainingUses() <= 0 ? 'exhausted' : 'active',
        ])->save();

        ActivityLog::record('subscription.extended', $subscription->fresh(), [
            'days' => max($days, 1),
            'reason' => 'Quick action',
        ], $oldValues, $this->subscriptionAuditValues($subscription->fresh()));

        $this->selectedSubscriptionId = $subscription->id;
        session()->flash('status', 'Subscription expiry extended.');
    }

    public function addSubscriptionUses(int $id, int $uses = 1): void
    {
        $uses = max($uses, 1);
        $subscription = $this->subscriptionQuery()->findOrFail($id);
        $oldValues = $this->subscriptionAuditValues($subscription);
        $limit = max($subscription->usageLimit(), 0) + $uses;
        $remaining = max($subscription->remainingUses(), 0) + $uses;
        $used = max($subscription->usedUses(), 0);
        $payload = [
            'status' => $subscription->ends_at && $subscription->ends_at->lt(today()) ? 'expired' : 'active',
            'allowance' => [
                'limit' => $limit,
                'used' => $used,
                'remaining' => $remaining,
            ],
        ];

        if (Schema::hasColumn('customer_subscriptions', 'washes_remaining')) {
            $payload['washes_remaining'] = $remaining;
        }

        $subscription->forceFill($payload)->save();

        ActivityLog::record('subscription.uses_added', $subscription->fresh(), [
            'uses' => $uses,
            'reason' => 'Quick action',
        ], $oldValues, $this->subscriptionAuditValues($subscription->fresh()));

        $this->selectedSubscriptionId = $subscription->id;
        session()->flash('status', $uses.' subscription use'.($uses === 1 ? '' : 's').' added.');
    }

    public function edit(int $id): void
    {
        $package = $this->packageQuery()->findOrFail($id);

        $this->activeTab = 'packages';
        $this->showPackageForm = true;
        $this->editingId = $package->id;
        $this->name = $package->name;
        $this->laundry_service_id = (string) $package->laundry_service_id;
        $this->validity_months = (string) ($package->validity_months ?: max(1, (int) ceil($package->validity_days / 30)));
        $this->usage_limit = (string) ($package->usage_limit ?: $package->wash_limit);
        $this->pickup_included = (bool) $package->pickup_included;
        $this->amount = (string) ($package->amount ?: $package->price);
        $this->is_active = (bool) $package->is_active;
    }

    public function toggleStatus(int $id): void
    {
        $package = $this->packageQuery()->findOrFail($id);

        if (! $package->is_active && ! $this->packageIsComplete($package)) {
            session()->flash('error', 'Complete the package service, usage limit, validity, and amount before enabling it.');
            return;
        }

        if ($package->is_active && $this->hasActiveSubscriptions($package)) {
            session()->flash('error', 'Package has active customer subscriptions and cannot be disabled.');
            return;
        }

        $package->update(['is_active' => ! $package->is_active]);
        session()->flash('status', $package->fresh()->is_active ? 'Subscription package enabled.' : 'Subscription package disabled.');
    }

    public function delete(int $id): void
    {
        $package = $this->packageQuery()->findOrFail($id);

        if (CustomerSubscription::withoutGlobalScopes()->where('subscription_plan_id', $package->id)->exists()) {
            session()->flash('error', 'Package is assigned to customers and cannot be deleted.');
            return;
        }

        $package->delete();
        $this->resetForm();
        session()->flash('status', 'Subscription package deleted.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->laundry_service_id = '';
        $this->validity_months = '1';
        $this->usage_limit = '1';
        $this->pickup_included = false;
        $this->amount = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function resetSubscriptionForm(): void
    {
        $this->subscription_customer_id = '';
        $this->subscription_plan_id = '';
        $this->subscription_starts_at = now()->toDateString();
        $this->subscription_auto_renew = false;
        $this->subscription_remarks = '';
        $this->customerSearch = '';
        $this->packageSearch = '';
        $this->resetValidation();
    }

    public function renewSubscription(int $id): void
    {
        $subscription = $this->subscriptionQuery()->with('plan')->findOrFail($id);
        $subscription->renew();

        ActivityLog::record('subscription.renewed', $subscription, [
            'customer' => $subscription->customer?->name,
            'package' => $subscription->plan?->name,
            'remaining' => $subscription->fresh()->remainingUses(),
        ]);

        session()->flash('status', 'Subscription renewed.');
    }

    public function cancelSubscription(int $id): void
    {
        $subscription = $this->subscriptionQuery()->findOrFail($id);
        $subscription->update(['status' => 'cancelled']);

        ActivityLog::record('subscription.cancelled', $subscription, [
            'customer' => $subscription->customer?->name,
            'package' => $subscription->plan?->name,
        ]);

        session()->flash('status', 'Subscription cancelled.');
    }

    public function expireDueSubscriptions(): void
    {
        $count = $this->subscriptionQuery()
            ->where('status', 'active')
            ->whereDate('ends_at', '<', today())
            ->update(['status' => 'expired']);

        session()->flash('status', $count.' expired subscription'.($count === 1 ? '' : 's').' updated.');
    }

    public function packageIssues(SubscriptionPlan $package): array
    {
        $issues = [];

        if (! $package->laundry_service_id || ! $package->service) {
            $issues[] = 'Service missing';
        }

        if ($this->packageUsageLimit($package) <= 0) {
            $issues[] = 'Usage missing';
        }

        if ($this->packageValidityMonths($package) <= 0) {
            $issues[] = 'Validity missing';
        }

        if ($this->packageAmount($package) <= 0) {
            $issues[] = 'Amount missing';
        }

        return $issues;
    }

    public function render()
    {
        $branchId = auth()->user()?->branch_id;

        $services = LaundryService::query()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $packages = $this->packageQuery()
            ->with('service')
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhereHas('service', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'));
            }))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('amount')
            ->get();

        $allPackagesForStats = $this->packageQuery()
            ->with('service')
            ->get();

        $setupIssueCount = $allPackagesForStats
            ->filter(fn (SubscriptionPlan $package) => $this->packageIssues($package) !== [])
            ->count();

        $customers = Customer::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->when($this->customerSearch !== '' && $this->subscription_customer_id === '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('name', 'like', '%'.$this->customerSearch.'%')
                    ->orWhere('phone', 'like', '%'.$this->customerSearch.'%')
                    ->orWhere('customer_code', 'like', '%'.$this->customerSearch.'%');
            }))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'phone', 'customer_code']);

        $selectedCustomer = $this->subscription_customer_id !== ''
            ? Customer::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->find($this->subscription_customer_id)
            : null;

        $activePackages = $this->packageQuery()
            ->with('service')
            ->where('is_active', true)
            ->whereNotNull('laundry_service_id')
            ->when($this->packageSearch !== '' && $this->subscription_plan_id === '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('name', 'like', '%'.$this->packageSearch.'%')
                    ->orWhereHas('service', fn (Builder $query) => $query->where('name', 'like', '%'.$this->packageSearch.'%'));
            }))
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->filter(fn (SubscriptionPlan $package) => $this->packageIsComplete($package))
            ->take(8)
            ->values();

        $editCustomers = Customer::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->when($this->editCustomerSearch !== '' && (string) $this->edit_subscription_customer_id === '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('name', 'like', '%'.$this->editCustomerSearch.'%')
                    ->orWhere('phone', 'like', '%'.$this->editCustomerSearch.'%')
                    ->orWhere('customer_code', 'like', '%'.$this->editCustomerSearch.'%');
            }))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'phone', 'customer_code']);

        $selectedEditCustomer = $this->edit_subscription_customer_id !== ''
            ? Customer::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->find($this->edit_subscription_customer_id)
            : null;

        $editPackages = $this->packageQuery()
            ->with('service')
            ->when($this->editPackageSearch !== '' && (string) $this->edit_subscription_plan_id === '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('name', 'like', '%'.$this->editPackageSearch.'%')
                    ->orWhereHas('service', fn (Builder $query) => $query->where('name', 'like', '%'.$this->editPackageSearch.'%'));
            }))
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->filter(fn (SubscriptionPlan $package) => $this->packageIsComplete($package))
            ->take(8)
            ->values();

        $selectedEditPackage = $this->edit_subscription_plan_id !== ''
            ? $this->packageQuery()->with('service')->find($this->edit_subscription_plan_id)
            : null;

        $selectedPackage = $this->subscription_plan_id !== ''
            ? $this->packageQuery()->with('service')->find($this->subscription_plan_id)
            : null;

        $subscriptionBaseQuery = $this->subscriptionQuery()
            ->with(['customer', 'plan.service'])
            ->when($this->subscriptionSearch !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('subscription_no', 'like', '%'.$this->subscriptionSearch.'%')
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', '%'.$this->subscriptionSearch.'%')
                        ->orWhere('phone', 'like', '%'.$this->subscriptionSearch.'%')
                        ->orWhere('customer_code', 'like', '%'.$this->subscriptionSearch.'%'))
                    ->orWhereHas('plan', fn (Builder $query) => $query->where('name', 'like', '%'.$this->subscriptionSearch.'%'));
            }))
            ->when($this->subscriptionFilter === 'active', fn (Builder $query) => $query->where('status', 'active'))
            ->when($this->subscriptionFilter === 'due_7', fn (Builder $query) => $query->where('status', 'active')
                ->whereBetween('ends_at', [today()->toDateString(), today()->addDays(7)->toDateString()]))
            ->when($this->subscriptionFilter === 'due_30', fn (Builder $query) => $query->where('status', 'active')
                ->whereBetween('ends_at', [today()->toDateString(), today()->addDays(30)->toDateString()]))
            ->when($this->subscriptionFilter === 'cancelled_expired', fn (Builder $query) => $query->whereIn('status', ['cancelled', 'expired']));

        $customerSubscriptions = $subscriptionBaseQuery
            ->latest()
            ->limit(100)
            ->get();

        if ($this->subscriptionFilter === 'exhausted') {
            $customerSubscriptions = $customerSubscriptions
                ->filter(fn (CustomerSubscription $subscription) => $subscription->status === 'active' && $subscription->remainingUses() <= 0)
                ->values();
        }

        $selectedSubscription = $this->selectedSubscriptionId
            ? $this->subscriptionQuery()->with(['customer', 'plan.service'])->find($this->selectedSubscriptionId)
            : null;

        if (! $selectedSubscription || ! $customerSubscriptions->contains('id', $selectedSubscription->id)) {
            $selectedSubscription = $customerSubscriptions->first();
            $this->selectedSubscriptionId = $selectedSubscription?->id;
        }

        $activeSubscriptionCollection = $this->subscriptionQuery()
            ->with('plan')
            ->where('status', 'active')
            ->get();

        $subscriptionStats = [
            'active_packages' => $allPackagesForStats
                ->filter(fn (SubscriptionPlan $package) => $package->is_active && $this->packageIssues($package) === [])
                ->count(),
            'needs_setup' => $setupIssueCount,
            'active_subscriptions' => $activeSubscriptionCollection->count(),
            'expiring_soon' => $this->subscriptionQuery()
                ->where('status', 'active')
                ->whereBetween('ends_at', [today()->toDateString(), today()->addDays(30)->toDateString()])
                ->count(),
            'remaining_uses' => $activeSubscriptionCollection->sum(fn (CustomerSubscription $subscription) => $subscription->remainingUses()),
        ];

        return view('livewire.subscription-packages-manager', [
            'services' => $services,
            'packages' => $packages,
            'customers' => $customers,
            'activePackages' => $activePackages,
            'editCustomers' => $editCustomers,
            'editPackages' => $editPackages,
            'selectedCustomer' => $selectedCustomer,
            'selectedPackage' => $selectedPackage,
            'selectedEditCustomer' => $selectedEditCustomer,
            'selectedEditPackage' => $selectedEditPackage,
            'customerSubscriptions' => $customerSubscriptions,
            'selectedSubscription' => $selectedSubscription,
            'subscriptionStats' => $subscriptionStats,
        ])->layout('layouts.app', ['title' => 'Subscription Packages']);
    }

    private function packageQuery(): Builder
    {
        return SubscriptionPlan::query()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereNull('branch_id');
            });
    }

    private function hasActiveSubscriptions(SubscriptionPlan $package): bool
    {
        return CustomerSubscription::withoutGlobalScopes()
            ->where('subscription_plan_id', $package->id)
            ->where('status', 'active')
            ->exists();
    }

    private function packageIsComplete(SubscriptionPlan $package): bool
    {
        return $this->packageIssues($package) === [];
    }

    private function packageUsageLimit(SubscriptionPlan $package): int
    {
        return (int) ($package->usage_limit ?: $package->wash_limit);
    }

    private function packageValidityMonths(SubscriptionPlan $package): int
    {
        return (int) ($package->validity_months ?: max(0, (int) ceil(((int) $package->validity_days) / 30)));
    }

    private function packageAmount(SubscriptionPlan $package): float
    {
        return (float) ($package->amount ?: $package->price);
    }

    private function subscriptionQuery(): Builder
    {
        return CustomerSubscription::withoutGlobalScopes()
            ->where(function (Builder $query) {
                $query->where('branch_id', auth()->user()?->branch_id)
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('branch_id', auth()->user()?->branch_id));
            });
    }

    private function subscriptionPayload(Customer $customer, SubscriptionPlan $package, Carbon $start, int $limit, array $overrides = []): array
    {
        $months = max((int) ($package->validity_months ?: ceil(((int) $package->validity_days) / 30)), 1);
        $payload = array_merge([
            'branch_id' => auth()->user()?->branch_id,
            'customer_id' => $customer->id,
            'subscription_plan_id' => $package->id,
            'subscription_no' => $this->nextSubscriptionNumber(),
            'starts_at' => $start->toDateString(),
            'ends_at' => $start->copy()->addMonths($months)->toDateString(),
            'status' => 'active',
            'allowance' => [
                'limit' => $limit,
                'used' => 0,
                'remaining' => $limit,
            ],
            'auto_renew' => false,
            'remarks' => null,
        ], $overrides);

        if (Schema::hasColumn('customer_subscriptions', 'washes_remaining')) {
            $payload['washes_remaining'] = $limit;
        }

        return $payload;
    }

    private function subscriptionAuditValues(CustomerSubscription $subscription): array
    {
        return [
            'customer_id' => (int) $subscription->customer_id,
            'subscription_plan_id' => (int) $subscription->subscription_plan_id,
            'starts_at' => $subscription->starts_at?->toDateString(),
            'ends_at' => $subscription->ends_at?->toDateString(),
            'status' => $subscription->status,
            'auto_renew' => (bool) $subscription->auto_renew,
            'usage_limit' => max($subscription->usageLimit(), 0),
            'used_uses' => max($subscription->usedUses(), 0),
            'remaining_uses' => max($subscription->remainingUses(), 0),
            'remarks' => $subscription->remarks,
        ];
    }

    private function nextSubscriptionNumber(): string
    {
        $prefix = 'SUB-'.now()->format('Ymd').'-';
        $count = CustomerSubscription::withoutGlobalScopes()
            ->where('subscription_no', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function nextPackageCode(?int $branchId): string
    {
        $prefix = 'SUB-PKG-'.now()->format('Ymd').'-';
        $count = SubscriptionPlan::query()
            ->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->where('code', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
