<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->foreignId('rate_chart_id')->nullable()->after('package_type_id')->constrained()->nullOnDelete();
            $table->decimal('tax_amount', 10, 2)->default(0)->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('rate_chart_id');
            $table->dropColumn('tax_amount');
        });
    }
};
