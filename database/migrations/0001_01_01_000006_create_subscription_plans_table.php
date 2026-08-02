<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('billing_cycle');
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('pickup_limit')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedInteger('turnaround_hours')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};