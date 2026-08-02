<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $receiptNumber }}</title>
    <style>
        @page { margin: 0; size: 80mm auto; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 80mm; }
        body { background: #f4f4f5; color: #111; font-family: "Courier New", monospace; font-size: 11px; }
        .receipt-shell { margin: 0; min-height: 100vh; padding: 12px 0 12px 0; width: 80mm; }
        .receipt { background: #fff; margin: 0; padding: 8px; width: 72mm; }
        .center { text-align: center; }
        .logo { display: block; margin: 0 auto 4px; max-height: 42px; max-width: 54mm; object-fit: contain; }
        h1 { font-size: 15px; line-height: 1.15; margin: 2px 0; text-transform: uppercase; }
        p { margin: 2px 0; }
        .line { border-top: 1px dashed #111; margin: 7px 0; }
        .duplicate { border: 1px solid #111; display: inline-block; font-weight: 700; margin-top: 4px; padding: 2px 5px; }
        .row { display: flex; gap: 6px; justify-content: space-between; }
        .row span:first-child { min-width: 0; overflow-wrap: anywhere; }
        .row strong { white-space: nowrap; }
        .meta { display: grid; gap: 2px; }
        .items { width: 100%; }
        .items__head, .items__row { display: grid; gap: 3px; grid-template-columns: minmax(0, 1fr) 18px 42px 48px; }
        .items__head { font-weight: 700; }
        .items__row { margin-top: 3px; }
        .num { text-align: right; }
        .totals { display: grid; gap: 2px; }
        .total-row { font-size: 13px; font-weight: 700; }
        .actions { display: flex; gap: 6px; justify-content: center; margin: 12px auto; width: 72mm; }
        .actions button, .actions a { background: #111; border: 0; border-radius: 4px; color: #fff; cursor: pointer; font-family: Arial, sans-serif; font-size: 12px; font-weight: 700; padding: 7px 9px; text-decoration: none; }
        .muted-button { background: #52525b !important; }
        @media print {
            html, body { background: #fff; margin: 0 !important; padding: 0 !important; width: 80mm !important; }
            .receipt-shell { margin: 0 !important; padding: 0 !important; width: 80mm !important; }
            .receipt { box-shadow: none; margin: 0 !important; padding: 0 4mm 0 0; width: 80mm !important; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <main class="receipt-shell">
        <section class="receipt">
            <header class="center">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" class="logo" alt="Organization logo">
                @endif
                <h1>{{ $settings['organization_name'] ?? config('app.name', 'JumpWash') }}</h1>
                <p>{{ $settings['phone'] ?? '' }}</p>
                <p>{{ $settings['address'] ?? '' }}</p>
                @if ($isDuplicate)
                    <span class="duplicate">DUPLICATE COPY</span>
                @endif
            </header>

            <div class="line"></div>

            <section class="meta">
                <div class="row"><span>Receipt No</span><strong>{{ $receiptNumber }}</strong></div>
                <div class="row"><span>Order No</span><strong>{{ $order->order_number }}</strong></div>
                <div class="row"><span>Customer</span><strong>{{ $order->customer?->name ?? 'Walk-in' }}</strong></div>
                <div class="row"><span>Customer No</span><strong>{{ $customerNumber }}</strong></div>
                <div class="row"><span>Date</span><strong>{{ now()->format('M d, Y h:i A') }}</strong></div>
                <div class="row"><span>Cashier</span><strong>{{ $cashier }}</strong></div>
            </section>

            <div class="line"></div>

            <section class="items">
                <div class="items__head">
                    <span>Item</span>
                    <span class="num">Qty</span>
                    <span class="num">Rate</span>
                    <span class="num">Amount</span>
                </div>
                @foreach ($order->items as $item)
                    <div class="items__row">
                        <span>{{ $item->item_name }}</span>
                        <span class="num">{{ number_format((float) $item->quantity, 0) }}</span>
                        <span class="num">{{ number_format((float) $item->unit_price, 2) }}</span>
                        <span class="num">{{ number_format((float) $item->line_total, 2) }}</span>
                    </div>
                @endforeach
            </section>

            <div class="line"></div>

            <section class="totals">
                <div class="row"><span>Subtotal</span><strong>GHS {{ number_format((float) $order->subtotal, 2) }}</strong></div>
                <div class="row"><span>Tax</span><strong>GHS {{ number_format((float) $tax, 2) }}</strong></div>
                <div class="row total-row"><span>Total</span><strong>GHS {{ number_format((float) ($order->total_amount ?: $order->total), 2) }}</strong></div>
                <div class="row"><span>Paid</span><strong>GHS {{ number_format((float) $paid, 2) }}</strong></div>
                <div class="row"><span>Balance</span><strong>GHS {{ number_format((float) $balance, 2) }}</strong></div>
                <div class="row"><span>Status</span><strong>{{ $status }}</strong></div>
            </section>

            <div class="line"></div>

            <footer class="center">
                <p><strong>{{ $settings['receipt_footer'] ?? 'Thank you for your business.' }}</strong></p>
                <p>{{ $settings['terms_conditions'] ?? 'Terms and conditions apply.' }}</p>
            </footer>
        </section>

        @unless ($embedded ?? false)
            <nav class="actions">
                <button type="button" onclick="window.print()">Print</button>
                <a href="{{ route('receipts.orders.show', ['order' => $order, 'print' => 1]) }}">Reprint</a>
                <a class="muted-button" href="{{ route('receipts.orders.show', ['order' => $order, 'duplicate' => 1]) }}">Duplicate Copy</a>
            </nav>
        @endunless
    </main>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
</body>
</html>
