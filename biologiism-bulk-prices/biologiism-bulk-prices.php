<?php
/**
 * Plugin Name: Biologiism Bulk Price Manager
 * Description: Preview, apply, and restore WooCommerce sale-price changes in bulk.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: biologiism-bulk-prices
 *
 * @package BiologiismBulkPrices
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-variation-classifier.php';
require_once __DIR__ . '/includes/class-price-calculator.php';
require_once __DIR__ . '/includes/class-price-manager.php';
require_once __DIR__ . '/includes/class-admin.php';

add_action(
	'plugins_loaded',
	array( \Biologiism\BulkPrices\Admin::class, 'init' ),
	20
);