<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupDeliveryTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlManager extends Component
{
    public string $activeTab = 'users';

    public string $userSearch = '';
    public ?int $userEditingId = null;
    public string $userName = '';
    public string $userEmail = '';
    public string $userPhone = '';
    public string $userPassword = '';
    public ?int $userBranchId = null;
    public bool $userIsActive = true;
    public array $userRoles = [];

    public ?int $roleEditingId = null;
    public string $roleName = '';
    public array $rolePermissions = [];

    public ?int $permissionEditingId = null;
    public string $permissionName = '';

    public function mount(): void
    {
        $this->userBranchId = auth()->user()?->branch_id;
    }

    public function setActiveTab(string $tab): void
    {
        abort_unless(in_array($tab, ['users', 'roles', 'permissions'], true), 404);

        $this->activeTab = $tab;
        $this->resetValidation();
    }

    public function saveUser(): void
    {
        $validated = $this->validate([
            'userName' => ['required', 'string', 'max:150'],
            'userEmail' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->userEditingId)],
            'userPhone' => ['nullable', 'string', 'max:40'],
            'userPassword' => [$this->userEditingId ? 'nullable' : 'required', 'string', 'min:8'],
            'userBranchId' => ['nullable', 'exists:branches,id'],
            'userIsActive' => ['boolean'],
            'userRoles' => ['array'],
            'userRoles.*' => ['string', 'exists:roles,name'],
        ]);

        $nameParts = str($validated['userName'])->explode(' ', 2);
        $payload = [
            'branch_id' => $validated['userBranchId'] ?? null,
            'name' => $validated['userName'],
            'first_name' => $nameParts[0] ?? $validated['userName'],
            'last_name' => $nameParts[1] ?? null,
            'phone' => $validated['userPhone'] ?: null,
            'email' => $validated['userEmail'],
            'is_active' => $validated['userIsActive'],
            'email_verified_at' => now(),
        ];

        if ($validated['userPassword'] !== '') {
            $payload['password'] = Hash::make($validated['userPassword']);
        }

        $user = User::updateOrCreate(['id' => $this->userEditingId], $payload);
        $user->syncRoles($validated['userRoles'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->resetUserForm();
        session()->flash('status', 'User saved.');
    }

    public function editUser(int $id): void
    {
        $user = User::query()->with('roles')->findOrFail($id);

        $this->activeTab = 'users';
        $this->userEditingId = $user->id;
        $this->userName = $user->name;
        $this->userEmail = $user->email;
        $this->userPhone = (string) $user->phone;
        $this->userPassword = '';
        $this->userBranchId = $user->branch_id;
        $this->userIsActive = (bool) $user->is_active;
        $this->userRoles = $user->roles->pluck('name')->all();
        $this->resetValidation();
    }

    public function toggleUserStatus(int $id): void
    {
        $user = User::query()->findOrFail($id);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot disable your own account.');
            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
        session()->flash('status', 'User status updated.');
    }

    public function deleteUser(int $id): void
    {
        $user = User::query()->findOrFail($id);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        if ($this->userHasOperationalRecords($user)) {
            session()->flash('error', 'This user is linked to orders, payments, or delivery tasks and cannot be deleted.');
            return;
        }

        $user->delete();
        $this->resetUserForm();
        session()->flash('status', 'User deleted.');
    }

    public function resetUserForm(): void
    {
        $this->userEditingId = null;
        $this->userName = '';
        $this->userEmail = '';
        $this->userPhone = '';
        $this->userPassword = '';
        $this->userBranchId = auth()->user()?->branch_id;
        $this->userIsActive = true;
        $this->userRoles = [];
        $this->resetValidation();
    }

    public function saveRole(): void
    {
        $validated = $this->validate([
            'roleName' => ['required', 'string', 'max:150', Rule::unique('roles', 'name')->ignore($this->roleEditingId)],
            'rolePermissions' => ['array'],
            'rolePermissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = $this->roleEditingId ? Role::query()->findOrFail($this->roleEditingId) : new Role(['guard_name' => 'web']);

        if ($role->exists && $role->name === 'Super Admin' && $validated['roleName'] !== 'Super Admin') {
            session()->flash('error', 'The Super Admin role name is protected.');
            return;
        }

        $role->name = $validated['roleName'];
        $role->guard_name = 'web';
        $role->save();

        $grants = $role->name === 'Super Admin'
            ? Permission::query()->orderBy('name')->pluck('name')->all()
            : array_values($validated['rolePermissions'] ?? []);

        $role->syncPermissions($grants);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->resetRoleForm();
        session()->flash('status', 'Role saved.');
    }

    public function editRole(int $id): void
    {
        $role = Role::query()->with('permissions')->findOrFail($id);

        $this->activeTab = 'roles';
        $this->roleEditingId = $role->id;
        $this->roleName = $role->name;
        $this->rolePermissions = $role->permissions->pluck('name')->all();
        $this->resetValidation();
    }

    public function deleteRole(int $id): void
    {
        $role = Role::query()->withCount('users')->findOrFail($id);

        if ($role->name === 'Super Admin') {
            session()->flash('error', 'The Super Admin role cannot be deleted.');
            return;
        }

        if ($role->users_count > 0) {
            session()->flash('error', 'This role is assigned to users and cannot be deleted.');
            return;
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->resetRoleForm();
        session()->flash('status', 'Role deleted.');
    }

    public function resetRoleForm(): void
    {
        $this->roleEditingId = null;
        $this->roleName = '';
        $this->rolePermissions = [];
        $this->resetValidation();
    }

    public function savePermission(): void
    {
        $validated = $this->validate([
            'permissionName' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9._-]+$/', Rule::unique('permissions', 'name')->ignore($this->permissionEditingId)],
        ]);

        $permission = $this->permissionEditingId ? Permission::query()->findOrFail($this->permissionEditingId) : new Permission(['guard_name' => 'web']);

        if ($permission->exists && in_array($permission->name, $this->protectedPermissionNames(), true)) {
            session()->flash('error', 'Core permissions used by routes cannot be renamed.');
            return;
        }

        $permission->name = $validated['permissionName'];
        $permission->guard_name = 'web';
        $permission->save();

        $this->syncSuperAdminPermissions();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->resetPermissionForm();
        session()->flash('status', 'Permission saved.');
    }

    public function editPermission(int $id): void
    {
        $permission = Permission::query()->findOrFail($id);

        $this->activeTab = 'permissions';
        $this->permissionEditingId = $permission->id;
        $this->permissionName = $permission->name;
        $this->resetValidation();
    }

    public function deletePermission(int $id): void
    {
        $permission = Permission::query()->withCount(['roles', 'users'])->findOrFail($id);

        if (in_array($permission->name, $this->protectedPermissionNames(), true)) {
            session()->flash('error', 'Core permissions used by routes cannot be deleted.');
            return;
        }

        if ($permission->roles_count > 0 || $permission->users_count > 0) {
            session()->flash('error', 'This permission is assigned to roles or users and cannot be deleted.');
            return;
        }

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->resetPermissionForm();
        session()->flash('status', 'Permission deleted.');
    }

    public function resetPermissionForm(): void
    {
        $this->permissionEditingId = null;
        $this->permissionName = '';
        $this->resetValidation();
    }

    public function render()
    {
        $roles = Role::query()->withCount('users')->with('permissions')->orderBy('name')->get();
        $permissions = Permission::query()->withCount('roles')->orderBy('name')->get();

        return view('livewire.access-control-manager', [
            'branches' => Branch::query()->orderBy('name')->get(),
            'users' => $this->usersQuery()->get(),
            'roles' => $roles,
            'permissions' => $permissions,
            'permissionGroups' => $permissions->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString()),
            'protectedPermissionNames' => $this->protectedPermissionNames(),
        ])->layout('layouts.app', ['title' => 'Access Control']);
    }

    private function usersQuery(): Builder
    {
        return User::query()
            ->with(['branch', 'roles'])
            ->when($this->userSearch !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('name', 'like', '%'.$this->userSearch.'%')
                    ->orWhere('email', 'like', '%'.$this->userSearch.'%')
                    ->orWhere('phone', 'like', '%'.$this->userSearch.'%')
                    ->orWhereHas('roles', fn (Builder $query) => $query->where('name', 'like', '%'.$this->userSearch.'%'));
            }))
            ->orderBy('name');
    }

    private function userHasOperationalRecords(User $user): bool
    {
        return Order::query()
            ->where('created_by', $user->id)
            ->orWhere('assigned_laundry_staff_id', $user->id)
            ->orWhere('delivery_staff_id', $user->id)
            ->orWhere('assigned_to', $user->id)
            ->exists()
            || Payment::query()->where('received_by', $user->id)->exists()
            || PickupDeliveryTask::query()->where('assigned_to', $user->id)->exists();
    }

    private function protectedPermissionNames(): array
    {
        return [
            'dashboard.view',
            'orders.manage',
            'orders.assigned.view',
            'customers.manage',
            'staff.manage',
            'payments.manage',
            'reports.view',
            'subscriptions.manage',
            'settings.manage',
            'services.manage',
            'products.manage',
            'rate-chart.manage',
            'garments.scan',
            'deliveries.manage',
            'deliveries.assigned.view',
        ];
    }

    private function syncSuperAdminPermissions(): void
    {
        $superAdmin = Role::query()->where('name', 'Super Admin')->first();

        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::query()->pluck('name')->all());
        }
    }
}
