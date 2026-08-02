<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('expected_garment_count')->default(0)->after('payment_status');
            $table->timestamp('garment_closed_at')->nullable()->after('completed_at');
        });

        Schema::table('garment_tags', function (Blueprint $table) {
            $table->string('garment_type')->nullable()->after('tag_code');
            $table->string('color')->nullable()->after('garment_type');
            $table->string('brand')->nullable()->after('color');
            $table->string('size')->nullable()->after('brand');
            $table->string('gender')->nullable()->after('size');
            $table->string('condition')->nullable()->after('gender');
            $table->boolean('is_scanned')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('garment_tags', function (Blueprint $table) {
            $table->dropColumn(['garment_type', 'color', 'brand', 'size', 'gender', 'condition', 'is_scanned']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['expected_garment_count', 'garment_closed_at']);
        });
    }
};
