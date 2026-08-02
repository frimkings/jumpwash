<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    private const USERS = [
        [
            'name' => 'JumpWash Super Admin',
            'email' => 'superadmin@jumpwash.test',
            'role' => 'Super Admin',
            'phone' => '0240000001',
        ],
        [
            'name' => 'JumpWash Manager',
            'email' => 'manager@jumpwash.test',
            'role' => 'Manager',
            'phone' => '0240000002',
        ],
        [
            'name' => 'JumpWash Cashier',
            'email' => 'cashier@jumpwash.test',
            'role' => 'Cashier',
            'phone' => '0240000003',
        ],
        [
            'name' => 'JumpWash Laundry Staff',
            'email' => 'laundry@jumpwash.test',
            'role' => 'Laundry Staff',
            'phone' => '0240000004',
        ],
        [
            'name' => 'JumpWash Delivery Staff',
            'email' => 'delivery@jumpwash.test',
            'role' => 'Delivery Staff',
            'phone' => '0240000005',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $branch = Branch::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Branch', 'phone' => '0000000000', 'address' => 'Local LAN Branch', 'is_active' => true],
        );

        foreach (self::USERS as $seed) {
            [$firstName, $lastName] = $this->splitName($seed['name']);

            $user = User::updateOrCreate(
                ['email' => $seed['email']],
                [
                    'branch_id' => $branch->id,
                    'name' => $seed['name'],
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $seed['phone'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->assignRole($seed['role']);

            StaffProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'branch_id' => $branch->id,
                    'staff_code' => 'STF-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'employee_code' => 'STF-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'title' => $seed['role'],
                    'position' => $seed['role'],
                    'phone' => $seed['phone'],
                    'status' => 'active',
                    'vehicle' => $seed['role'] === 'Delivery Staff' ? 'Motorbike' : null,
                    'license_number' => $seed['role'] === 'Delivery Staff' ? 'LIC-JW-0001' : null,
                    'availability' => 'available',
                ],
            );
        }
    }

    private function splitName(string $name): array
    {
        $parts = str($name)->after('JumpWash ')->explode(' ', 2);

        return [
            $parts[0] ?? $name,
            $parts[1] ?? '',
        ];
    }
}
