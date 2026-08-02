<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_delivery_tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('pickup_delivery_tasks', 'pickup_signature_path')) {
                $table->string('pickup_signature_path')->nullable()->after('signature_data');
            }

            if (! Schema::hasColumn('pickup_delivery_tasks', 'delivery_signature_path')) {
                $table->string('delivery_signature_path')->nullable()->after('pickup_signature_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pickup_delivery_tasks', function (Blueprint $table): void {
            foreach (['delivery_signature_path', 'pickup_signature_path'] as $column) {
                if (Schema::hasColumn('pickup_delivery_tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
