---
type: Project Overview
title: Biologiism Bulk Price Manager
description: Scope, live behavior, and supported operations for the WooCommerce bulk pricing plugin.
tags:
  - wordpress
  - woocommerce
  - pricing
status: stable
---

# Purpose

Provide an administrator-only workflow for bulk sale-price changes on the Biologiism WooCommerce store.

## Scope

- Clear sale prices and scheduled sale dates for lifetime-license variations only.
- Apply a selected percentage sale price to published simple products and paid variations of published variable products.
- Preview all proposed changes before applying them.
- Preserve a recoverable snapshot and support restoring a previous sale-price state.

## Boundaries

The plugin does not change regular prices, product descriptions, orders, customers, course access, or the storefront theme. It uses WooCommerce product CRUD APIs and preserves a per-operation restore snapshot.

## Site evidence

A read-only review of [product 14550](https://biologiism.com/product/medical-genetics-for-the-ministry-of-healths-masters-and-phd-entrance-exams/) showed a variable product with a payment attribute and three values: lifetime license, five-installment payment, and free 42-day access. Before the update, lifetime variation 14578 had regular price 11034000 and sale price 2758500; the installment variation 15176 had regular price 2133000 and no sale price; the free variation 15177 had regular price 0 and no sale price.

The active plugin preview scanned 120 published simple and variable products and 226 published variations. It found 89 lifetime-license variations: 88 had a sale price to remove and one already had no sale price or schedule. All 88 proposed rows had the lifetime attribute value "نسخه مادام العمر لایسنس آنی"; no installment or free rows appeared. The apply result verified 88 changes with no stale items, save failures, or parent-sync failures. A post-update preview found 89 lifetime variations and zero further changes.

## Public verification

After the update, the public page for product 14550 showed the lifetime price at 11034000 with no former 2758500 sale price or 75% badge. Selecting the installment option still showed 2133000; the free option remained free.

## Scope exception

Product 13538, دوره آموزشی ژنتیک بیوشیمیایی, is a simple product rather than a variable product and has no license selector. Its regular price is 766000 and its sale price remains 191500. It was not part of the lifetime-variation operation and was left unchanged.