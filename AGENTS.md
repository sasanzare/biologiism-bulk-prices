# Project Instructions

## Purpose and boundaries
This project contains a standalone WooCommerce plugin for previewing and applying bulk sale-price changes to published simple and variable products on Biologiism. It does not change product descriptions, regular prices, orders, users, or course access.

## Architecture and safety rules
- Use WooCommerce product CRUD APIs for every price write; do not write price post meta or SQL directly.
- Keep lifetime-only removal scoped to a normalized variation attribute value that starts with نسخه مادام العمر.
- Require a read-only preview and a verified recoverable snapshot before applying changes.
- Gate every admin action with capability checks, nonces, server-side input validation, and escaped output.
- Keep unrelated reference repositories and existing user changes untouched.

## Build and validation
- PHP CLI is available at C:\php-8.2\gphp.exe; php is not on PATH.
- Lint PHP files with C:\php-8.2\gphp.exe -l <file>.
- Run the plugin smoke checks with C:\php-8.2\gphp.exe biologiism-bulk-prices\tests\smoke.php.

## Documentation map
- Product usage and installation: [README.md](README.md)
- Continuation checkpoint: [HANDOFF.md](HANDOFF.md)
- Structured project knowledge: [docs/okf/index.md](docs/okf/index.md)

## Sensitive areas
- includes/ contains code that can change live WooCommerce prices and backups; review the preview/apply boundary before modifying it.
- dist/ contains installable plugin archives. Do not upload or activate a newly built archive without following the user's request and the applicable action-time confirmation policy.
