<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('users', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('phone');
            }
        });

        if (! Schema::hasColumn('staff_profiles', 'employee_code')) {
            Schema::table('staff_profiles', function (Blueprint $table) {
                $table->string('employee_code')->nullable()->unique()->after('user_id');
            });

            if (Schema::hasColumn('staff_profiles', 'staff_code')) {
                DB::table('staff_profiles')
                    ->whereNull('employee_code')
                    ->orderBy('id')
                    ->eachById(function (object $profile): void {
                        DB::table('staff_profiles')
                            ->where('id', $profile->id)
                            ->update(['employee_code' => $profile->staff_code]);
                    });
            }
        }

        Schema::table('staff_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_profiles', 'position')) {
                $table->string('position')->nullable()->after('employee_code');
            }

            if (! Schema::hasColumn('staff_profiles', 'emergency_contact')) {
                $table->string('emergency_contact')->nullable()->after('position');
            }

            if (! Schema::hasColumn('staff_profiles', 'vehicle')) {
                $table->string('vehicle')->nullable()->after('position');
            }

            if (! Schema::hasColumn('staff_profiles', 'license_number')) {
                $table->string('license_number')->nullable()->after('vehicle');
            }

            if (! Schema::hasColumn('staff_profiles', 'availability')) {
                $table->string('availability')->default('available')->after('license_number');
            }
        });
    }

    public function down(): void
    {
        $profileColumns = array_filter(
            ['vehicle', 'license_number', 'availability', 'emergency_contact', 'position', 'employee_code'],
            fn (string $column): bool => Schema::hasColumn('staff_profiles', $column)
        );

        if ($profileColumns !== []) {
            Schema::table('staff_profiles', function (Blueprint $table) use ($profileColumns) {
                $table->dropColumn($profileColumns);
            });
        }

        $userColumns = array_filter(
            ['first_name', 'last_name', 'photo_path'],
            fn (string $column): bool => Schema::hasColumn('users', $column)
        );

        if ($userColumns !== []) {
            Schema::table('users', function (Blueprint $table) use ($userColumns) {
                $table->dropColumn($userColumns);
            });
        }
    }
};
