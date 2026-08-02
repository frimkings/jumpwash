<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'original_unit_price')) {
                $table->decimal('original_unit_price', 10, 2)->nullable()->after('unit_price');
            }

            if (! Schema::hasColumn('order_items', 'price_override_reason')) {
                $table->string('price_override_reason')->nullable()->after('original_unit_price');
            }

            if (! Schema::hasColumn('order_items', 'price_overridden_by')) {
                $table->foreignId('price_overridden_by')->nullable()->after('price_override_reason')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'price_overridden_by')) {
                $table->dropConstrainedForeignId('price_overridden_by');
            }

            if (Schema::hasColumn('order_items', 'price_override_reason')) {
                $table->dropColumn('price_override_reason');
            }

            if (Schema::hasColumn('order_items', 'original_unit_price')) {
                $table->dropColumn('original_unit_price');
            }
        });
    }
};
