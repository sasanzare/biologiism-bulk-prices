<?php
/**
 * Persian variation-label normalization and classification.
 *
 * @package BiologiismBulkPrices
 */

declare(strict_types=1);

namespace Biologiism\BulkPrices;

defined( 'ABSPATH' ) || exit;

final class VariationClassifier {
	public const LIFETIME    = 'lifetime';
	public const INSTALLMENT = 'installment';
	public const FREE        = 'free';
	public const NONE        = 'none';
	public const AMBIGUOUS   = 'ambiguous';

	/**
	 * Normalize variation labels before matching.
	 *
	 * @param string $text Raw attribute value.
	 * @return string
	 */
	public static function normalize( string $text ): string {
		$text = str_replace(
			array(
				"\u{200B}", "\u{200D}", "\u{FEFF}", "\u{202A}",
				"\u{202B}", "\u{202C}", "\u{200E}", "\u{200F}",
			),
			'',
			$text
		);

		$decoded = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $decoded !== $text ) {
			$decoded = html_entity_decode( $decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		$text = $decoded;

		$text = str_replace( "\u{064A}", "\u{06CC}", $text );
		$text = str_replace( "\u{0643}", "\u{06A9}", $text );
		$text = str_replace( "\u{0629}", "\u{0647}", $text );
		$text = str_replace( "\u{0640}", '', $text );
		$text = str_replace( "\u{00A0}", ' ', $text );
		$text = str_replace( "\u{200C}", '', $text );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		$text = trim( $text );

		return strtr(
			$text,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);
	}

	/**
	 * Classify one human-readable variation attribute value by its prefix.
	 *
	 * @param string $label Attribute value.
	 * @return string
	 */
	public static function classify( string $label ): string {
		$value = self::normalize( $label );

		$prefixes = array(
			self::LIFETIME    => 'نسخه مادام العمر',
			self::INSTALLMENT => 'پرداخت قسط اول از',
			self::FREE        => 'دریافت رایگان دوره',
		);

		foreach ( $prefixes as $type => $prefix ) {
			if ( 0 === strpos( $value, $prefix ) ) {
				return $type;
			}
		}

		return self::NONE;
	}

	/**
	 * Classify all attribute values for a variation.
	 *
	 * Multiple conflicting license labels are treated as ambiguous and excluded.
	 *
	 * @param array<int,string> $values Human-readable attribute values.
	 * @return string
	 */
	public static function classify_values( array $values ): string {
		$matches = array();

		foreach ( $values as $value ) {
			$type = self::classify( (string) $value );
			if ( self::NONE !== $type ) {
				$matches[ $type ] = true;
			}
		}

		if ( count( $matches ) > 1 ) {
			return self::AMBIGUOUS;
		}

		if ( 1 === count( $matches ) ) {
			return (string) array_key_first( $matches );
		}

		return self::NONE;
	}
}