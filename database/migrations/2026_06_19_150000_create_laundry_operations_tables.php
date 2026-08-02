<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createTableIfMissing('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
                }

                if (! Schema::hasColumn('users', 'phone')) {
                    $table->string('phone')->nullable()->after('email');
                }

                if (! Schema::hasColumn('users', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('password');
                }
            });
        }

        $this->createTableIfMissing('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('customer_code')->unique();
            $table->string('name');
            $table->string('phone')->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tag')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->createTableIfMissing('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code')->unique();
            $table->string('position');
            $table->date('hired_at')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('laundry_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->index();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('unit')->default('kg');
            $table->unsignedInteger('turnaround_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
        });

        $this->createTableIfMissing('package_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('max_weight', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->createTableIfMissing('service_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->createTableIfMissing('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->createTableIfMissing('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('wash_limit');
            $table->unsignedInteger('validity_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->createTableIfMissing('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('washes_remaining');
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        $this->createTableIfMissing('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_laundry_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('received')->index();
            $table->string('payment_status')->default('unpaid')->index();
            $table->string('billing_source')->default('cash');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laundry_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->string('status')->default('received')->index();
            $table->timestamps();
        });

        $this->createTableIfMissing('garment_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('tag_code')->unique();
            $table->string('barcode_payload');
            $table->string('status')->default('tagged')->index();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('method')->default('cash');
            $table->decimal('amount', 10, 2);
            $table->decimal('change_due', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('pickup_delivery_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('scheduled')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('address')->nullable();
            $table->text('signature_data')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->timestamps();
        });

        $this->createTableIfMissing('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
            $table->unique(['branch_id', 'key']);
        });

        $this->createTableIfMissing('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module');
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('pickup_delivery_tasks');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('garment_tags');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('service_addons');
        Schema::dropIfExists('package_types');
        Schema::dropIfExists('laundry_services');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }

    private function createTableIfMissing(string $tableName, callable $callback): void
    {
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, $callback);
        }
    }
};
