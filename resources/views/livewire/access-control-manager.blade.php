<div class="module-page access-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Administration</p>
            <h2>Access Control</h2>
        </div>
        <div class="module-actions">
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
            @if (session('error'))
                <span class="notice notice--error">{{ session('error') }}</span>
            @endif
        </div>
    </section>

    <nav class="access-tabs" aria-label="Access control tabs">
        <button type="button" wire:click="setActiveTab('users')" class="{{ $activeTab === 'users' ? 'is-active' : '' }}">Users</button>
        <button type="button" wire:click="setActiveTab('roles')" class="{{ $activeTab === 'roles' ? 'is-active' : '' }}">Roles</button>
        <button type="button" wire:click="setActiveTab('permissions')" class="{{ $activeTab === 'permissions' ? 'is-active' : '' }}">Permissions</button>
    </nav>

    @if ($activeTab === 'users')
        <section class="module-grid access-layout">
            <form wire:submit="saveUser" class="module-panel">
                <h3>{{ $userEditingId ? 'Edit User' : 'Create User' }}</h3>

                <div class="form-grid form-grid--single">
                    <label class="field">
                        <span>Name</span>
                        <input type="text" wire:model="userName">
                        @error('userName') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Email</span>
                        <input type="email" wire:model="userEmail">
                        @error('userEmail') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Phone</span>
                        <input type="text" wire:model="userPhone">
                        @error('userPhone') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>{{ $userEditingId ? 'New Password' : 'Password' }}</span>
                        <input type="password" wire:model="userPassword" placeholder="{{ $userEditingId ? 'Leave blank to keep current password' : 'Minimum 8 characters' }}">
                        @error('userPassword') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Branch</span>
                        <select wire:model="userBranchId">
                            <option value="">No branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('userBranchId') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="toggle-field">
                        <input type="checkbox" wire:model="userIsActive">
                        <span>Account Active</span>
                    </label>
                </div>

                <div class="access-check-list">
                    <h4>Roles</h4>
                    @foreach ($roles as $role)
                        <label>
                            <input type="checkbox" wire:model="userRoles" value="{{ $role->name }}">
                            <span>{{ $role->name }}</span>
                        </label>
                    @endforeach
                    @error('userRoles') <small>{{ $message }}</small> @enderror
                </div>

                <div class="form-actions">
                    @if ($userEditingId)
                        <button type="button" wire:click="resetUserForm" class="btn-secondary">Cancel</button>
                    @endif
                    <button type="submit" class="btn-primary">{{ $userEditingId ? 'Update User' : 'Save User' }}</button>
                </div>
            </form>

            <section class="module-panel module-panel--list">
                <div class="list-toolbar list-toolbar--single">
                    <label class="field">
                        <span>Search Users</span>
                        <input type="search" wire:model.live="userSearch" placeholder="Name, email, phone, role">
                    </label>
                </div>

                <div class="service-list">
                    @forelse ($users as $user)
                        <article class="service-row">
                            <div>
                                <div class="service-row__title">
                                    <h3>{{ $user->name }}</h3>
                                    <span class="{{ $user->is_active ? 'badge badge--success' : 'badge badge--muted' }}">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <p>{{ $user->email }} · {{ $user->phone ?: 'No phone' }} · {{ $user->branch?->name ?? 'No branch' }}</p>
                                <div class="service-row__meta">
                                    @forelse ($user->roles as $role)
                                        <span>{{ $role->name }}</span>
                                    @empty
                                        <span>No role</span>
                                    @endforelse
                                </div>
                            </div>
                            <div class="row-actions">
                                <button type="button" wire:click="editUser({{ $user->id }})" class="btn-secondary">Edit</button>
                                <button type="button" wire:click="toggleUserStatus({{ $user->id }})" class="btn-secondary">
                                    {{ $user->is_active ? 'Disable' : 'Enable' }}
                                </button>
                                <button type="button" wire:click="deleteUser({{ $user->id }})" wire:confirm="Delete this user?" class="btn-danger">Delete</button>
                            </div>
                        </article>
                    @empty
                        <p class="empty-state">No users found.</p>
                    @endforelse
                </div>
            </section>
        </section>
    @endif

    @if ($activeTab === 'roles')
        <section class="module-grid access-layout">
            <form wire:submit="saveRole" class="module-panel">
                <h3>{{ $roleEditingId ? 'Edit Role' : 'Create Role' }}</h3>

                <label class="field">
                    <span>Role Name</span>
                    <input type="text" wire:model="roleName" @readonly($roleName === 'Super Admin')>
                    @error('roleName') <small>{{ $message }}</small> @enderror
                </label>

                <div class="access-permission-matrix">
                    @foreach ($permissionGroups as $group => $groupPermissions)
                        <section>
                            <h4>{{ str($group)->headline() }}</h4>
                            @foreach ($groupPermissions as $permission)
                                <label>
                                    <input type="checkbox" wire:model="rolePermissions" value="{{ $permission->name }}" @disabled($roleName === 'Super Admin')>
                                    <span>{{ $permission->name }}</span>
                                </label>
                            @endforeach
                        </section>
                    @endforeach
                    @error('rolePermissions') <small>{{ $message }}</small> @enderror
                </div>

                <div class="form-actions">
                    @if ($roleEditingId)
                        <button type="button" wire:click="resetRoleForm" class="btn-secondary">Cancel</button>
                    @endif
                    <button type="submit" class="btn-primary">{{ $roleEditingId ? 'Update Role' : 'Save Role' }}</button>
                </div>
            </form>

            <section class="module-panel module-panel--list">
                <div class="service-list">
                    @forelse ($roles as $role)
                        <article class="service-row">
                            <div>
                                <div class="service-row__title">
                                    <h3>{{ $role->name }}</h3>
                                    @if ($role->name === 'Super Admin')
                                        <span class="badge badge--warning">Protected</span>
                                    @endif
                                </div>
                                <p>{{ $role->users_count }} users · {{ $role->permissions->count() }} permissions</p>
                                <div class="service-row__meta">
                                    @foreach ($role->permissions->take(8) as $permission)
                                        <span>{{ $permission->name }}</span>
                                    @endforeach
                                    @if ($role->permissions->count() > 8)
                                        <span>{{ $role->permissions->count() - 8 }} more</span>
                                    @endif
                                </div>
                            </div>
                            <div class="row-actions">
                                <button type="button" wire:click="editRole({{ $role->id }})" class="btn-secondary">Edit</button>
                                <button type="button" wire:click="deleteRole({{ $role->id }})" wire:confirm="Delete this role?" class="btn-danger">Delete</button>
                            </div>
                        </article>
                    @empty
                        <p class="empty-state">No roles found.</p>
                    @endforelse
                </div>
            </section>
        </section>
    @endif

    @if ($activeTab === 'permissions')
        <section class="module-grid access-layout">
            <form wire:submit="savePermission" class="module-panel">
                <h3>{{ $permissionEditingId ? 'Edit Permission' : 'Create Permission' }}</h3>

                <label class="field">
                    <span>Permission Name</span>
                    <input type="text" wire:model="permissionName" placeholder="module.action">
                    @error('permissionName') <small>{{ $message }}</small> @enderror
                </label>

                <div class="form-actions">
                    @if ($permissionEditingId)
                        <button type="button" wire:click="resetPermissionForm" class="btn-secondary">Cancel</button>
                    @endif
                    <button type="submit" class="btn-primary">{{ $permissionEditingId ? 'Update Permission' : 'Save Permission' }}</button>
                </div>
            </form>

            <section class="module-panel module-panel--list">
                <div class="service-list">
                    @forelse ($permissions as $permission)
                        <article class="service-row">
                            <div>
                                <div class="service-row__title">
                                    <h3>{{ $permission->name }}</h3>
                                    @if (in_array($permission->name, $protectedPermissionNames, true))
                                        <span class="badge badge--warning">Route guard</span>
                                    @endif
                                </div>
                                <p>{{ $permission->roles_count }} roles use this permission</p>
                            </div>
                            <div class="row-actions">
                                <button type="button" wire:click="editPermission({{ $permission->id }})" class="btn-secondary">Edit</button>
                                <button type="button" wire:click="deletePermission({{ $permission->id }})" wire:confirm="Delete this permission?" class="btn-danger">Delete</button>
                            </div>
                        </article>
                    @empty
                        <p class="empty-state">No permissions found.</p>
                    @endforelse
                </div>
            </section>
        </section>
    @endif

    <style>
        .access-page { gap: .9rem; }
        .access-tabs { align-items: center; background: #fff; border: 1px solid #e4e4e7; border-radius: .5rem; display: flex; flex-wrap: wrap; gap: .4rem; padding: .45rem; }
        .access-tabs button { background: #f8fafc; border: 1px solid transparent; border-radius: .4rem; color: #334155; cursor: pointer; font-size: .78rem; font-weight: 950; min-height: 2.35rem; padding: .45rem .75rem; }
        .access-tabs button.is-active { background: #0e7490; border-color: #0e7490; color: #fff; }
        .access-layout { grid-template-columns: minmax(18rem, .56fr) minmax(0, 1.44fr); }
        .access-check-list, .access-permission-matrix { display: grid; gap: .55rem; margin-top: 1rem; }
        .access-check-list h4, .access-permission-matrix h4 { color: #111827; font-size: .8rem; font-weight: 950; margin: 0; text-transform: uppercase; }
        .access-check-list label, .access-permission-matrix label { align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .42rem; display: flex; gap: .55rem; min-height: 2.35rem; padding: .5rem .65rem; }
        .access-check-list input, .access-permission-matrix input { accent-color: #0e7490; flex: 0 0 auto; height: 1rem; width: 1rem; }
        .access-check-list span, .access-permission-matrix span { color: #334155; font-size: .78rem; font-weight: 850; overflow-wrap: anywhere; }
        .access-permission-matrix { max-height: 31rem; overflow: auto; padding-right: .2rem; }
        .access-permission-matrix section { border-top: 1px solid #e2e8f0; display: grid; gap: .45rem; padding-top: .75rem; }
        .access-permission-matrix section:first-child { border-top: 0; padding-top: 0; }
        .access-page .service-row { align-items: start; }
        @media (max-width: 980px) { .access-layout { grid-template-columns: 1fr; } .access-permission-matrix { max-height: none; } }
        @media (max-width: 640px) { .access-tabs button { width: 100%; } }
    </style>
</div>
