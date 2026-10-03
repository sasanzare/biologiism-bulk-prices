<?php
/**
 * Focused pure-logic smoke checks.
 *
 * @package BiologiismBulkPrices
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );

require_once __DIR__ . '/../includes/class-variation-classifier.php';
require_once __DIR__ . '/../includes/class-price-calculator.php';

use Biologiism\BulkPrices\PriceCalculator;
use Biologiism\BulkPrices\VariationClassifier;

function bbpm_expect_same( $expected, $actual, string $name ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $name . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

bbpm_expect_same( VariationClassifier::LIFETIME, VariationClassifier::classify( 'نسخه مادام العمر لایسنس آنی' ), 'lifetime label' );
bbpm_expect_same( VariationClassifier::INSTALLMENT, VariationClassifier::classify( 'پرداخت قسط اول از ۵ قسط' ), 'installment label' );
bbpm_expect_same( VariationClassifier::FREE, VariationClassifier::classify( 'دريافت رايگان دوره به مدت ٤٢ روز' ), 'Arabic letters and digits' );
bbpm_expect_same( VariationClassifier::NONE, VariationClassifier::classify( 'خرید نسخه مادام العمر دوره' ), 'embedded lifetime prefix' );
bbpm_expect_same( VariationClassifier::AMBIGUOUS, VariationClassifier::classify_values( array( 'نسخه مادام العمر لایسنس آنی', 'دریافت رایگان دوره' ) ), 'conflicting values' );
bbpm_expect_same( '2758500', PriceCalculator::discounted_price( '11034000', 75.0, 0 ), '75 percent sale price' );
bbpm_expect_same( '90.00', PriceCalculator::discounted_price( '100.00', 10.0, 2 ), 'decimal sale price' );
bbpm_expect_same( null, PriceCalculator::discounted_price( '1', 99.99, 0 ), 'no zero-valued sale' );

echo "8 smoke checks passed.\n";