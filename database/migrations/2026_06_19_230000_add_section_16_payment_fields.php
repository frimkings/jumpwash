<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'payment_number')) {
                $table->string('payment_number')->nullable()->after('id')->index();
            }

            if (! Schema::hasColumn('payments', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('payment_number')->index();
            }

            if (! Schema::hasColumn('payments', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('amount')->index();
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0)->after('total');
            }

            if (! Schema::hasColumn('orders', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->default(0)->after('total_amount');
            }

            if (! Schema::hasColumn('orders', 'balance')) {
                $table->decimal('balance', 12, 2)->default(0)->after('amount_paid');
            }
        });

        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                $paid = Schema::hasColumn('payments', 'order_id')
                    ? (float) DB::table('payments')->where('order_id', $order->id)->sum('amount')
                    : 0.0;
                $total = (float) ($order->total ?? 0);
                $balance = max($total - $paid, 0);

                DB::table('orders')->where('id', $order->id)->update([
                    'total_amount' => $total,
                    'amount_paid' => $paid,
                    'balance' => $balance,
                    'payment_status' => $paid <= 0 ? 'unpaid' : ($balance <= 0 ? 'paid' : 'part_paid'),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            foreach (['balance', 'amount_paid', 'total_amount'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            foreach (['payment_method', 'order_id', 'payment_number'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
