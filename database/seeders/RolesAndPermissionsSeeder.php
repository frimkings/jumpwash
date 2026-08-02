<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'orders.manage',
            'orders.assigned.view',
            'customers.manage',
            'staff.manage',
            'payments.manage',
            'payments.correct',
            'loyalty.adjust',
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

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'Super Admin' => $permissions,
            'Manager' => array_values(array_diff($permissions, ['settings.manage'])),
            'Cashier' => ['dashboard.view', 'orders.manage', 'customers.manage', 'payments.manage'],
            'Laundry Staff' => ['dashboard.view', 'orders.assigned.view', 'garments.scan'],
            'Delivery Staff' => ['dashboard.view', 'deliveries.assigned.view'],
        ];

        foreach ($rolePermissions as $roleName => $grants) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])
                ->syncPermissions($grants);
        }
    }
}
