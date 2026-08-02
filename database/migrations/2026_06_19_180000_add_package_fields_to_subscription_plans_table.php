<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->foreignId('laundry_service_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('validity_months')->default(1)->after('price');
            $table->unsignedInteger('usage_limit')->default(0)->after('validity_months');
            $table->boolean('pickup_included')->default(false)->after('usage_limit');
            $table->decimal('amount', 10, 2)->default(0)->after('pickup_included');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('laundry_service_id');
            $table->dropColumn(['validity_months', 'usage_limit', 'pickup_included', 'amount']);
        });
    }
};
