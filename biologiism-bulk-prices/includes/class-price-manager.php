<?php
/**
 * WooCommerce catalog preview, price update, snapshot, and restore services.
 *
 * @package BiologiismBulkPrices
 */

declare(strict_types=1);

namespace Biologiism\BulkPrices;

defined( 'ABSPATH' ) || exit;

final class PriceManager {
	public const MODE_REMOVE_LIFETIME = 'remove_lifetime';
	public const MODE_PERCENT         = 'percent';

	private const PAGE_SIZE       = 100;
	private const BACKUP_PREFIX   = 'bbpm_backup_';
	private const BACKUP_INDEX    = 'bbpm_backup_index';

	/**
	 * Read published simple and variable products and build a read-only preview.
	 *
	 * @param string   $mode       Requested operation.
	 * @param float|null $percentage Percentage discount.
	 * @return array|\WP_Error
	 */
	public static function build_preview( string $mode, ?float $percentage = null ) {
		if ( ! in_array( $mode, array( self::MODE_REMOVE_LIFETIME, self::MODE_PERCENT ), true ) ) {
			return new \WP_Error( 'invalid_mode', 'عملیات انتخاب‌شده معتبر نیست.' );
		}

		if ( self::MODE_PERCENT === $mode && ( null === $percentage || $percentage < 0.01 || $percentage > 99.99 ) ) {
			return new \WP_Error( 'invalid_percentage', 'درصد تخفیف باید بین ۰٫۰۱ تا ۹۹٫۹۹ باشد.' );
		}

		$preview = array(
			'mode'       => $mode,
			'percentage' => $percentage,
			'created_at' => gmdate( 'c' ),
			'items'      => array(),
			'summary'    => array(
				'products_scanned'          => 0,
				'simple_products_scanned'   => 0,
				'variable_products_scanned' => 0,
				'variations_scanned'        => 0,
				'lifetime_matches'          => 0,
				'free_skipped'              => 0,
				'ambiguous_skipped'         => 0,
				'zero_price_skipped'        => 0,
				'zero_sale_skipped'         => 0,
				'unchanged'                 => 0,
				'change_count'              => 0,
			),
		);

		$page = 1;
		$seen = array();

		do {
			$products = wc_get_products(
				array(
					'status'  => 'publish',
					'type'    => array( 'simple', 'variable' ),
					'limit'   => self::PAGE_SIZE,
					'page'    => $page,
					'return'  => 'objects',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);

			if ( ! is_array( $products ) || empty( $products ) ) {
				break;
			}

			foreach ( $products as $product ) {
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}

				$product_id = (int) $product->get_id();
				if ( isset( $seen[ $product_id ] ) ) {
					continue;
				}
				$seen[ $product_id ] = true;
				++$preview['summary']['products_scanned'];

				if ( $product->is_type( 'simple' ) ) {
					++$preview['summary']['simple_products_scanned'];
					if ( self::MODE_PERCENT === $mode ) {
						self::add_target( $preview, $product, $product, array(), VariationClassifier::NONE, $percentage );
					}
					continue;
				}

				if ( ! $product->is_type( 'variable' ) ) {
					continue;
				}

				++$preview['summary']['variable_products_scanned'];
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( ! $variation instanceof \WC_Product_Variation || 'publish' !== $variation->get_status() ) {
						continue;
					}

					++$preview['summary']['variations_scanned'];
					$attribute_values = self::variation_attribute_values( $variation );
					$class = VariationClassifier::classify_values( $attribute_values );

					if ( self::MODE_REMOVE_LIFETIME === $mode ) {
						if ( VariationClassifier::LIFETIME !== $class ) {
							if ( VariationClassifier::AMBIGUOUS === $class ) {
								++$preview['summary']['ambiguous_skipped'];
							}
							continue;
						}
						++$preview['summary']['lifetime_matches'];
					}

					if ( self::MODE_PERCENT === $mode && VariationClassifier::FREE === $class ) {
						++$preview['summary']['free_skipped'];
						continue;
					}

					if ( self::MODE_PERCENT === $mode && VariationClassifier::AMBIGUOUS === $class ) {
						++$preview['summary']['ambiguous_skipped'];
						continue;
					}

					self::add_target( $preview, $product, $variation, $attribute_values, $class, $percentage );
				}
			}

			++$page;
		} while ( count( $products ) === self::PAGE_SIZE );

		$preview['summary']['change_count'] = count( $preview['items'] );

		return $preview;
	}

	/**
	 * Apply a server-stored preview after checking it is still current.
	 *
	 * @param array $preview Preview data from a user-bound transient.
	 * @return array
	 */
	public static function apply_preview( array $preview ): array {
		$rows = isset( $preview['items'] ) && is_array( $preview['items'] ) ? $preview['items'] : array();
		if ( empty( $rows ) ) {
			return array(
				'status'  => 'empty',
				'applied' => 0,
				'stale'   => 0,
				'failed'  => 0,
				'message' => 'پیش‌نمایش، تغییری برای اعمال ندارد.',
			);
		}

		$stale = 0;
		foreach ( $rows as $row ) {
			$target = self::load_and_validate_preview_row( $row, (string) $preview['mode'], true );
			if ( is_wp_error( $target ) ) {
				++$stale;
			}
		}

		if ( $stale > 0 ) {
			return array(
				'status'  => 'stale',
				'applied' => 0,
				'stale'   => $stale,
				'failed'  => 0,
				'message' => 'قیمت یا ویژگی بعضی موارد از زمان پیش‌نمایش تغییر کرده است. هیچ قیمتی تغییر نکرد؛ پیش‌نمایش تازه بسازید.',
			);
		}

		$backup_items = array();
		foreach ( $rows as $row ) {
			$backup_items[] = self::backup_item_from_preview_row( $row, 'pending' );
		}

		$backup = self::create_backup( $preview, $backup_items );
		if ( is_wp_error( $backup ) ) {
			return array(
				'status'  => 'failed',
				'applied' => 0,
				'stale'   => 0,
				'failed'  => count( $rows ),
				'message' => 'پشتیبان قیمت‌ها ذخیره و بررسی نشد؛ هیچ قیمتی تغییر نکرد.',
			);
		}

		$results = array(
			'status'  => 'completed',
			'applied' => 0,
			'stale'   => 0,
			'failed'  => 0,
			'message' => '',
		);
		$parent_ids = array();
		$backup_items = $backup['items'];
		$backup['status'] = 'applying';
		update_option( $backup['option_key'], $backup, false );
		self::update_backup_index_state( $backup['id'], 'applying' );

		foreach ( $rows as $index => $row ) {
			$product = self::load_and_validate_preview_row( $row, (string) $preview['mode'], true );
			if ( is_wp_error( $product ) ) {
				++$results['stale'];
				$backup_items[ $index ]['apply_state'] = 'stale';
			} else {
				if ( 'variation' === $row['kind'] ) {
					$parent_ids[ (int) $row['product_id'] ] = true;
				}
				$backup_items[ $index ]['apply_state'] = 'applying';
				$backup['items'] = $backup_items;
				update_option( $backup['option_key'], $backup, false );

				try {
					$product->set_sale_price( (string) $row['new_sale_price'] );
					$product->set_date_on_sale_from( null );
					$product->set_date_on_sale_to( null );
					$product->save();
					if ( 'simple' === $row['kind'] ) {
						wc_delete_product_transients( (int) $row['object_id'] );
					}

					$fresh = wc_get_product( (int) $row['object_id'] );
					if ( ! $fresh instanceof \WC_Product || ! self::matches_expected_result( $fresh, $row ) ) {
						++$results['failed'];
						$backup_items[ $index ]['apply_state'] = 'verification_failed';
					} else {
						++$results['applied'];
						$backup_items[ $index ]['apply_state'] = 'changed';
					}
				} catch ( \Throwable $error ) {
					++$results['failed'];
					$backup_items[ $index ]['apply_state'] = 'failed';
				}
			}

			$backup['items'] = $backup_items;
			$backup['status'] = 'applying';
			update_option( $backup['option_key'], $backup, false );
		}

		$sync_failed = 0;
		foreach ( array_keys( $parent_ids ) as $parent_id ) {
			try {
				\WC_Product_Variable::sync( (int) $parent_id );
				wc_delete_product_transients( (int) $parent_id );
			} catch ( \Throwable $error ) {
				++$sync_failed;
			}
		}

		$backup['items'] = $backup_items;
		$backup['status'] = ( $results['stale'] > 0 || $results['failed'] > 0 || $sync_failed > 0 ) ? 'partial' : 'completed';
		$backup['result'] = array(
			'applied'     => $results['applied'],
			'stale'       => $results['stale'],
			'failed'      => $results['failed'],
			'sync_failed' => $sync_failed,
		);
		update_option( $backup['option_key'], $backup, false );
		self::update_backup_index_state( $backup['id'], $backup['status'] );

		$results['sync_failed'] = $sync_failed;
		$results['backup_id'] = $backup['id'];
		$results['status'] = ( $results['stale'] > 0 || $results['failed'] > 0 || $sync_failed > 0 ) ? 'partial' : 'completed';
		$results['message'] = sprintf(
			'تغییرهای تأییدشده: %1$s؛ موارد تغییرکرده پس از پیش‌نمایش: %2$s؛ خطاهای ذخیره یا بررسی: %3$s؛ خطای همگام‌سازی محصول والد: %4$s. شناسه پشتیبان: %5$s',
			number_format_i18n( $results['applied'] ),
			number_format_i18n( $results['stale'] ),
			number_format_i18n( $results['failed'] ),
			number_format_i18n( $sync_failed ),
			$backup['id']
		);

		return $results;
	}

	/**
	 * Restore one previously saved snapshot.
	 *
	 * @param array $backup Snapshot data.
	 * @return array
	 */
	public static function restore_backup( array $backup ): array {
		$source_items = isset( $backup['items'] ) && is_array( $backup['items'] ) ? $backup['items'] : array();
		$restore_items = array();

		foreach ( $source_items as $item ) {
			$state = isset( $item['apply_state'] ) ? (string) $item['apply_state'] : '';
			if ( 'changed' !== $state && 'verification_failed' !== $state && 'failed' !== $state && 'applying' !== $state ) {
				continue;
			}

			$product = self::load_backup_target( $item );
			if ( is_wp_error( $product ) ) {
				return array(
					'status'  => 'failed',
					'applied' => 0,
					'stale'   => 1,
					'failed'  => 0,
					'message' => 'یکی از محصولات پشتیبان دیگر در دسترس نیست؛ هیچ موردی بازگردانی نشد.',
				);
			}

			$restore_items[] = self::backup_item_from_current_product( $product, (int) $item['product_id'], (string) $item['kind'] );
		}

		if ( empty( $restore_items ) ) {
			return array(
				'status'  => 'empty',
				'applied' => 0,
				'stale'   => 0,
				'failed'  => 0,
				'message' => 'در این پشتیبان، مورد تغییرکرده‌ای برای بازگردانی وجود ندارد.',
			);
		}

		$guard = self::create_backup(
			array(
				'mode'       => 'restore_guard',
				'percentage' => null,
				'created_at' => gmdate( 'c' ),
			),
			$restore_items
		);
		if ( is_wp_error( $guard ) ) {
			return array(
				'status'  => 'failed',
				'applied' => 0,
				'stale'   => 0,
				'failed'  => count( $restore_items ),
				'message' => 'پشتیبان وضعیت فعلی ذخیره و بررسی نشد؛ هیچ قیمتی بازگردانی نشد.',
			);
		}

		$applied = 0;
		$failed = 0;
		$parent_ids = array();
		$guard_items = $guard['items'];

		$guard_index_by_object = array();
		foreach ( $guard_items as $guard_index => $guard_item ) {
			$guard_index_by_object[ (int) $guard_item['object_id'] ] = $guard_index;
		}
		$guard['status'] = 'applying';
		update_option( $guard['option_key'], $guard, false );
		self::update_backup_index_state( $guard['id'], 'applying' );

		foreach ( $source_items as $item ) {
			$state = isset( $item['apply_state'] ) ? (string) $item['apply_state'] : '';
			if ( 'changed' !== $state && 'verification_failed' !== $state && 'failed' !== $state && 'applying' !== $state ) {
				continue;
			}

			$product = self::load_backup_target( $item );
			if ( is_wp_error( $product ) ) {
				++$failed;
				continue;
			}

			$guard_index = $guard_index_by_object[ (int) $item['object_id'] ] ?? null;
			if ( null !== $guard_index ) {
				$guard_items[ $guard_index ]['apply_state'] = 'applying';
				$guard['items'] = $guard_items;
				update_option( $guard['option_key'], $guard, false );
			}

			try {
				$product->set_sale_price( (string) $item['sale_price'] );
				$product->set_date_on_sale_from( self::timestamp_to_date( $item['sale_from'] ) );
				$product->set_date_on_sale_to( self::timestamp_to_date( $item['sale_to'] ) );
				$product->save();

				$fresh = wc_get_product( (int) $item['object_id'] );
				if ( ! $fresh instanceof \WC_Product || ! self::matches_sale_snapshot( $fresh, $item ) ) {
					++$failed;
					if ( null !== $guard_index ) {
						$guard_items[ $guard_index ]['apply_state'] = 'verification_failed';
					}
				} else {
					++$applied;
					if ( 'variation' === $item['kind'] ) {
						$parent_ids[ (int) $item['product_id'] ] = true;
					} else {
						wc_delete_product_transients( (int) $item['object_id'] );
					}
					if ( null !== $guard_index ) {
						$guard_items[ $guard_index ]['apply_state'] = 'changed';
					}
				}
			} catch ( \Throwable $error ) {
				++$failed;
				if ( null !== $guard_index ) {
					$guard_items[ $guard_index ]['apply_state'] = 'failed';
				}
			}

			$guard['items'] = $guard_items;
			update_option( $guard['option_key'], $guard, false );
		}

		$sync_failed = 0;
		foreach ( array_keys( $parent_ids ) as $parent_id ) {
			try {
				\WC_Product_Variable::sync( (int) $parent_id );
				wc_delete_product_transients( (int) $parent_id );
			} catch ( \Throwable $error ) {
				++$sync_failed;
			}
		}

		$guard['items'] = $guard_items;
		$guard['status'] = ( $failed > 0 || $sync_failed > 0 ) ? 'partial' : 'completed';
		update_option( $guard['option_key'], $guard, false );
		self::update_backup_index_state( $guard['id'], $guard['status'] );

		return array(
			'status'    => ( $failed > 0 || $sync_failed > 0 ) ? 'partial' : 'completed',
			'applied'   => $applied,
			'stale'     => 0,
			'failed'    => $failed,
			'sync_failed' => $sync_failed,
			'backup_id' => $guard['id'],
			'message'   => sprintf(
				'موارد بازگردانی‌شده: %1$s؛ خطاهای بازگردانی: %2$s؛ خطای همگام‌سازی محصول والد: %3$s. شناسه پشتیبان وضعیت قبل از بازگردانی: %4$s',
				number_format_i18n( $applied ),
				number_format_i18n( $failed ),
				number_format_i18n( $sync_failed ),
				$guard['id']
			),
		);
	}

	/**
	 * Return the stored backup index.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_backup_index(): array {
		$index = get_option( self::BACKUP_INDEX, array() );
		return is_array( $index ) ? array_reverse( $index ) : array();
	}

	/**
	 * Read one saved snapshot by its opaque identifier.
	 *
	 * @param string $id Backup identifier.
	 * @return array|\WP_Error
	 */
	public static function get_backup( string $id ) {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			return new \WP_Error( 'invalid_backup_id', 'شناسه پشتیبان معتبر نیست.' );
		}

		$backup = get_option( self::BACKUP_PREFIX . $id, false );
		if ( ! is_array( $backup ) || empty( $backup['items'] ) ) {
			return new \WP_Error( 'backup_missing', 'پشتیبان انتخاب‌شده پیدا نشد.' );
		}

		return $backup;
	}

	/**
	 * Add a product or variation to the preview when a price change is needed.
	 *
	 * @param array          $preview         Preview by reference.
	 * @param \WC_Product    $parent          Parent product.
	 * @param \WC_Product    $target          Product or variation.
	 * @param array<int,string> $attribute_values Display values.
	 * @param string         $classification License classification.
	 * @param float|null     $percentage      Percentage discount.
	 * @return void
	 */
	private static function add_target( array &$preview, \WC_Product $parent, \WC_Product $target, array $attribute_values, string $classification, ?float $percentage ): void {
		$regular_price = self::price_string( $target->get_regular_price( 'edit' ) );
		$sale_price = self::price_string( $target->get_sale_price( 'edit' ) );
		$sale_from = self::date_timestamp( $target, 'get_date_on_sale_from' );
		$sale_to = self::date_timestamp( $target, 'get_date_on_sale_to' );
		$new_sale_price = '';
		$mode = (string) $preview['mode'];

		if ( self::MODE_REMOVE_LIFETIME === $mode ) {
			if ( '' === $sale_price && null === $sale_from && null === $sale_to ) {
				++$preview['summary']['unchanged'];
				return;
			}
		} else {
			if ( '' === $regular_price || (float) $regular_price <= 0 ) {
				++$preview['summary']['zero_price_skipped'];
				return;
			}

			$new_sale_price = PriceCalculator::discounted_price(
				$regular_price,
				(float) $percentage,
				(int) wc_get_price_decimals()
			);
			if ( null === $new_sale_price ) {
				++$preview['summary']['zero_sale_skipped'];
				return;
			}

			if ( $new_sale_price === $sale_price && null === $sale_from && null === $sale_to ) {
				++$preview['summary']['unchanged'];
				return;
			}
		}

		$preview['items'][] = array(
			'product_id'       => (int) $parent->get_id(),
			'product_name'     => (string) $parent->get_name(),
			'object_id'        => (int) $target->get_id(),
			'kind'             => $target->is_type( 'variation' ) ? 'variation' : 'simple',
			'attribute_values' => array_values( array_map( 'strval', $attribute_values ) ),
			'attribute_label'  => implode( ' / ', array_map( 'strval', $attribute_values ) ),
			'classification'   => $classification,
			'regular_price'    => $regular_price,
			'sale_price'       => $sale_price,
			'sale_from'        => $sale_from,
			'sale_to'          => $sale_to,
			'new_sale_price'   => $new_sale_price,
		);
	}

	/**
	 * Get human-readable values for a variation's selected attributes.
	 *
	 * @param \WC_Product_Variation $variation Variation.
	 * @return array<int,string>
	 */
	private static function variation_attribute_values( \WC_Product_Variation $variation ): array {
		$values = array();
		foreach ( (array) $variation->get_attributes() as $attribute_name => $raw_value ) {
			if ( '' === (string) $raw_value ) {
				continue;
			}

			$display_value = $variation->get_attribute( (string) $attribute_name );
			if ( ! is_string( $display_value ) || '' === $display_value ) {
				$display_value = (string) $raw_value;
			}
			$values[] = $display_value;
		}

		return $values;
	}

	/**
	 * Validate that the target still matches its preview snapshot.
	 *
	 * @param array  $row       Preview row.
	 * @param string $mode      Operation mode.
	 * @param bool   $compare_price Whether to compare prices and schedules.
	 * @return \WC_Product|\WP_Error
	 */
	private static function load_and_validate_preview_row( array $row, string $mode, bool $compare_price ) {
		$object_id = isset( $row['object_id'] ) ? (int) $row['object_id'] : 0;
		$product = $object_id > 0 ? wc_get_product( $object_id ) : false;
		if ( ! $product instanceof \WC_Product || 'publish' !== $product->get_status() ) {
			return new \WP_Error( 'product_missing', 'محصول دیگر منتشرشده یا در دسترس نیست.' );
		}

		if ( 'simple' === $row['kind'] ) {
			if ( ! $product->is_type( 'simple' ) || (int) $product->get_id() !== (int) $row['product_id'] ) {
				return new \WP_Error( 'product_type_changed', 'نوع محصول تغییر کرده است.' );
			}
		} else {
			if ( ! $product instanceof \WC_Product_Variation || (int) $product->get_parent_id() !== (int) $row['product_id'] ) {
				return new \WP_Error( 'variation_parent_changed', 'variation دیگر به محصول پیش‌نمایش‌شده تعلق ندارد.' );
			}
			$parent = wc_get_product( (int) $row['product_id'] );
			if ( ! $parent instanceof \WC_Product_Variable || 'publish' !== $parent->get_status() ) {
				return new \WP_Error( 'parent_unpublished', 'محصول متغیر والد دیگر منتشرشده نیست.' );
			}

			$current_values = self::variation_attribute_values( $product );
			$expected_values = isset( $row['attribute_values'] ) && is_array( $row['attribute_values'] ) ? $row['attribute_values'] : array();
			$current_values = array_map( array( VariationClassifier::class, 'normalize' ), $current_values );
			$expected_values = array_map( array( VariationClassifier::class, 'normalize' ), $expected_values );
			sort( $current_values, SORT_STRING );
			sort( $expected_values, SORT_STRING );
			if ( $current_values !== $expected_values ) {
				return new \WP_Error( 'variation_attributes_changed', 'ویژگی‌های variation تغییر کرده است.' );
			}

			$class = VariationClassifier::classify_values( self::variation_attribute_values( $product ) );
			if ( self::MODE_REMOVE_LIFETIME === $mode && VariationClassifier::LIFETIME !== $class ) {
				return new \WP_Error( 'not_lifetime', 'variation دیگر از نوع مادام‌العمر نیست.' );
			}
			if ( self::MODE_PERCENT === $mode && in_array( $class, array( VariationClassifier::FREE, VariationClassifier::AMBIGUOUS ), true ) ) {
				return new \WP_Error( 'excluded_variation', 'variation رایگان یا مبهم است.' );
			}
		}

		if ( ! $compare_price ) {
			return $product;
		}

		if ( self::price_string( $product->get_regular_price( 'edit' ) ) !== (string) $row['regular_price']
			|| self::price_string( $product->get_sale_price( 'edit' ) ) !== (string) $row['sale_price']
			|| self::date_timestamp( $product, 'get_date_on_sale_from' ) !== $row['sale_from']
			|| self::date_timestamp( $product, 'get_date_on_sale_to' ) !== $row['sale_to']
		) {
			return new \WP_Error( 'price_changed', 'قیمت یا زمان‌بندی فروش از زمان پیش‌نمایش تغییر کرده است.' );
		}

		return $product;
	}

	/**
	 * Verify the new sale price and that its schedule is cleared.
	 *
	 * @param \WC_Product $product Saved product.
	 * @param array       $row     Preview row.
	 * @return bool
	 */
	private static function matches_expected_result( \WC_Product $product, array $row ): bool {
		return self::price_string( $product->get_regular_price( 'edit' ) ) === (string) $row['regular_price']
			&& self::price_string( $product->get_sale_price( 'edit' ) ) === (string) $row['new_sale_price']
			&& null === self::date_timestamp( $product, 'get_date_on_sale_from' )
			&& null === self::date_timestamp( $product, 'get_date_on_sale_to' );
	}

	/**
	 * Capture one current product/variation state for a restore guard.
	 *
	 * @param \WC_Product $product Product object.
	 * @param int         $parent_id Parent product ID.
	 * @param string      $kind Product kind.
	 * @return array
	 */
	private static function backup_item_from_current_product( \WC_Product $product, int $parent_id, string $kind ): array {
		return array(
			'product_id'    => $parent_id,
			'object_id'     => (int) $product->get_id(),
			'kind'          => $kind,
			'regular_price' => self::price_string( $product->get_regular_price( 'edit' ) ),
			'sale_price'    => self::price_string( $product->get_sale_price( 'edit' ) ),
			'sale_from'     => self::date_timestamp( $product, 'get_date_on_sale_from' ),
			'sale_to'       => self::date_timestamp( $product, 'get_date_on_sale_to' ),
			'apply_state'   => 'pending',
		);
	}

	/**
	 * Build a restore item from a preview row.
	 *
	 * @param array  $row Preview row.
	 * @param string $state Current apply state.
	 * @return array
	 */
	private static function backup_item_from_preview_row( array $row, string $state ): array {
		return array(
			'product_id'    => (int) $row['product_id'],
			'object_id'     => (int) $row['object_id'],
			'kind'          => (string) $row['kind'],
			'regular_price' => (string) $row['regular_price'],
			'sale_price'    => (string) $row['sale_price'],
			'sale_from'     => $row['sale_from'],
			'sale_to'       => $row['sale_to'],
			'apply_state'   => $state,
		);
	}

	/**
	 * Create and verify a non-autoloaded recoverable backup.
	 *
	 * @param array $context Operation context.
	 * @param array $items Snapshot items.
	 * @return array|\WP_Error
	 */
	private static function create_backup( array $context, array $items ) {
		$id = str_replace( '-', '', wp_generate_uuid4() );
		$option_key = self::BACKUP_PREFIX . $id;
		$backup = array(
			'id'         => $id,
			'option_key' => $option_key,
			'created_at' => gmdate( 'c' ),
			'created_by' => get_current_user_id(),
			'mode'       => isset( $context['mode'] ) ? (string) $context['mode'] : 'restore_guard',
			'percentage' => isset( $context['percentage'] ) ? $context['percentage'] : null,
			'status'     => 'prepared',
			'items'      => array_values( $items ),
		);

		if ( ! add_option( $option_key, $backup, '', false ) ) {
			return new \WP_Error( 'backup_insert_failed', 'ذخیره پشتیبان ناموفق بود.' );
		}

		$stored = get_option( $option_key, false );
		if ( ! is_array( $stored ) || ! self::backup_matches( $stored, $backup ) ) {
			delete_option( $option_key );
			return new \WP_Error( 'backup_verify_failed', 'بررسی پشتیبان ناموفق بود.' );
		}

		$index = get_option( self::BACKUP_INDEX, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		$index[] = array(
			'id'         => $id,
			'created_at' => $backup['created_at'],
			'mode'       => $backup['mode'],
			'item_count' => count( $items ),
			'status'     => 'prepared',
		);
		update_option( self::BACKUP_INDEX, $index, false );
		$stored_index = get_option( self::BACKUP_INDEX, array() );
		$last_index = is_array( $stored_index ) && ! empty( $stored_index ) ? $stored_index[ count( $stored_index ) - 1 ] : null;
		if ( ! is_array( $last_index ) || ! isset( $last_index['id'] ) || $last_index['id'] !== $id ) {
			delete_option( $option_key );
			return new \WP_Error( 'backup_index_failed', 'ثبت شناسه پشتیبان ناموفق بود.' );
		}

		return $backup;
	}

	/**
	 * Verify the persisted recovery values before permitting the first price write.
	 *
	 * @param array $stored Persisted option value.
	 * @param array $expected In-memory snapshot.
	 * @return bool
	 */
	private static function backup_matches( array $stored, array $expected ): bool {
		if ( ( $stored['id'] ?? null ) !== ( $expected['id'] ?? null )
			|| ! isset( $stored['items'], $expected['items'] )
			|| ! is_array( $stored['items'] )
			|| ! is_array( $expected['items'] )
			|| count( $stored['items'] ) !== count( $expected['items'] )
		) {
			return false;
		}

		$fields = array( 'product_id', 'object_id', 'kind', 'regular_price', 'sale_price', 'sale_from', 'sale_to', 'apply_state' );
		foreach ( $expected['items'] as $index => $expected_item ) {
			if ( ! isset( $stored['items'][ $index ] ) || ! is_array( $stored['items'][ $index ] ) ) {
				return false;
			}
			foreach ( $fields as $field ) {
				if ( ( $stored['items'][ $index ][ $field ] ?? null ) !== ( $expected_item[ $field ] ?? null ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Load a backup target and confirm that its WooCommerce object still exists.
	 *
	 * @param array $item Snapshot item.
	 * @return \WC_Product|\WP_Error
	 */
	private static function load_backup_target( array $item ) {
		$product = wc_get_product( (int) $item['object_id'] );
		if ( ! $product instanceof \WC_Product ) {
			return new \WP_Error( 'backup_target_missing', 'محصول پشتیبان پیدا نشد.' );
		}

		if ( 'variation' === $item['kind'] ) {
			if ( ! $product instanceof \WC_Product_Variation || (int) $product->get_parent_id() !== (int) $item['product_id'] ) {
				return new \WP_Error( 'backup_variation_mismatch', 'variation پشتیبان تغییر کرده است.' );
			}
		} elseif ( ! $product->is_type( 'simple' ) || (int) $product->get_id() !== (int) $item['product_id'] ) {
			return new \WP_Error( 'backup_product_mismatch', 'محصول پشتیبان تغییر کرده است.' );
		}

		return $product;
	}

	/**
	 * Verify sale price and schedule against a stored snapshot.
	 *
	 * @param \WC_Product $product Product object.
	 * @param array       $item Snapshot.
	 * @return bool
	 */
	private static function matches_sale_snapshot( \WC_Product $product, array $item ): bool {
		return self::price_string( $product->get_sale_price( 'edit' ) ) === (string) $item['sale_price']
			&& self::date_timestamp( $product, 'get_date_on_sale_from' ) === $item['sale_from']
			&& self::date_timestamp( $product, 'get_date_on_sale_to' ) === $item['sale_to'];
	}

	/**
	 * Update a backup's status in the admin index.
	 *
	 * @param string $id Backup ID.
	 * @param string $status State.
	 * @return void
	 */
	private static function update_backup_index_state( string $id, string $status ): void {
		$index = get_option( self::BACKUP_INDEX, array() );
		if ( ! is_array( $index ) ) {
			return;
		}

		foreach ( $index as &$entry ) {
			if ( isset( $entry['id'] ) && $id === $entry['id'] ) {
				$entry['status'] = $status;
				break;
			}
		}
		unset( $entry );
		update_option( self::BACKUP_INDEX, $index, false );
	}

	/**
	 * Get a WooCommerce date property as a Unix timestamp.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $method Getter method.
	 * @return int|null
	 */
	private static function date_timestamp( \WC_Product $product, string $method ): ?int {
		$date = $product->{$method}( 'edit' );
		return $date instanceof \DateTimeInterface ? (int) $date->getTimestamp() : null;
	}

	/**
	 * Convert a stored timestamp to a WooCommerce-compatible date value.
	 *
	 * @param mixed $timestamp Timestamp or null.
	 * @return int|null
	 */
	private static function timestamp_to_date( $timestamp ): ?int {
		return is_numeric( $timestamp ) ? (int) $timestamp : null;
	}

	/**
	 * Format a stored price for exact comparison.
	 *
	 * @param mixed $price Price value.
	 * @return string
	 */
	private static function price_string( $price ): string {
		if ( null === $price || '' === $price ) {
			return '';
		}
		return wc_format_decimal( (string) $price, (int) wc_get_price_decimals() );
	}
}
