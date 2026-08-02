<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ThermalReceiptController extends Controller
{
    public function show(Request $request, Order $order)
    {
        abort_unless($order->branch_id === auth()->user()?->branch_id, 403);

        $order->load(['customer', 'items', 'payments.receiver']);

        $settings = Setting::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->pluck('value', 'key');

        $latestPayment = $order->payments->sortByDesc('created_at')->first();
        $receiptNumber = $latestPayment?->payment_number
            ?? $latestPayment?->receipt_number
            ?? $latestPayment?->receipt_no
            ?? 'RCPT-'.$order->order_number;

        $customerNumber = $order->customer?->customer_code ?? $order->customer?->code ?? 'N/A';
        $total = (float) ($order->total_amount ?: $order->total);
        $paid = (float) $order->payments->sum('amount');
        $balance = max($total - $paid, 0);
        $status = $paid <= 0 ? 'Unpaid' : ($balance <= 0 ? 'Paid' : 'Part Paid');

        if ($request->boolean('print') || $request->boolean('duplicate')) {
            ActivityLog::record('printed', $order, [
                'module' => 'receipts',
                'receipt_number' => $receiptNumber,
                'order_number' => $order->order_number,
                'duplicate' => $request->boolean('duplicate'),
                'auto_print' => $request->boolean('print'),
            ], [], [
                'customer_number' => $customerNumber,
                'amount' => number_format($total, 2, '.', ''),
                'status' => $status,
            ]);
        }

        return view('receipts.thermal-80mm', [
            'order' => $order,
            'settings' => $settings,
            'receiptNumber' => $receiptNumber,
            'cashier' => $latestPayment?->receiver?->name ?? auth()->user()?->name ?? 'Cashier',
            'customerNumber' => $customerNumber,
            'paid' => $paid,
            'balance' => $balance,
            'status' => $status,
            'tax' => max($total - (float) $order->subtotal, 0),
            'isDuplicate' => $request->boolean('duplicate'),
            'autoPrint' => $request->boolean('print'),
            'embedded' => $request->boolean('embed'),
            'logoUrl' => ($settings['logo_path'] ?? null) ? Storage::url($settings['logo_path']) : null,
        ]);
    }
}
