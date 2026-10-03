# Project Handoff

## Last Updated
2026-10-03

## Project Summary
A standalone WordPress/WooCommerce plugin for bulk sale-price changes on Biologiism. The supplied workspace began empty and has no Git metadata.

## Current Objective
Remove sale-price discounts from lifetime-license variations on biologiism.com and leave a recoverable, documented result. The plugin also supports applying percentage sale prices to published paid simple products and variable-product variations.

## Current Phase or Milestone
Lifetime variation sale prices have been cleared and verified. The plugin remains active. One simple product sale remains outside the lifetime-variation scope.

## Repository State
- Repository path: D:\All projects\Discounts_biologiism
- Current branch: Not applicable; no .git directory.
- Base or starting commit: Not applicable.
- Current HEAD: Not applicable.
- Working tree status: Not a Git repository; the directory was empty at task start.
- Staged changes: Not applicable.
- Unstaged changes: Not applicable.
- Untracked files: Not applicable.

## Completed Work
- Reviewed the persistent mistakes log and relevant WordPress/WooCommerce entries; appended a reusable note about defining ABSPATH in standalone PHP smoke checks.
- Inspected product 14550 in the live WordPress editor. It is variable with lifetime, five-installment, and free 42-day variations. Before changes, lifetime variation 14578 had regular price 11034000 and sale price 2758500; installment variation 15176 had regular price 2133000 and no sale price; free variation 15177 had regular price 0 and no sale price.
- Created project AGENTS.md, README.md, HANDOFF.md, and an OKF 0.2 bundle.
- Implemented and packaged plugin version 0.1.0. The plugin includes Persian variation classification, percentage price calculation, read-only previews, verified snapshots, WooCommerce CRUD updates, parent sync, cache invalidation, read-back verification, and restore.
- Ran PHP lint on all six plugin PHP files and the eight focused smoke checks; both passed.
- The user installed and activated version 0.1.0.
- Live preview scanned 120 published products and 226 published variations. It found 89 lifetime-license variations: 88 had sale prices to clear; one already had no sale price or schedule.
- Verified all 88 proposed rows had the lifetime attribute value نسخه مادام العمر لایسنس آنی. No installment or free rows appeared.
- Applied the preview. WooCommerce read-back verification reported 88 applied, zero stale, zero failed, and zero parent-sync failures.
- Ran a fresh lifetime-only preview after application: 89 lifetime variations found, all 89 unchanged, zero further changes.
- Public verification of product 14550 showed lifetime price 11034000 with no former sale price 2758500 or 75% badge. The installment option remained 2133000; the free option remained free.
- Inspected the remaining 75% badge on product 13538. WooCommerce identifies it as a simple product with no license selector; regular price is 766000 and sale price is 191500. It was left unchanged because it is outside the lifetime-variation scope.
- Updated the OKF project overview, architecture, log, and this handoff with the final live result.

## Work in Progress
None for the requested lifetime-variation operation.

## Remaining Work
No remaining work for removing sale prices from lifetime variations. If the user wants simple products without license variations discounted/undiscounted too, clarify the scope before changing those prices.

## Exact Next Steps
1. No further price action is required for lifetime variations.
2. If requested, add a separate operation for simple-product sale prices, preview those products, and apply only after scope is clear.
3. Keep the active plugin available for future previews, percentage operations, and backup restoration.

## Files Created
- AGENTS.md, README.md, HANDOFF.md
- docs/okf/index.md, log.md, project-overview.md
- docs/okf/architecture/index.md, current-architecture.md
- docs/okf/decisions/index.md, bulk-pricing-safety.md
- biologiism-bulk-prices/biologiism-bulk-prices.php
- biologiism-bulk-prices/README.md
- biologiism-bulk-prices/includes/class-admin.php
- biologiism-bulk-prices/includes/class-price-calculator.php
- biologiism-bulk-prices/includes/class-price-manager.php
- biologiism-bulk-prices/includes/class-variation-classifier.php
- biologiism-bulk-prices/tests/smoke.php
- dist/biologiism-bulk-prices-0.1.0.zip

## Files Modified
All local files were created in the previously empty workspace. The OKF overview, architecture, decision, log, and handoff were updated during implementation and live validation. No adjacent project files were modified.

## Important Architecture and Design Decisions
- Lifetime matching uses normalized human-readable variation values beginning with نسخه مادام العمر.
- Lifetime-only removal leaves installment and free variations and all regular prices unchanged.
- Percentage mode covers published simple products and paid variations of published variable products. It skips free, ambiguous, zero-price, and zero-rounded-price cases.
- Percentage mode derives sale prices from regular prices and clears old sale schedules.
- Price writes use WooCommerce CRUD APIs, verified snapshots, parent synchronization, and read-back verification.
- Restore creates a second snapshot of the current state before restoring prior sale values.

## Commands Executed
- Inspected the initial workspace and confirmed it has no Git repository.
- Read relevant mistakes-log entries and adjacent importer documentation.
- Ran PHP lint on all six plugin PHP files.
- Ran tests/smoke.php: 8 smoke checks passed.
- Checked OKF concept frontmatter structure and internal links: no missing type/frontmatter or broken internal links.
- Searched plugin source for direct post-meta/SQL price write APIs; no matches.
- Checked for a YAML parser module; PyYAML and PowerShell YAML modules are unavailable.
- Built and checked the plugin ZIP contents and SHA-256.
- Used WordPress admin and public product pages to verify plugin activation, preview scope, update result, backup, and prices.

## Validation and Test Results
- PHP 8.2.28 syntax lint: passed for all six PHP files.
- Pure-logic smoke checks: 8 passed.
- OKF frontmatter structure and relative Markdown links: passed structural checks; YAML parser unavailable.
- ZIP entry validation: passed.
- Plugin active on WordPress: confirmed by the plugins list and admin page.
- Live apply: 88 lifetime sale-price clears verified; zero stale, save, or parent-sync errors.
- Post-apply preview: 89 lifetime variations unchanged, zero changes ready.
- Public product 14550: lifetime shows regular 11034000 with no sale price; installment remains 2133000 and free stays free.
- Simple product 13538 still has a 75% sale and no license selector; it was not changed.

## Known Issues and Blockers
- The supplied workspace has no Git repository.
- Product 13538 is a simple product with a sale price but no lifetime variation; it remains outside the requested lifetime-only scope.

## Risks and Assumptions
- The plugin is active and available to users with manage_woocommerce or manage_options.
- Only published simple and variable products are in percentage scope; grouped and external products are not directly modified.
- Product 14550 confirmed the variation label pattern; the live preview matched the same lifetime value across all 88 changed rows.
- Prices remain in WooCommerce's configured unit; no currency conversion is performed.
- The adjacent WooCommerce price importer repository has pre-existing modifications and remains untouched.

## Database or Migration State
No plugin tables were created. Eighty-eight WooCommerce variation sale prices and their sale schedules were cleared using WooCommerce CRUD. Restore snapshot ID: 06cbb3b4477d49fda9f034f9d7e7ed33. No regular price was changed.

## Configuration and Environment Notes
The authenticated WordPress admin session is available in Chrome. The active plugin page is under WooCommerce → مدیریت گروهی قیمت‌ها.

## Uncommitted or Partially Applied Changes
This workspace is not a Git repository. The plugin is installed and active. The requested lifetime sale-price operation completed and was verified; no partial price changes remain.

## Recovery or Rollback Notes
Use backup 06cbb3b4477d49fda9f034f9d7e7ed33 in the plugin backup list to restore the prior sale price and sale schedule for the 88 changed variations. Restore itself takes a new snapshot first.

## Related Documentation
- [Project instructions](AGENTS.md)
- [Project README](README.md)
- [OKF bundle index](docs/okf/index.md)

## Notes for the Next Agent
The user asked to remove lifetime-license discounts. That scope is complete. Do not clear the simple-product discount on product 13538 unless the user broadens the request.