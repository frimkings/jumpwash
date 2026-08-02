<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('properties');
            }

            if (! Schema::hasColumn('activity_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }

            if (! Schema::hasColumn('activity_logs', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('new_values');
            }

            if (! Schema::hasColumn('activity_logs', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });

        if (! Schema::hasTable('backup_records')) {
            Schema::create('backup_records', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('backup_number')->unique();
                $table->string('type')->index();
                $table->string('mode')->default('manual')->index();
                $table->string('target')->default('local')->index();
                $table->string('target_path')->nullable();
                $table->string('file_path');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('status')->default('completed')->index();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');

        Schema::table('activity_logs', function (Blueprint $table): void {
            foreach (['user_agent', 'ip_address', 'new_values', 'old_values'] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
