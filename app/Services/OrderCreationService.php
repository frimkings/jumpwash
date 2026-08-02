<?php

namespace App\Services;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Models\ActivityLog;
use App\Models\LaundryService;
use App\Models\Order;
use App\Models\Product;
use App\Models\RateChart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderCreationService
{
    public function __construct(private readonly OrderRepositoryInterface $orders)
    {
    }

    public function create(array $payload): Order
    {
        return DB::transaction(function () use ($payload): Order {
            $rows = collect($payload['rows']);
            $taxRate = (float) ($payload['tax_rate'] ?? 0);
            $subtotal = $rows->sum(fn (array $row) => round((float) $row['quantity'] * (float) $row['unit_price'], 2));
            $tax = round($subtotal * ($taxRate / 100), 2);
            $discount = min(round((float) ($payload['discount'] ?? 0), 2), round($subtotal + $tax, 2));
            $total = round(max(($subtotal + $tax) - $discount, 0), 2);
            $paid = $total <= 0 && ($payload['billing_source'] ?? 'cash') === 'subscription' ? 0 : 0;
            $orderData = [
                'branch_id' => $payload['branch_id'],
                'customer_id' => $payload['customer_id'],
                'created_by' => $payload['created_by'] ?? null,
                'order_number' => $this->orders->nextOrderNumber($payload['branch_id']),
                'status' => $payload['status'],
                'payment_status' => $total <= 0 ? 'paid' : 'unpaid',
                'billing_source' => $payload['billing_source'] ?? 'cash',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => 0,
                'total' => $total,
                'total_amount' => $total,
                'amount_paid' => $paid,
                'balance' => $total,
                'notes' => $payload['notes'] ?? null,
            ];

            if (Schema::hasColumn('orders', 'customer_subscription_id')) {
                $orderData['customer_subscription_id'] = $payload['customer_subscription_id'] ?? null;
            }

            $order = Order::create($orderData);

            foreach ($rows as $row) {
                $product = Product::find($row['product_id']);
                $service = LaundryService::find($row['laundry_service_id']);
                $rate = RateChart::where('product_id', $row['product_id'])
                    ->where('laundry_service_id', $row['laundry_service_id'])
                    ->where(function (Builder $query) use ($payload): void {
                        $query->where('branch_id', $payload['branch_id'])->orWhereNull('branch_id');
                    })
                    ->first();

                $lineTotal = round(((float) $row['quantity']) * ((float) $row['unit_price']), 2);
                $lineTax = round($lineTotal * ($taxRate / 100), 2);
                $originalUnitPrice = $rate ? (float) $rate->price : (float) $row['unit_price'];
                $isOverride = round((float) $row['unit_price'], 2) !== round($originalUnitPrice, 2);

                $order->items()->create([
                    'product_id' => $product?->id,
                    'laundry_service_id' => $service?->id,
                    'rate_chart_id' => $rate?->id,
                    'item_name' => trim(($product?->name ?? 'Product').' + '.($service?->name ?? 'Service')),
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'original_unit_price' => $originalUnitPrice,
                    'price_override_reason' => $isOverride ? ($row['price_override_reason'] ?? null) : null,
                    'price_overridden_by' => $isOverride ? auth()->id() : null,
                    'line_total' => $lineTotal,
                    'tax_amount' => $lineTax,
                    'status' => $payload['status'],
                ]);
            }

            $order->load(['customer', 'items']);

            ActivityLog::record('created', $order, [
                'module' => 'orders',
                'order_number' => $order->order_number,
                'customer' => $order->customer?->name,
            ], [], [
                'status' => $order->status,
                'subtotal' => $order->subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $order->total,
                'items' => $order->items->map(fn ($item) => [
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'original_unit_price' => $item->original_unit_price,
                    'unit_price' => $item->unit_price,
                    'price_override_reason' => $item->price_override_reason,
                    'line_total' => $item->line_total,
                ])->all(),
            ]);

            return $order;
        });
    }
}
