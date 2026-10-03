# Biologiism Bulk Price Manager

This plugin adds a WooCommerce admin page for bulk sale-price operations.

## Operations

- Lifetime-only removal: clears the sale price and sale schedule only from published product variations whose normalized human-readable attribute value starts with “نسخه مادام العمر”.
- Percentage sale: calculates a selected percentage from the regular price of published simple products and paid variations of published variable products. It replaces the current sale price and clears any existing sale schedule so the new sale is immediate. It skips free, ambiguous, zero-price, and zero-rounded-price cases.

## Safety

- A read-only preview lists each target and old/proposed sale price.
- Applying is blocked if any target's price, schedule, parent product, or attribute values changed after preview.
- A non-autoloaded WordPress option stores prior sale prices and schedules before the first mutation.
- Updates use WooCommerce CRUD APIs, synchronize variable parents, and verify saved values.
- The backup list supports restoring previous sale prices; the restore operation first records the current values as another backup.
- Only users with manage_woocommerce or manage_options can view or operate the admin page.
