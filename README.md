# Biologiism Bulk Price Manager

A standalone WordPress/WooCommerce plugin for managing sale prices in bulk.

It provides two operations:

1. Clear sale prices only for published product variations whose human-readable attribute value starts with نسخه مادام العمر.
2. Apply a chosen percentage sale price to published simple products and paid variations of published variable products.

Every operation has a read-only preview and a stored restore snapshot. Applying a percentage discount replaces any existing sale price and clears its sale schedule so the new sale is immediate. Free variations are excluded from percentage changes. The plugin keeps regular prices unchanged and uses WooCommerce product CRUD APIs.

## Installation

Upload dist/biologiism-bulk-prices-0.1.0.zip through Plugins → Add New Plugin → Upload Plugin, install it, and activate it. The management page appears under WooCommerce → مدیریت گروهی قیمت‌ها.

## Workflow

1. Choose an operation and create a preview.
2. Review the matched products, variation labels, current prices, and proposed sale prices.
3. Apply the preview. The plugin stores the previous sale-price and sale-schedule values before the first write, saves through WooCommerce APIs, synchronizes affected variable parents, and verifies the result.
4. Use the saved snapshot list to restore a prior sale-price state if needed.

Only users with manage_woocommerce or manage_options can use the admin page. No storefront code or public endpoints are added.

## Development

- PHP runtime: C:\php-8.2\gphp.exe
- Lint: C:\php-8.2\gphp.exe -l <file>
- Smoke checks: C:\php-8.2\gphp.exe biologiism-bulk-prices\tests\smoke.php

See [AGENTS.md](AGENTS.md), [HANDOFF.md](HANDOFF.md), and [docs/okf/index.md](docs/okf/index.md) for project instructions and knowledge.
