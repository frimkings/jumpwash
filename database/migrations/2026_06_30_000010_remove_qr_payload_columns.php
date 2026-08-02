<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('garments') && Schema::hasColumn('garments', 'qr_payload')) {
            Schema::table('garments', function (Blueprint $table): void {
                $table->dropColumn('qr_payload');
            });
        }

        if (Schema::hasTable('garment_tags') && Schema::hasColumn('garment_tags', 'qr_payload')) {
            Schema::table('garment_tags', function (Blueprint $table): void {
                $table->dropColumn('qr_payload');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('garments') && ! Schema::hasColumn('garments', 'qr_payload')) {
            Schema::table('garments', function (Blueprint $table): void {
                $table->string('qr_payload')->nullable()->after('garment_code');
            });
        }

        if (Schema::hasTable('garment_tags') && ! Schema::hasColumn('garment_tags', 'qr_payload')) {
            Schema::table('garment_tags', function (Blueprint $table): void {
                $table->string('qr_payload')->nullable()->after('barcode_payload');
            });
        }
    }
};
