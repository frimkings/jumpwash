<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LaundryService;
use App\Models\Product;
use App\Models\RateChart;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Branch', 'phone' => '0000000000', 'address' => 'Local LAN Branch', 'is_active' => true],
        );

        $services = collect(['Laundry', 'Dry Cleaning', 'Ironing', 'Starching', 'Express Service'])
            ->map(fn (string $name) => LaundryService::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => str($name)->slug('-')->upper()->toString()],
                ['name' => $name, 'description' => $name.' service', 'price' => 0, 'tax_percentage' => 0, 'unit' => 'piece', 'turnaround_hours' => 24, 'is_active' => true],
            ));

        $products = collect(['Shirt', 'Trousers', 'Suit', 'Dress', 'Blanket', 'Bedsheet'])
            ->map(fn (string $name) => Product::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $name],
                ['description' => $name.' garment type', 'is_active' => true],
            ));

        foreach ($products as $product) {
            foreach ($services as $service) {
                RateChart::firstOrCreate(
                    ['branch_id' => $branch->id, 'product_id' => $product->id, 'laundry_service_id' => $service->id],
                    ['price' => match ($service->name) {
                        'Dry Cleaning' => 25,
                        'Ironing' => 3,
                        'Express Service' => 15,
                        default => 5,
                    }],
                );
            }
        }

        Customer::firstOrCreate(
            ['branch_id' => $branch->id, 'customer_code' => 'CUS-SEED-0001'],
            [
                'code' => 'CUS-SEED-0001',
                'first_name' => 'Walk-In',
                'last_name' => 'Customer',
                'name' => 'Walk-In Customer',
                'phone' => '0000000001',
                'email' => null,
                'address' => 'Local branch counter',
                'is_active' => true,
            ],
        );
    }
}
