<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country')->nullable();
                $table->string('logo_path')->nullable();
                $table->text('receipt_footer')->nullable();
                $table->text('terms_conditions')->nullable();
                $table->decimal('tax_percentage', 5, 2)->default(0);
                $table->string('currency', 10)->default('GHS');
                $table->text('business_hours')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('label')->default('Primary');
                $table->text('address');
                $table->string('gps_location')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('staff_number')->nullable()->index();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('role')->nullable();
                $table->string('vehicle')->nullable();
                $table->string('license_number')->nullable();
                $table->string('availability')->default('available');
                $table->string('photo_path')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rates')) {
            Schema::create('rates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('laundry_service_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('price', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['branch_id', 'product_id', 'laundry_service_id'], 'rates_branch_product_service_unique');
            });
        }

        if (! Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('laundry_service_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->unsignedInteger('validity_months')->default(1);
                $table->unsignedInteger('usage_limit')->default(0);
                $table->boolean('pickup_included')->default(false);
                $table->decimal('amount', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
                $table->string('subscription_number')->nullable()->unique();
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->unsignedInteger('usage_remaining')->default(0);
                $table->string('status')->default('active')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('garment_status_history')) {
            Schema::create('garment_status_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('garment_tag_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('changed_at')->useCurrent();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pickups')) {
            Schema::create('pickups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pickup_delivery_task_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type')->default('door_pickup');
                $table->string('status')->default('scheduled')->index();
                $table->timestamp('scheduled_at')->nullable();
                $table->text('address')->nullable();
                $table->string('signature_path')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delivery_assignments')) {
            Schema::create('delivery_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pickup_delivery_task_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('delivery_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->string('status')->default('assigned')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('calendar_events')) {
            Schema::create('calendar_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->nullableMorphs('eventable');
                $table->string('title');
                $table->string('category')->index();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('all_day')->default(false);
                $table->string('color')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->nullableMorphs('notifiable_subject', 'notifications_subject_idx');
                $table->string('type')->index();
                $table->string('channel')->default('local')->index();
                $table->string('title');
                $table->text('message');
                $table->string('status')->default('unread')->index();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'notifications',
            'calendar_events',
            'delivery_assignments',
            'pickups',
            'garment_status_history',
            'subscriptions',
            'packages',
            'rates',
            'staff',
            'customer_addresses',
            'organizations',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
