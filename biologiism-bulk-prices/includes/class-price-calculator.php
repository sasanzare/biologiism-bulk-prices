<?php
/**
 * Pure percentage-sale calculation.
 *
 * @package BiologiismBulkPrices
 */

declare(strict_types=1);

namespace Biologiism\BulkPrices;

defined( 'ABSPATH' ) || exit;

final class PriceCalculator {
	/**
	 * Calculate a sale price from the regular price.
	 *
	 * The result follows WooCommerce's configured decimal precision and never
	 * returns zero: a discount that rounds to zero is skipped rather than made free.
	 *
	 * @param string     $regular_price Regular price in WooCommerce storage units.
	 * @param float      $percentage    Percentage discount from 0.01 to 99.99.
	 * @param int        $decimals      Store price precision.
	 * @return string|null
	 */
	public static function discounted_price( string $regular_price, float $percentage, int $decimals ): ?string {
		if ( ! is_numeric( $regular_price ) || ! is_finite( (float) $regular_price ) ) {
			return null;
		}

		if ( $percentage < 0.01 || $percentage > 99.99 || (float) $regular_price <= 0 ) {
			return null;
		}

		$decimals = max( 0, min( 6, $decimals ) );
		$discounted = round(
			(float) $regular_price * ( 100 - $percentage ) / 100,
			$decimals,
			PHP_ROUND_HALF_UP
		);

		if ( $discounted <= 0 ) {
			return null;
		}

		return number_format( $discounted, $decimals, '.', '' );
	}
}