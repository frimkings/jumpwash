<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('customers', ['branch_id', 'is_active', 'name'], 'customers_branch_active_name_idx');
        $this->addIndex('customers', ['branch_id', 'phone'], 'customers_branch_phone_idx');
        $this->addIndex('customers', ['branch_id', 'created_at'], 'customers_branch_created_idx');

        $this->addIndex('orders', ['branch_id', 'created_at'], 'orders_branch_created_idx');
        $this->addIndex('orders', ['branch_id', 'status', 'created_at'], 'orders_branch_status_created_idx');
        $this->addIndex('orders', ['branch_id', 'payment_status', 'created_at'], 'orders_branch_pay_status_created_idx');
        $this->addIndex('orders', ['branch_id', 'customer_id', 'created_at'], 'orders_branch_customer_created_idx');
        $this->addIndex('orders', ['branch_id', 'order_number'], 'orders_branch_number_idx');
        $this->addIndex('orders', ['branch_id', 'due_at'], 'orders_branch_due_idx');

        $this->addIndex('order_items', ['order_id', 'laundry_service_id'], 'order_items_order_service_idx');
        $this->addIndex('order_items', ['order_id', 'product_id'], 'order_items_order_product_idx');
        $this->addIndex('order_items', ['laundry_service_id', 'status'], 'order_items_service_status_idx');
        $this->addIndex('order_items', ['product_id', 'status'], 'order_items_product_status_idx');

        $this->addIndex('garment_tags', ['order_id', 'status'], 'garment_tags_order_status_idx');
        $this->addIndex('garment_tags', ['order_id', 'is_scanned'], 'garment_tags_order_scanned_idx');
        $this->addIndex('garment_tags', ['status', 'is_scanned'], 'garment_tags_status_scanned_idx');
        $this->addIndex('garment_tags', ['last_scanned_at'], 'garment_tags_last_scanned_idx');

        $this->addIndex('payments', ['branch_id', 'created_at'], 'payments_branch_created_idx');
        $this->addIndex('payments', ['branch_id', 'payment_method', 'created_at'], 'payments_branch_method_created_idx');
        $this->addIndex('payments', ['order_id', 'created_at'], 'payments_order_created_idx');
        $this->addIndex('payments', ['customer_id', 'created_at'], 'payments_customer_created_idx');
        $this->addIndex('payments', ['received_by', 'created_at'], 'payments_receiver_created_idx');

        $this->addIndex('pickup_delivery_tasks', ['branch_id', 'type', 'status', 'scheduled_at'], 'tasks_branch_type_status_sched_idx');
        $this->addIndex('pickup_delivery_tasks', ['branch_id', 'assigned_to', 'status', 'scheduled_at'], 'tasks_branch_assigned_status_sched_idx');
        $this->addIndex('pickup_delivery_tasks', ['order_id', 'type'], 'tasks_order_type_idx');
        $this->addIndex('pickup_delivery_tasks', ['customer_id', 'type'], 'tasks_customer_type_idx');

        $this->addIndex('customer_subscriptions', ['customer_id', 'status', 'ends_at'], 'cust_sub_customer_status_ends_idx');
        $this->addIndex('subscriptions', ['branch_id', 'status', 'ends_at'], 'subscriptions_branch_status_ends_idx');
        $this->addIndex('subscriptions', ['customer_id', 'status'], 'subscriptions_customer_status_idx');

        $this->addIndex('activity_logs', ['branch_id', 'created_at'], 'activity_logs_branch_created_idx');
        $this->addIndex('activity_logs', ['branch_id', 'action', 'created_at'], 'activity_logs_branch_action_created_idx');
        $this->addIndex('notifications', ['branch_id', 'status', 'created_at'], 'notifications_branch_status_created_idx');
        $this->addIndex('calendar_events', ['branch_id', 'category', 'starts_at'], 'calendar_branch_category_start_idx');
    }

    public function down(): void
    {
        foreach ([
            'customers' => ['customers_branch_active_name_idx', 'customers_branch_phone_idx', 'customers_branch_created_idx'],
            'orders' => ['orders_branch_created_idx', 'orders_branch_status_created_idx', 'orders_branch_pay_status_created_idx', 'orders_branch_customer_created_idx', 'orders_branch_number_idx', 'orders_branch_due_idx'],
            'order_items' => ['order_items_order_service_idx', 'order_items_order_product_idx', 'order_items_service_status_idx', 'order_items_product_status_idx'],
            'garment_tags' => ['garment_tags_order_status_idx', 'garment_tags_order_scanned_idx', 'garment_tags_status_scanned_idx', 'garment_tags_last_scanned_idx'],
            'payments' => ['payments_branch_created_idx', 'payments_branch_method_created_idx', 'payments_order_created_idx', 'payments_customer_created_idx', 'payments_receiver_created_idx'],
            'pickup_delivery_tasks' => ['tasks_branch_type_status_sched_idx', 'tasks_branch_assigned_status_sched_idx', 'tasks_order_type_idx', 'tasks_customer_type_idx'],
            'customer_subscriptions' => ['cust_sub_customer_status_ends_idx'],
            'subscriptions' => ['subscriptions_branch_status_ends_idx', 'subscriptions_customer_status_idx'],
            'activity_logs' => ['activity_logs_branch_created_idx', 'activity_logs_branch_action_created_idx'],
            'notifications' => ['notifications_branch_status_created_idx'],
            'calendar_events' => ['calendar_branch_category_start_idx'],
        ] as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (Throwable) {
                        //
                    }
                }
            });
        }
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        try {
            return collect(Schema::getIndexes($table))->contains(fn (array $index) => ($index['name'] ?? null) === $name);
        } catch (Throwable) {
            return false;
        }
    }
};
