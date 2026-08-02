<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('laundry_order_id')->nullable()->constrained('laundry_orders')->nullOnDelete();
            $table->foreignId('customer_subscription_id')->nullable()->constrained('customer_subscriptions')->nullOnDelete();
            $table->string('receipt_no')->unique();
            $table->string('method');
            $table->string('status')->default('settled');
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at')->useCurrent();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};