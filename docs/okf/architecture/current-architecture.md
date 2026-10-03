---
type: System Architecture
title: Current Architecture
description: Architecture and live-verified behavior of the WooCommerce bulk sale-price workflow.
tags:
  - architecture
  - woocommerce
status: stable
---

# Implementation

The plugin bootstrap loads four modules: the admin workflow, variation label classifier, percentage price calculator, and WooCommerce price manager. The WooCommerce submenu is capability-gated. Preview actions read published simple and variable products through WooCommerce APIs and store a user-bound, short-lived preview.

Applying a preview checks all targets again, saves prior sale values and schedule dates in a verified non-autoloaded WordPress option, writes only through WooCommerce CRUD objects, synchronizes variable parents, invalidates product transients, and reads prices back for verification. Backup history supports restoring prior sale values; restoring creates a second snapshot of the current state first.

## Variation labels

For each variation, inspect human-readable variation attribute values. Normalize Persian/Arabic letters, spacing, zero-width characters, and digits before classifying by prefix:

- نسخه مادام العمر — lifetime.
- پرداخت قسط اول از — installment.
- دریافت رایگان دوره — free.

Lifetime removal targets only lifetime variations. Percentage mode skips free and ambiguous variations and skips zero or zero-rounded sale prices.

## Compatibility boundary

WooCommerce APIs own product and variation persistence. The implementation does not update sale-price, effective-price, or related post meta directly and does not write prices with SQL. Variation label resolution follows the official [WooCommerce product variation API](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-product-variation.php); parent synchronization uses [WC_Product_Variable::sync](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-product-variable.php).

## Live validation

On 2026-10-03 the live preview scanned 120 published products and 226 published variations. It matched 89 lifetime variations and proposed 88 sale-price clears. WooCommerce read-back verification reported 88 applied, zero stale, zero failed, and zero parent-sync failures. A fresh preview reported 89 lifetime variations and zero further changes. Product 14550 was verified publicly with the regular lifetime price; its installment and free choices were also checked.

The live price changes are recoverable from backup 06cbb3b4477d49fda9f034f9d7e7ed33. Simple product 13538 has its own sale price but no license variation and remains outside the lifetime-only operation.