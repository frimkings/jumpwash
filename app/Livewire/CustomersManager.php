<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Support\LoyaltyService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class CustomersManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingId = null;
    public ?int $selectedId = null;
    public bool $showProfileModal = false;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $customer_code = '';
    public string $full_name = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $gps_location = '';
    public string $notes = '';
    public ?TemporaryUploadedFile $importFile = null;
    public bool $is_active = true;
    public string $loyalty_adjustment_points = '';
    public string $loyalty_adjustment_reason = '';

    public function quickRegister(): void
    {
        $this->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $this->save();
    }

    public function exportCustomers()
    {
        $customers = $this->customerQuery()
            ->orderBy('name')
            ->get(['customer_code', 'first_name', 'last_name', 'name', 'phone', 'email', 'address', 'gps_location', 'notes', 'is_active']);

        return response()->streamDownload(function () use ($customers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['customer_number', 'first_name', 'last_name', 'full_name', 'phone', 'email', 'address', 'gps_location', 'notes', 'is_active']);

            foreach ($customers as $customer) {
                fputcsv($handle, [
                    $customer->customer_code,
                    $customer->first_name,
                    $customer->last_name,
                    $customer->name,
                    $customer->phone,
                    $customer->email,
                    $customer->address,
                    $customer->gps_location,
                    $customer->notes,
                    $customer->is_active ? '1' : '0',
                ]);
            }

            fclose($handle);
        }, 'jumpwash-customers.csv');
    }

    public function downloadTemplate()
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['first_name', 'last_name', 'full_name', 'phone', 'email', 'address', 'gps_location', 'notes', 'is_active']);
            fputcsv($handle, ['Ama', 'Mensah', 'Ama Mensah', '0240000000', 'ama@example.com', 'Accra', '5.6037,-0.1870', 'Prefers door pickup', '1']);
            fclose($handle);
        }, 'jumpwash-customer-import-template.csv');
    }

    public function importCustomers(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $handle = fopen($this->importFile->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $imported = 0;

        if (! $header) {
            session()->flash('error', 'CSV file is empty.');
            return;
        }

        $columns = collect($header)->map(fn ($column) => str($column)->lower()->replace([' ', '-'], '_')->toString())->all();

        while (($line = fgetcsv($handle)) !== false) {
            $row = array_combine($columns, array_slice(array_pad($line, count($columns), null), 0, count($columns)));

            if (! $row || blank($row['phone'] ?? null)) {
                continue;
            }

            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $fullName = trim((string) ($row['full_name'] ?? trim($firstName.' '.$lastName)));

            if ($firstName === '' && $fullName !== '') {
                $firstName = str($fullName)->before(' ')->toString();
                $lastName = str($fullName)->after(' ')->toString();
            }

            if ($firstName === '') {
                continue;
            }

            $existingCustomer = $this->customerQuery()
                ->where('phone', trim((string) $row['phone']))
                ->first();

            $customer = Customer::updateOrCreate(
                [
                    'branch_id' => auth()->user()?->branch_id,
                    'phone' => trim((string) $row['phone']),
                ],
                [
                    'customer_code' => $existingCustomer?->customer_code ?: $this->nextCustomerNumber(),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => $fullName ?: trim($firstName.' '.$lastName),
                    'email' => filled($row['email'] ?? null) ? trim((string) $row['email']) : null,
                    'address' => filled($row['address'] ?? null) ? trim((string) $row['address']) : null,
                    'gps_location' => filled($row['gps_location'] ?? null) ? trim((string) $row['gps_location']) : null,
                    'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                    'is_active' => ! in_array(strtolower((string) ($row['is_active'] ?? '1')), ['0', 'false', 'inactive', 'no'], true),
                ],
            );

            ActivityLog::record('imported', $customer, [
                'module' => 'customers',
                'customer_number' => $customer->customer_code,
            ]);

            $imported++;
        }

        fclose($handle);
        $this->importFile = null;
        session()->flash('status', $imported.' customers imported.');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'gps_location' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $fullName = trim($validated['full_name']);
        $firstName = str($fullName)->before(' ')->toString() ?: $fullName;
        $lastName = str($fullName)->contains(' ') ? str($fullName)->after(' ')->toString() : '';

        $wasEditing = (bool) $this->editingId;
        $customerNumber = $wasEditing ? null : $this->nextCustomerNumber();
        $customer = $wasEditing
            ? $this->customerQuery()->findOrFail($this->editingId)
            : new Customer([
                'branch_id' => auth()->user()?->branch_id,
                'code' => $customerNumber,
                'customer_code' => $customerNumber,
            ]);

        $customer->fill([
            'branch_id' => auth()->user()?->branch_id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $fullName,
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'gps_location' => $validated['gps_location'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'],
        ])->save();

        ActivityLog::record($wasEditing ? 'updated' : 'created', $customer, [
            'module' => 'customers',
            'customer_number' => $customer->customer_code,
        ]);

        $this->selectedId = $customer->id;
        $this->resetForm(keepSelection: true);
        session()->flash('status', 'Customer saved.');
    }

    public function edit(int $id): void
    {
        $customer = $this->customerQuery()->findOrFail($id);

        $this->editingId = $customer->id;
        $this->showProfileModal = false;
        $this->customer_code = $customer->customer_code;
        $this->full_name = $customer->name;
        $this->first_name = $customer->first_name ?: str($customer->name)->before(' ')->toString();
        $this->last_name = $customer->last_name ?: str($customer->name)->after(' ')->toString();
        $this->phone = $customer->phone;
        $this->email = (string) $customer->email;
        $this->address = (string) $customer->address;
        $this->gps_location = (string) $customer->gps_location;
        $this->notes = (string) $customer->notes;
        $this->is_active = (bool) $customer->is_active;
    }

    public function selectCustomer(int $id): void
    {
        $this->selectedId = $this->customerQuery()->findOrFail($id)->id;
        $this->showProfileModal = true;
    }

    public function closeProfile(): void
    {
        $this->showProfileModal = false;
    }

    public function toggleStatus(int $id): void
    {
        $customer = $this->customerQuery()->findOrFail($id);
        $customer->update(['is_active' => ! $customer->is_active]);
    }

    public function adjustLoyaltyPoints(LoyaltyService $loyalty): void
    {
        abort_unless(auth()->user()?->can('payments.manage') || auth()->user()?->can('loyalty.adjust'), 403);

        $customer = $this->selectedId ? $this->customerQuery()->findOrFail($this->selectedId) : null;

        if (! $customer || ! $this->showProfileModal) {
            return;
        }

        $validated = $this->validate([
            'loyalty_adjustment_points' => ['required', 'integer', 'not_in:0', 'min:-999999', 'max:999999'],
            'loyalty_adjustment_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $transaction = $loyalty->adjustPoints(
                $customer,
                (int) $validated['loyalty_adjustment_points'],
                $validated['loyalty_adjustment_reason'],
            );
        } catch (\InvalidArgumentException $exception) {
            $this->addError('loyalty_adjustment_points', $exception->getMessage());
            return;
        }

        ActivityLog::record('loyalty.adjustment_recorded', $transaction, [
            'module' => 'customers',
            'customer_number' => $customer->customer_code,
            'customer' => $customer->name,
            'reason' => $validated['loyalty_adjustment_reason'],
        ], [
            'loyalty_points' => (int) $customer->loyalty_points,
        ], [
            'loyalty_points' => (int) $customer->fresh()->loyalty_points,
            'points' => (int) $validated['loyalty_adjustment_points'],
        ]);

        $this->loyalty_adjustment_points = '';
        $this->loyalty_adjustment_reason = '';
        $this->resetValidation(['loyalty_adjustment_points', 'loyalty_adjustment_reason']);
        session()->flash('status', 'Loyalty points adjusted.');
    }

    public function delete(int $id): void
    {
        $customer = $this->customerQuery()->findOrFail($id);

        if ($customer->orders()->exists() || $customer->payments()->exists() || $customer->subscriptions()->exists()) {
            session()->flash('error', 'Customer has orders, payments, or subscriptions and cannot be deleted.');
            return;
        }

        $customer->delete();

        if ($this->selectedId === $id) {
            $this->selectedId = null;
            $this->showProfileModal = false;
        }

        $this->resetForm();
        session()->flash('status', 'Customer deleted.');
    }

    public function resetForm(bool $keepSelection = false): void
    {
        $selectedId = $this->selectedId;

        $this->editingId = null;
        $this->customer_code = '';
        $this->full_name = '';
        $this->first_name = '';
        $this->last_name = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->gps_location = '';
        $this->notes = '';
        $this->is_active = true;
        $this->loyalty_adjustment_points = '';
        $this->loyalty_adjustment_reason = '';
        $this->resetValidation();

        if ($keepSelection) {
            $this->selectedId = $selectedId;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $customerQuery = $this->customerQuery()
            ->withCount(['orders', 'payments', 'subscriptions'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('customer_code', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%')
                    ->orWhere('phone', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderByDesc('created_at');

        $activeCustomers = $this->customerQuery()->where('is_active', true)->count();
        $inactiveCustomers = $this->customerQuery()->where('is_active', false)->count();
        $customers = $customerQuery->paginate(15);

        $selectedCustomer = $this->selectedId
            ? $this->customerQuery()
                ->with([
                    'orders' => fn ($query) => $query->latest()->limit(8),
                    'payments' => fn ($query) => $query->latest()->limit(8),
                    'loyaltyTransactions' => fn ($query) => $query->latest()->limit(8),
                    'subscriptions.plan' => fn ($query) => $query->latest()->limit(8),
                    'history' => fn ($query) => $query->latest()->limit(8),
                ])
                ->find($this->selectedId)
            : null;

        return view('livewire.customers-manager', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'nextCustomerNumber' => $this->nextCustomerNumber(),
            'activeCustomers' => $activeCustomers,
            'inactiveCustomers' => $inactiveCustomers,
        ])->layout('layouts.app', ['title' => 'Customers']);
    }

    private function customerQuery(): Builder
    {
        return Customer::query()->where('branch_id', auth()->user()?->branch_id);
    }

    private function nextCustomerNumber(): string
    {
        $prefix = 'JW-'.now()->format('Ymd').'-';
        $latestNumber = Customer::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('customer_code', 'like', $prefix.'%')
            ->pluck('customer_code')
            ->map(fn (?string $code): int => (int) str($code ?? '')->afterLast('-')->toString())
            ->max() + 1;

        return $prefix.str_pad((string) $latestNumber, 4, '0', STR_PAD_LEFT);
    }
}
