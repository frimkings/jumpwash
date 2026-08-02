<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\BackupRecord;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\GarmentTag;
use App\Models\LaundryService;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupDeliveryTask;
use App\Models\Product;
use App\Models\RateChart;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TestDataSeeder extends Seeder
{
    private const TEST_USERS = [
        ['name' => 'JumpWash Super Admin', 'email' => 'superadmin@jumpwash.test', 'role' => 'Super Admin'],
        ['name' => 'JumpWash Manager', 'email' => 'manager@jumpwash.test', 'role' => 'Manager'],
        ['name' => 'JumpWash Cashier', 'email' => 'cashier@jumpwash.test', 'role' => 'Cashier'],
        ['name' => 'JumpWash Laundry Staff', 'email' => 'laundry@jumpwash.test', 'role' => 'Laundry Staff'],
        ['name' => 'JumpWash Delivery Staff', 'email' => 'delivery@jumpwash.test', 'role' => 'Delivery Staff'],
    ];

    private const ORDER_TARGET = 1000;
    private const CUSTOMER_TARGET = 250;

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DemoDataSeeder::class,
        ]);

        $branch = Branch::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Branch', 'phone' => '0000000000', 'address' => 'Local LAN Branch', 'is_active' => true],
        );

        $users = $this->seedUsers($branch);
        $customers = $this->seedCustomers($branch);
        [$services, $products] = $this->seedCatalog($branch);
        $orders = $this->seedOrders($branch, $customers, $services, $products, $users);

        $this->seedSubscriptions($branch, $customers);
        $this->seedPickupDelivery($branch, $orders, $users);
        $this->seedNotifications($branch, $users, $orders);
        $this->seedCalendarEvents($branch, $orders);
        $this->seedOperationalRecords($branch, $users, $orders);

        $this->command?->info('Seeded 5 users and topped up to '.self::ORDER_TARGET.' test orders with related feature data.');
    }

    private function seedUsers(Branch $branch): array
    {
        return collect(self::TEST_USERS)->map(function (array $seed) use ($branch): User {
            $user = User::updateOrCreate(
                ['email' => $seed['email']],
                [
                    'name' => $seed['name'],
                    'password' => Hash::make('password'),
                    'branch_id' => $branch->id,
                    'phone' => '024'.fake()->unique()->numerify('#######'),
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$seed['role']]);

            if (Schema::hasTable('staff_profiles')) {
                DB::table('staff_profiles')->updateOrInsert(
                    ['user_id' => $user->id],
                    $this->tablePayload('staff_profiles', [
                        'branch_id' => $branch->id,
                        'staff_code' => 'STF-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                        'employee_code' => 'STF-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                        'title' => $seed['role'],
                        'position' => $seed['role'],
                        'phone' => $user->phone,
                        'status' => 'active',
                        'hired_at' => now()->subMonths(6)->toDateString(),
                        'emergency_contact' => '0200000000',
                        'vehicle' => $seed['role'] === 'Delivery Staff' ? 'Motorbike' : null,
                        'license_number' => $seed['role'] === 'Delivery Staff' ? 'LIC-JW-0001' : null,
                        'availability' => 'available',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]),
                );
            }

            if (Schema::hasTable('staff')) {
                DB::table('staff')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'branch_id' => $branch->id,
                        'staff_number' => 'STF-CANON-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                        'first_name' => str($user->name)->after('JumpWash ')->beforeLast(' ')->toString() ?: $user->name,
                        'last_name' => str($user->name)->afterLast(' ')->toString(),
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'role' => $seed['role'],
                        'vehicle' => $seed['role'] === 'Delivery Staff' ? 'Motorbike' : null,
                        'license_number' => $seed['role'] === 'Delivery Staff' ? 'LIC-JW-0001' : null,
                        'availability' => 'available',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            return $user;
        })->all();
    }

    private function seedCustomers(Branch $branch): array
    {
        $existing = Customer::query()
            ->where('branch_id', $branch->id)
            ->where('customer_code', 'like', 'TST-CUS-%')
            ->count();

        for ($i = $existing + 1; $i <= self::CUSTOMER_TARGET; $i++) {
            $first = fake()->firstName();
            $last = fake()->lastName();

            Customer::create([
                'branch_id' => $branch->id,
                'code' => 'TST-CUS-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'customer_code' => 'TST-CUS-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'first_name' => $first,
                'last_name' => $last,
                'name' => $first.' '.$last,
                'phone' => '055'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'email' => 'customer'.$i.'@jumpwash.test',
                'address' => fake()->streetAddress(),
                'gps_location' => fake()->latitude(5, 6).','.fake()->longitude(-1, 1),
                'notes' => fake()->optional()->sentence(),
                'is_active' => fake()->boolean(96),
                'created_at' => now()->subDays(fake()->numberBetween(0, 365)),
                'updated_at' => now(),
            ]);
        }

        return Customer::query()
            ->where('branch_id', $branch->id)
            ->where('customer_code', 'like', 'TST-CUS-%')
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function seedCatalog(Branch $branch): array
    {
        $serviceNames = ['Laundry', 'Dry Cleaning', 'Ironing', 'Starching', 'Fold Only', 'Express Service', 'Curtain Cleaning', 'Blanket Cleaning'];
        $productNames = ['Shirt', 'Trousers', 'Suit', 'Dress', 'Curtains', 'Bedsheets', 'Blankets', 'Pillow Covers'];

        $services = collect($serviceNames)->map(fn (string $name) => LaundryService::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => str($name)->slug('-')->upper()->toString()],
            [
                'name' => $name,
                'description' => $name.' test service',
                'price' => 0,
                'tax_percentage' => 0,
                'unit' => 'piece',
                'turnaround_hours' => $name === 'Express Service' ? 8 : 24,
                'is_active' => true,
            ],
        ))->values();

        $products = collect($productNames)->map(fn (string $name) => Product::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => $name],
            ['description' => $name.' test garment', 'is_active' => true],
        ))->values();

        foreach ($products as $product) {
            foreach ($services as $service) {
                RateChart::updateOrCreate(
                    ['branch_id' => $branch->id, 'product_id' => $product->id, 'laundry_service_id' => $service->id],
                    ['price' => fake()->randomFloat(2, 3, 65)],
                );
            }
        }

        return [$services, $products];
    }

    private function seedOrders(Branch $branch, array $customers, $services, $products, array $users): array
    {
        $existing = Order::query()
            ->where('branch_id', $branch->id)
            ->where('order_number', 'like', 'TST-ORD-%')
            ->count();

        $cashier = collect($users)->firstWhere('email', 'cashier@jumpwash.test') ?? $users[0];
        $statuses = ['pending_pickup', 'picked_up', 'received', 'processing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'];
        $paymentStatuses = ['unpaid', 'part_paid', 'paid'];

        for ($i = $existing + 1; $i <= self::ORDER_TARGET; $i++) {
            $customer = $customers[array_rand($customers)];
            $status = $statuses[$i % count($statuses)];
            $paymentStatus = $paymentStatuses[$i % count($paymentStatuses)];
            $createdAt = now()->subDays(fake()->numberBetween(0, 360))->subMinutes(fake()->numberBetween(0, 1440));
            $lineCount = fake()->numberBetween(1, 4);
            $subtotal = 0;

            $order = Order::create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'created_by' => $cashier->id,
                'order_number' => 'TST-ORD-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'status' => $status,
                'payment_status' => $paymentStatus,
                'billing_source' => $i % 10 === 0 ? 'subscription' : 'cash',
                'expected_garment_count' => 0,
                'subtotal' => 0,
                'discount' => 0,
                'delivery_fee' => $i % 4 === 0 ? 10 : 0,
                'total' => 0,
                'total_amount' => 0,
                'amount_paid' => 0,
                'balance' => 0,
                'notes' => 'Generated test order',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $garmentCount = 0;

            for ($line = 1; $line <= $lineCount; $line++) {
                $product = $products->random();
                $service = $services->random();
                $rate = RateChart::where('branch_id', $branch->id)->where('product_id', $product->id)->where('laundry_service_id', $service->id)->first();
                $quantity = fake()->numberBetween(1, 5);
                $unitPrice = (float) ($rate?->price ?? fake()->randomFloat(2, 3, 60));
                $lineTotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineTotal;
                $garmentCount += $quantity;

                $item = $order->items()->create([
                    'product_id' => $product->id,
                    'laundry_service_id' => $service->id,
                    'rate_chart_id' => $rate?->id,
                    'item_name' => $product->name.' + '.$service->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'tax_amount' => 0,
                    'status' => $status,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                for ($tagNo = 1; $tagNo <= $quantity; $tagNo++) {
                    $tagCode = 'TST-TAG-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT).'-'.str_pad((string) $line, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) $tagNo, 2, '0', STR_PAD_LEFT);
                    GarmentTag::create([
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'tag_code' => $tagCode,
                        'garment_type' => $product->name,
                        'color' => fake()->safeColorName(),
                        'brand' => fake()->optional()->company(),
                        'size' => fake()->randomElement(['S', 'M', 'L', 'XL', 'Free Size']),
                        'gender' => fake()->randomElement(['Male', 'Female', 'Unisex', 'Kids']),
                        'condition' => fake()->randomElement(['Good', 'Stained', 'Torn', 'Delicate']),
                        'barcode_payload' => $tagCode,
                        'status' => fake()->randomElement(['received', 'washing', 'drying', 'ironing', 'packaging', 'ready', 'delivered']),
                        'is_scanned' => fake()->boolean(82),
                        'last_scanned_at' => fake()->optional(.8)->dateTimeBetween($createdAt, 'now'),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }

            $total = round($subtotal + (float) $order->delivery_fee, 2);
            $paid = match ($paymentStatus) {
                'paid' => $total,
                'part_paid' => round($total * fake()->randomFloat(2, .25, .75), 2),
                default => 0,
            };

            $order->update([
                'expected_garment_count' => $garmentCount,
                'subtotal' => $subtotal,
                'total' => $total,
                'total_amount' => $total,
                'amount_paid' => $paid,
                'balance' => max($total - $paid, 0),
                'garment_closed_at' => in_array($status, ['ready', 'delivered'], true) ? $createdAt->copy()->addDays(1) : null,
            ]);

            if ($paid > 0) {
                Payment::create($this->paymentPayload([
                    'branch_id' => $branch->id,
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'received_by' => $cashier->id,
                    'payment_number' => 'TST-PAY-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'receipt_number' => 'TST-RCT-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'receipt_no' => 'TST-RCT-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'payment_method' => fake()->randomElement(['cash', 'mobile_money', 'bank_transfer', 'pos_card']),
                    'method' => 'cash',
                    'status' => 'settled',
                    'amount' => $paid,
                    'change_due' => 0,
                    'paid_at' => $createdAt->copy()->addHours(1),
                    'reference' => 'TEST-'.$i,
                    'notes' => 'Generated test payment',
                    'created_at' => $createdAt->copy()->addHours(1),
                    'updated_at' => $createdAt->copy()->addHours(1),
                ]));
            }
        }

        return Order::query()
            ->where('branch_id', $branch->id)
            ->where('order_number', 'like', 'TST-ORD-%')
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function paymentPayload(array $payload): array
    {
        return collect($payload)
            ->filter(fn (mixed $value, string $column): bool => Schema::hasColumn('payments', $column))
            ->all();
    }

    private function seedSubscriptions(Branch $branch, array $customers): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        foreach (array_slice($customers, 0, 120) as $index => $customer) {
            DB::table('subscriptions')->updateOrInsert(
                ['subscription_number' => 'TST-SUB-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT)],
                [
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'package_id' => null,
                    'starts_at' => now()->subMonths(fake()->numberBetween(0, 6))->toDateString(),
                    'ends_at' => now()->addDays(fake()->numberBetween(1, 90))->toDateString(),
                    'usage_remaining' => fake()->numberBetween(0, 30),
                    'status' => fake()->randomElement(['active', 'active', 'expired']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        if (Schema::hasTable('customer_subscriptions') && Schema::hasTable('subscription_plans')) {
            $planId = $this->testSubscriptionPlanId($branch);

            foreach (array_slice($customers, 0, 120) as $index => $customer) {
                DB::table('customer_subscriptions')->updateOrInsert(
                    ['customer_id' => $customer->id, 'subscription_plan_id' => $planId],
                    $this->tablePayload('customer_subscriptions', [
                        'branch_id' => $branch->id,
                        'subscription_no' => 'TST-CSUB-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                        'starts_at' => now()->subMonth(),
                        'ends_at' => now()->addDays(fake()->numberBetween(1, 90)),
                        'washes_remaining' => fake()->numberBetween(0, 20),
                        'allowance' => ['washes_remaining' => fake()->numberBetween(0, 20)],
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]),
                );
            }
        }
    }

    private function testSubscriptionPlanId(Branch $branch): int
    {
        $now = now();
        $payload = [
            'branch_id' => $branch->id,
            'code' => 'TST-PREMIUM',
            'name' => 'Test Premium',
            'billing_cycle' => 'monthly',
            'price' => 250,
            'wash_limit' => 30,
            'validity_days' => 30,
            'pickup_limit' => 8,
            'discount_percent' => 10,
            'turnaround_hours' => 24,
            'features' => json_encode(['Priority processing', 'Door pickup']),
            'is_active' => true,
            'validity_months' => 1,
            'usage_limit' => 30,
            'pickup_included' => true,
            'amount' => 250,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $identity = Schema::hasColumn('subscription_plans', 'code')
            ? ['code' => 'TST-PREMIUM']
            : ['name' => 'Test Premium'];

        DB::table('subscription_plans')->updateOrInsert(
            $identity,
            $this->tablePayload('subscription_plans', $payload),
        );

        $query = DB::table('subscription_plans');

        foreach ($identity as $column => $value) {
            $query->where($column, $value);
        }

        return (int) $query->value('id');
    }

    private function tablePayload(string $table, array $payload): array
    {
        return collect($payload)
            ->filter(fn (mixed $value, string $column): bool => Schema::hasColumn($table, $column))
            ->map(fn (mixed $value): mixed => is_array($value) ? json_encode($value) : $value)
            ->all();
    }

    private function seedPickupDelivery(Branch $branch, array $orders, array $users): void
    {
        $deliveryStaff = collect($users)->firstWhere('email', 'delivery@jumpwash.test') ?? $users[0];

        foreach (array_slice($orders, 0, 500) as $index => $order) {
            PickupDeliveryTask::updateOrCreate(
                ['branch_id' => $branch->id, 'order_id' => $order->id, 'type' => $index % 2 === 0 ? 'door_pickup' : 'door_delivery'],
                [
                    'customer_id' => $order->customer_id,
                    'assigned_to' => $deliveryStaff->id,
                    'type' => $index % 2 === 0 ? 'door_pickup' : 'door_delivery',
                    'status' => $index % 2 === 0 ? fake()->randomElement(['scheduled', 'picked_up', 'completed']) : fake()->randomElement(['pending', 'assigned', 'out_for_delivery', 'delivered']),
                    'scheduled_at' => now()->addDays(fake()->numberBetween(-7, 21))->setTime(fake()->numberBetween(8, 18), 0),
                    'completed_at' => fake()->optional(.35)->dateTimeBetween('-7 days', 'now'),
                    'address' => $order->customer?->address ?? fake()->address(),
                    'signature_data' => fake()->optional(.25)->name(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function seedNotifications(Branch $branch, array $users, array $orders): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        foreach (array_slice($orders, 0, 150) as $index => $order) {
            AppNotification::updateOrCreate(
                ['title' => 'Test Notification '.$order->order_number],
                [
                    'branch_id' => $branch->id,
                    'user_id' => $users[$index % count($users)]->id,
                    'notifiable_subject_type' => Order::class,
                    'notifiable_subject_id' => $order->id,
                    'type' => fake()->randomElement(['new_order', 'ready_for_delivery', 'outstanding_balance', 'expiring_subscription']),
                    'channel' => 'local',
                    'message' => 'Generated test notification for '.$order->order_number,
                    'status' => fake()->randomElement(['unread', 'read']),
                    'read_at' => fake()->optional(.4)->dateTimeBetween('-10 days', 'now'),
                    'scheduled_at' => now(),
                    'sent_at' => now(),
                    'payload' => ['order_number' => $order->order_number],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function seedCalendarEvents(Branch $branch, array $orders): void
    {
        if (! Schema::hasTable('calendar_events')) {
            return;
        }

        foreach (array_slice($orders, 0, 180) as $index => $order) {
            DB::table('calendar_events')->updateOrInsert(
                ['title' => 'Test Event '.$order->order_number],
                [
                    'branch_id' => $branch->id,
                    'eventable_type' => Order::class,
                    'eventable_id' => $order->id,
                    'category' => fake()->randomElement(['Pickup Schedule', 'Delivery Schedule', 'Staff Assignments', 'Subscription Expiry']),
                    'starts_at' => now()->addDays(fake()->numberBetween(-14, 30))->setTime(fake()->numberBetween(8, 18), 0),
                    'ends_at' => now()->addDays(fake()->numberBetween(-14, 30))->setTime(fake()->numberBetween(9, 19), 0),
                    'all_day' => false,
                    'color' => fake()->randomElement(['#2563eb', '#16a34a', '#7c3aed', '#dc2626']),
                    'meta' => json_encode(['order_number' => $order->order_number]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function seedOperationalRecords(Branch $branch, array $users, array $orders): void
    {
        foreach (array_slice($orders, 0, 250) as $index => $order) {
            ActivityLog::updateOrCreate(
                ['action' => 'test.seeded', 'subject_type' => Order::class, 'subject_id' => $order->id],
                [
                    'branch_id' => $branch->id,
                    'user_id' => $users[$index % count($users)]->id,
                    'module' => 'test_data',
                    'properties' => ['order_number' => $order->order_number],
                    'old_values' => null,
                    'new_values' => ['status' => $order->status],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'TestDataSeeder',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        if (Schema::hasTable('backup_records')) {
            foreach (range(1, 10) as $i) {
                BackupRecord::updateOrCreate(
                    ['backup_number' => 'TST-BKP-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT)],
                    [
                        'branch_id' => $branch->id,
                        'created_by' => $users[0]->id,
                        'type' => fake()->randomElement(['database', 'full_system']),
                        'mode' => fake()->randomElement(['manual', 'scheduled']),
                        'target' => fake()->randomElement(['local', 'usb', 'network_folder']),
                        'target_path' => null,
                        'file_path' => 'backups/test/tst-'.$i.'.zip',
                        'file_size' => fake()->numberBetween(100000, 8000000),
                        'status' => 'completed',
                        'scheduled_at' => now()->subDays($i),
                        'completed_at' => now()->subDays($i)->addMinutes(5),
                        'notes' => 'Generated test backup record',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
}
