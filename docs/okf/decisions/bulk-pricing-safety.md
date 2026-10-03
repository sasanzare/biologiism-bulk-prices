---
type: Architecture Decision
title: Bulk Pricing Safety
description: Safety constraints for bulk sale-price changes in WooCommerce.
tags:
  - pricing
  - decision
status: stable
---

# Decision

All price changes will go through a read-only preview, a recoverable snapshot, WooCommerce CRUD, parent synchronization, and read-back verification.

## Scope rules

- Lifetime removal matches normalized human-readable variation values that start with نسخه مادام العمر.
- Installment and free variations are not targets of lifetime removal.
- Percentage mode applies to published simple products and paid variations of published variable products; zero-priced and free variations are excluded.
- Regular prices remain unchanged.

## Rationale

The live product editor shows three distinct payment choices for a variable course: lifetime, installment, and free trial. Matching variation labels avoids changing the other license options. WooCommerce product objects keep derived prices and parent ranges consistent; the snapshot provides an explicit recovery path if the bulk result needs reversal.