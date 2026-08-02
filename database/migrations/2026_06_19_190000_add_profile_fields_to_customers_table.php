<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'customer_code')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('customer_code')->nullable()->unique()->after('branch_id');
            });

            if (Schema::hasColumn('customers', 'code')) {
                DB::table('customers')
                    ->whereNull('customer_code')
                    ->orderBy('id')
                    ->eachById(function (object $customer): void {
                        DB::table('customers')
                            ->where('id', $customer->id)
                            ->update(['customer_code' => $customer->code]);
                    });
            }
        }

        if (Schema::hasColumn('customers', 'code') && Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE customers MODIFY code VARCHAR(255) NULL');
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'first_name')) {
                $table->string('first_name')->nullable()->after('customer_code');
            }

            if (! Schema::hasColumn('customers', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('customers', 'gps_location')) {
                $table->string('gps_location')->nullable()->after('address');
            }

            if (! Schema::hasColumn('customers', 'notes')) {
                $table->text('notes')->nullable()->after('gps_location');
            }

            if (! Schema::hasColumn('customers', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        $columns = array_filter(
            ['first_name', 'last_name', 'gps_location', 'photo_path', 'customer_code'],
            fn (string $column): bool => Schema::hasColumn('customers', $column)
        );

        if ($columns !== []) {
            Schema::table('customers', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
