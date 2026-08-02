# JumpWash Database Schema

This schema supports an offline LAN laundry management system with single-branch deployment and multi-branch-ready data boundaries.

## Core Access

- `users`: login accounts, branch assignment, active flag.
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`: Spatie role-based access control.
- `branches`: shop or branch records.

## Organization

- `organizations`: receipt identity, logo, address, tax, currency, business hours, terms, receipt footer.
- `settings`: branch-scoped key/value operational settings.

## Customers

- `customers`: customer number, profile, contact, address, GPS, photo, notes, active status.
- `customer_addresses`: optional multiple customer addresses.
- `customer_subscriptions` and `subscriptions`: active package usage, expiry, usage remaining.

## Catalog

- `laundry_services`: laundry, dry cleaning, ironing, express services.
- `products`: garment/product types such as shirt, suit, blanket.
- `rate_charts` and `rates`: product + service pricing.
- `packages` and `subscription_plans`: reusable customer subscription packages.

## Orders

- `orders`: order number, customer, state, payment summary, totals, expected garment count, closure timestamp.
- `order_items`: product/service rows, quantity, unit price, tax, line total, item state.
- `payments`: full/part/multiple payments, payment method, reference, cashier, receipt/payment number.

## Garment Tracking

- `garment_tags`: unique garment tag, barcode/QR payload, garment details, workflow state, scan status.
- `garment_status_history`: status transition history per tag/order.

## Pickup And Delivery

- `pickup_delivery_tasks`: operational pickup and delivery queue with assignment, status, schedule, address, signatures.
- `pickups`: canonical pickup records mapped to tasks.
- `deliveries`: legacy/canonical delivery records.
- `delivery_assignments`: assignment history and delivery staff route allocation.
- `delivery_zones`: local route zones and fees.

## Calendar And Notifications

- `calendar_events`: pickup schedule, delivery schedule, staff assignment, subscription expiry events.
- `notifications`: offline notifications, with local channel now and future SMS/WhatsApp channel fields.

## Reporting, Audit, Backup

- `activity_logs`: user action, module, subject, old/new values, IP/user agent, timestamp.
- `backup_records`: database/full-system backup metadata, target, file path, status.
- `expenses`: local cost records for reporting.

## Performance Index Strategy

High-volume tables include composite indexes for branch-scoped filtering, status queues, date ranges, payment summaries, tag scanning, and customer/order searches:

- `orders`: branch/date, branch/status/date, branch/payment status/date, branch/customer/date, branch/order number.
- `customers`: branch/active/name, branch/phone, branch/date.
- `garment_tags`: order/status, order/scanned, status/scanned, last scanned.
- `payments`: branch/date, order/date, customer/date, receiver/date, method/date.
- `pickup_delivery_tasks`: branch/type/status/schedule and branch/assigned/status/schedule.

The application should continue to use eager loading, paginated lists, short-lived dashboard cache, and repository/service classes for high-volume workflows.
