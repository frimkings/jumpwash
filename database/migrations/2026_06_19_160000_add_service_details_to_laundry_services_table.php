<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laundry_services', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->decimal('tax_percentage', 5, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('laundry_services', function (Blueprint $table) {
            $table->dropColumn(['description', 'tax_percentage']);
        });
    }
};
