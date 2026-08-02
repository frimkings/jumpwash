<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('laundry_order_id')->nullable()->constrained('laundry_orders')->nullOnDelete();
            $table->string('garment_code')->unique();
            $table->string('barcode_value')->nullable();
            $table->string('category');
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('fabric')->nullable();
            $table->string('size')->nullable();
            $table->text('condition_notes')->nullable();
            $table->text('stain_notes')->nullable();
            $table->string('status')->default('received');
            $table->string('current_location')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garments');
    }
};
